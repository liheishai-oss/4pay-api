<?php

namespace app\admin\controller\v1;

use app\common;
use app\exception\MyBusinessException;
use app\model\AdminRule;
use app\model\PermissionGroup;
use app\model\RoleGroup;
use app\service\Rule;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class MenuRuleController
{
    public function index(Request $request): Response
    {
        $param = $request->all();

        $list = AdminRule::where('parent_id', 0)
            ->orderBy('weight', 'desc')
            ->paginate($param['page_size'])
            ->toArray();

        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                if ($item['has_children']) {
                    $item['children'] = $this->getChildren($item['id']);
                    $item['has_children'] = count($item['children']) > 0;
                }
            }
            unset($item);
        }

        return success($list);
    }
    /**
     * 递归获取子节点
     */
    protected function getChildren($parentId): array
    {
        $children = AdminRule::where('parent_id', $parentId)
            ->orderBy('weight', 'desc')
            ->get()
            ->map(function ($child) {
                $child->children = $this->getChildren($child->id);
                $child->has_children = count($child->children) > 0;
                return $child;
            })
            ->toArray();

        return $children;
    }
    public function children(Request $request, int $rule_id): Response
    {

        $data = AdminRule::where('parent_id',$rule_id)->get()->toArray();
        if(!empty($data['data'])) {
            foreach($data['data'] as $key => &$value) {
                if($value['has_children'] == 1) {
                    $value['has_children'] = true;
                    $value['children'] = [];

                }
            }
            unset($value);
        }
        return success($data);
    }

    public function dropdown(Request $request,int $group_id=0): Response
    {
        $param = $request->all();

        // 获取当前分组id
        if($group_id ==0){
            $group_id = $request->userData['user_group_id'];
        }
//


        // 非超管时获取该分组权限 ID
        $rules = [];
        if ($group_id != 1) {
            $group = RoleGroup::find($group_id);
            $rules = PermissionGroup::where('permission_group_id', $group->parent_id)
                ->pluck('permission_id')
                ->toArray();

        }

        // 查询顶级菜单
        $query = AdminRule::where('parent_id', 0)->select(['id', 'title','parent_id']);
        if ($group_id != 1) {
            $query->whereIn('id', $rules);
        }

        $data = $query->get()->map(function($item) {
            $item->children =  AdminRule::where('parent_id',$item->id)->select(['id','title','parent_id'])->get()->map(function($items)  {
                $items->children =  AdminRule::where('parent_id',$items->id)->select(['id','title','parent_id'])->get();
                $items->isPenultimate = true;
                return $items;
            });
            return $item;
        })->toArray();
        return success($data);
    }

    public function store(Request $request): Response
    {
        try {
            $param = $request->post(); // 获取提交参数

            // 校验参数
            $data = Validator::input($param, [
                'title'  => Validator::notEmpty()->setName('权限名称'),
                'rule'  => Validator::notEmpty()->setName('权限标识'),
            ]);

            // 默认值处理（可在 service 中也处理）
            $param['parent_id'] = $param['parent_id'] ?? 0;
            $param['is_menu'] = $param['is_menu'] ?? 0;
            $param['status'] = $param['status'] ?? 1;
            $param['weight'] = $param['weight'] ?? 0;
            $param['remark'] = $param['remark'] ?? '';
            $param['path'] = $param['path'] ?? '';
            $param['icon'] = $param['icon'] ?? '';

            // 调用业务服务添加规则
            $service = new Rule();
            $result = $service->addRule($param);

            return success([],'添加成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (\Throwable $e) {
            throw new MyBusinessException('系统异常：' . $e->getMessage());
        }
    }

    public function destroy(Request $request): Response
    {
        $param = $request->all();
        $idsString = $param['ids'] ?? '';

        if (empty($idsString)) {
            throw new MyBusinessException('未指定要删除的投诉ID');
        }


        try {

            AdminRule::whereIn('id', $idsString)->delete();

            return success([], '删除成功');
        } catch (Throwable $e) {

            throw new MyBusinessException('删除失败：' . $e->getMessage());
        }
    }
    public function edit(Request $request): Response
    {
        try {
            $param = $request->post(); // 获取提交参数

            // 校验参数
            $data = Validator::input($param, [
                'title'  => Validator::notEmpty()->setName('权限名称'),
                'rule'  => Validator::notEmpty()->setName('权限标识'),
            ]);

            // 默认值处理（可在 service 中也处理）
            $param['parent_id'] = $param['parent_id'] ?? 0;
            $param['is_menu'] = $param['is_menu'] ?? 0;
            $param['status'] = $param['status'] ?? 1;
            $param['weight'] = $param['weight'] ?? 0;
            $param['remark'] = $param['remark'] ?? '';
            $param['path'] = $param['path'] ?? '';
            $param['icon'] = $param['icon'] ?? '';

            // 调用业务服务添加规则
            $service = new Rule();
            $result = $service->edit($param);

            return success([],'更新成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (\Throwable $e) {
            throw new MyBusinessException('系统异常：' . $e->getMessage());
        }
    }

    public function rule(Request $request)
    {
 try {
        $adminId = $request->userData['admin_id'];
        $groupId = $request->userData['user_group_id'];

        // 超级管理员返回所有权限
        if ($adminId == common::ADMIN_USER_ID) {
            // 超级管理员：获取所有权限标识
            $rules = AdminRule::where('status', 1)
                ->pluck('rule')
                ->toArray();
        } else {
             // 普通用户：获取分组绑定的权限 ID
            $ruleIds = PermissionGroup::where('permission_group_id', $groupId)
                ->pluck('permission_id')
                ->toArray();

            if (empty($ruleIds)) {
                return success([]); // 无权限
            }

            $rules = AdminRule::whereIn('id', $ruleIds)
                ->where('status', 1)
                ->pluck('rule')
                ->toArray();
        }


        // 去除空值、重复项
        $rules = array_filter(array_unique($rules));

        return success($rules);
    } catch (\Throwable $e) {
        throw new MyBusinessException('获取权限失败：' . $e->getMessage());
    }
    }


}