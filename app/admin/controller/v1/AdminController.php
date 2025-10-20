<?php
namespace app\admin\controller\v1;

use app\common;
use app\common\config\OrderConfig;
use app\exception\MyBusinessException;
use app\model\Admin;
use app\model\AdminRule;
use app\model\PermissionGroup;
use app\service\Admin as AdminService;
use app\service\Login;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

class AdminController
{

   public function index(Request $request): Response
{
    $param = $request->all();
    $search = json_decode($param['search'] ?? '{}', true);

    // 处理嵌套的search对象
    if (isset($search['search']) && is_array($search['search'])) {
        $search = $search['search'];
    }

    // 检查用户数据
    if (!isset($request->userData) || !is_array($request->userData)) {
        return error('用户未登录或登录信息无效', 401);
    }
    
    $group_id = $request->userData['group_id'] ?? null;
    if (!$group_id) {
        return error('用户组信息不完整', 401);
    }

    // 构建基础查询
    $query = Admin::with('group')->whereIn('group_id', $group_id);

    // 添加搜索条件
    if (!empty($search['nickname'])) {
        $query->where('nickname', 'like', "%" . trim($search['nickname']) . "%");
    }

    if (!empty($search['username'])) {
        $query->where('username', 'like', "%" . trim($search['username']) . "%");
    }

    // 分页获取数据
    $data = $query->paginate($param['page_size'] ?? 10)->toArray();

    return success($data);
}



    public function menu(Request $request): Response
    {
            // 检查用户数据是否存在
            if (!isset($request->userData) || !is_array($request->userData)) {
                return error('用户未登录或登录信息无效', 401);
            }
            
            $userId  = $request->userData['admin_id'] ?? null;
            $groupId = $request->userData['user_group_id'] ?? null;
            
            if (!$userId || !$groupId) {
                return error('用户信息不完整', 401);
            }

            // 直接从数据库获取菜单数据，不使用缓存
            $baseQuery = AdminRule::where([
                'is_menu' => 1,
                'status'  => 1
            ])->select(['id', 'title', 'icon', 'path', 'parent_id']);
            
            if ($userId != Common::ADMIN_USER_ID) {
                // 获取分组权限id
                $ruleIds = PermissionGroup::where('permission_group_id', $groupId)
                    ->pluck('permission_id')->toArray();

                // 获取这些权限的所有父级（包括多级）
                $allRuleIds = $this->getRuleWithParents($ruleIds);

                $baseQuery->whereIn('id', $allRuleIds);
            }

            $menus = $baseQuery->orderBy('weight', 'desc')->get()->toArray();
            $tree = $this->buildMenuTree($menus);

            return success($tree);
    }
private function getRuleWithParents(array $ruleIds): array
{
    $allIds = $ruleIds;
    $parentIds = AdminRule::whereIn('id', $ruleIds)
        ->pluck('parent_id')
        ->unique()
        ->filter(fn($id) => $id != 0)
        ->values()
        ->all();

    while (!empty($parentIds)) {
        $newParents = AdminRule::whereIn('id', $parentIds)
            ->pluck('parent_id')
            ->unique()
            ->filter(fn($id) => $id != 0 && !in_array($id, $allIds))
            ->values()
            ->all();

        $allIds = array_merge($allIds, $parentIds);
        $parentIds = $newParents;
    }

    return array_unique($allIds);
}

    private function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $children = $this->buildMenuTree($menus, $menu['id']);
                if (!empty($children)) {
                    $menu['children'] = $children;
                }else{
                    $menu['children'] = [];
                }
                $tree[] = $menu;
            }
        }
        return $tree;
    }
    public function store(Request $request): Response
    {
        try {
            $param = $request->post();

            $isEdit = !empty($param['id']);

            // 验证参数（控制器层只负责校验）
            $rules = [
                'username' => Validator::notEmpty()->setName('用户名'),
            ];
            if (!$isEdit) {
                $rules['password'] = Validator::notEmpty()->setName('密码');
            }

            Validator::input($param, $rules);

            // 调用服务处理创建/编辑
            $service = new AdminService();
            $service->save($param);

            return success([], $isEdit ? '编辑成功' : '创建成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessages());
        } catch (\Throwable $e) {
            throw new MyBusinessException('系统异常：' . $e->getMessage());
        }
    }
    public function detail(Request $request, int $id): Response
    {
        try {
            // 查找分组基础信息
            $admin = Admin::find($id);
            if (!$admin) {
                throw new MyBusinessException('分组不存在');
            }

            // 拼接返回数据
            $data = $admin->toArray();
            return success($data);
        } catch (\Throwable $e) {
            throw new MyBusinessException('系统异常：' . $e->getMessage());
        }
    }
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids');

        try {
            if (empty($ids) || !is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            // 查找所有匹配的管理员
            $admins = Admin::whereIn('id', $ids)->get();

            if ($admins->isEmpty()) {
                throw new MyBusinessException('未找到对应的管理员记录');
            }

            // 执行批量删除
            Admin::whereIn('id', $ids)->delete();
            // 删除该管理员关联的商户（如果有关联的商户）
            $merchantCount = \app\model\Merchant::whereIn('admin_id', $ids)->count();
            if ($merchantCount > 0) {
                \app\model\Merchant::whereIn('admin_id', $ids)->delete();
            }

            return success([], '删除成功');
        } catch (\Throwable $e) {
            throw new MyBusinessException('系统异常：' . $e->getMessage());
        }
    }
    public function switch(Request $request): Response
    {
        $id = $request->post('id');

        if (!$id) {
            throw new MyBusinessException('参数错误');
        }

        $admin = Admin::find($id);
        if (!$admin) {
            throw new MyBusinessException('管理员不存在');
        }

        // 切换状态
        $admin->status = $admin->status == 1 ? 0 : 1;
        $admin->save();
        // 更新该管理员关联的商户状态（如果有关联的商户）
        $merchantCount = \app\model\Merchant::where('admin_id', $id)->count();
        if ($merchantCount > 0) {
            \app\model\Merchant::where('admin_id', $id)->update(['status' => $admin->status]);
        }
        return success([],'切换成功');
    }

    public function info(Request $request)
    {
        $userData = $request->userData;
        
        // 确保is_merchant_admin字段存在，如果不存在则查询并添加
        if (!isset($userData['is_merchant_admin'])) {
            $adminId = $userData['admin_id'] ?? null;
            $isMerchantAdmin = false;
            
            if ($adminId) {
                $isMerchantAdmin = \app\model\Merchant::where('admin_id', $adminId)->exists();
            }
            
            $userData['is_merchant_admin'] = $isMerchantAdmin;
        }
        
            return success($userData);
    }


}

