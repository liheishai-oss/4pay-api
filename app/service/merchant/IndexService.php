<?php

namespace app\service\merchant;

use app\model\Merchant;
use support\Db;

class IndexService
{
    /**
     * 获取商户列表
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getMerchantList(array $params = [])
    {
        $query = Merchant::with('admin');

        // 搜索条件
        if (!empty($params['search'])) {
            $search = json_decode($params['search'], true);
            if (is_array($search)) {
                // 处理嵌套的search对象
                if (isset($search['search']) && is_array($search['search'])) {
                    $search = $search['search'];
                }
                
                if (!empty($search['login_account'])) {
                    $query->where('login_account', 'like', '%' . trim($search['login_account']) . '%');
                }
                if (isset($search['status']) && $search['status'] !== '') {
                    $query->where('status', $search['status']);
                }
            }
        }

        // 兼容旧的直接参数传递方式
        if (!empty($params['login_account'])) {
            $query->where('login_account', 'like', '%' . $params['login_account'] . '%');
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['admin_id'])) {
            $query->where('admin_id', $params['admin_id']);
        }

        // 排序
        $sortField = $params['sort_field'] ?? 'id';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $perPage = $params['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}




