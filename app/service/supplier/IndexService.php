<?php

namespace app\service\supplier;

use app\model\Supplier;
use support\Db;

class IndexService
{
    /**
     * 获取供应商列表
     * @param array $params
     * @return array
     */
    public function getSupplierList(array $params): array
    {
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 15;

        $query = Supplier::query();

        // 搜索条件
        if (!empty($params['search'])) {
            $search = json_decode($params['search'], true);
            if (is_array($search)) {
                // 处理嵌套的search对象
                if (isset($search['search']) && is_array($search['search'])) {
                    $search = $search['search'];
                }
                
                if (!empty($search['supplier_name'])) {
                    $query->where('supplier_name', 'like', '%' . trim($search['supplier_name']) . '%');
                }
                if (!empty($search['interface_code'])) {
                    $query->where('interface_code', 'like', '%' . trim($search['interface_code']) . '%');
                }
                if (isset($search['status']) && $search['status'] !== '') {
                    $query->where('status', $search['status']);
                }
            }
        }

        // 兼容旧的直接参数传递方式
        $supplierName = $params['supplier_name'] ?? '';
        $status = $params['status'] ?? '';

        // 供应商名称筛选
        if (!empty($supplierName)) {
            $query->where('supplier_name', 'like', '%' . $supplierName . '%');
        }

        // 状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        // 排序
        $query->orderBy('created_at', 'desc');

        $result = $query->paginate($pageSize, ['*'], 'page', $page)->toArray();

        return $result;
    }
}






