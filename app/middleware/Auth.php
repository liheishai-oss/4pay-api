<?php
namespace app\middleware;

use app\common;
use app\model\Admin;
use app\model\AdminLog;
use app\model\RoleGroup;
use ReflectionClass;
use support\Log;
use support\Redis;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\exception\MyBusinessException;
class Auth implements MiddlewareInterface
{

    public function process(Request $request, callable $handler) : Response
    {
        // 当路由未命中或尚未解析出控制器/方法时，直接放行，避免反射空值导致500
        if (empty($request->controller) || empty($request->action)) {
            return $handler($request);
        }

        $controller = new ReflectionClass($request->controller);
        $noNeedLogin = $controller->getDefaultProperties()['noNeedLogin'] ?? [];

        // 如果noNeedLogin包含*，则跳过认证
        if (in_array('*', $noNeedLogin)) {
            return $handler($request);
        }

        // 如果动作不在noNeedLogin中，则需要认证
        if (!in_array($request->action, $noNeedLogin)) {

            $token = request()->header('Authorization') ?? false;
            if (!$token) {
                throw new MyBusinessException('请登录后操作1',401);
            }

            if (str_starts_with(strtolower($token), 'bearer ')) {
                $token = trim(substr($token, 7)); // 截取 'Bearer ' 后面的内容
            }
            try{
                $userData = Redis::hGetAll(common::LOGIN_TOKEN_PREFIX."admin:{$token}") ?: throw new MyBusinessException('请登录后操作2',401);
                $userData['group_id'] = json_decode($userData['group_id'],true);
            } catch (\Exception $e) {

                if($e->getCode() == 401){
                    throw $e;
                }
                Log::error('redis异常',[
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                $userinfo = Admin::where(['token'=>$token])->first();
                if (!$userinfo) {
                    throw new MyBusinessException('请登录后操作3',401);
                }

                $allGroupIds = $this->getAllSubGroupIds($userinfo->group_id);
                $allGroupIds[] = $userinfo->group_id;

                // 检查是否为商户管理员：通过admin_id查询商户表
                $isMerchantAdmin = \app\model\Merchant::where('admin_id', $userinfo->id)->exists();
                
                $userData = [
                    'admin_id' => $userinfo->id,
                    'nickname' => $userinfo->nickname,
                    'username' => $userinfo->username,
                    'user_group_id' => $userinfo->group_id,
                    'group_id' => $allGroupIds,
                    'status' => $userinfo->status,
                    'is_merchant_admin' => $isMerchantAdmin,
                ];

            }
            $request->userData = $userData;
        }

        // 执行控制器
        $response = $handler($request);

        // 记录操作日志
        if (isset($request->userData)) {
            $this->logUserOperation($request);
        }

        return $response;
    }
    private function logUserOperation(Request $request)
    {
        $user = $request->userData;
        AdminLog::insert([
            'admin_id' => $user['admin_id'],
            'username' => $user['username'],
            'route' => $request->path(),
            'method' => $request->method(),
            'params' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            'ip' => $request->getRealIp(true),
            'user_agent' => $request->header('user-agent', ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
    private function getAllSubGroupIds($groupId): array
    {
        $groupIds = [];
        $this->getSubGroupIdsRecursive($groupId, $groupIds);
        return $groupIds;
    }

    private function getSubGroupIdsRecursive($parentGroupId, &$groupIds)
    {
        // 获取当前父分组的子分组
        $subGroups = RoleGroup::where('parent_id', $parentGroupId)->get();

        foreach ($subGroups as $subGroup) {
            // 将当前分组ID添加到结果数组中
            $groupIds[] = $subGroup->id;

            // 递归查询子分组
            $this->getSubGroupIdsRecursive($subGroup->id, $groupIds);
        }
    }
}