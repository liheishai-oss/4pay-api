<?php

namespace app\service\payment\channel;

use app\model\PaymentChannel;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexService
{
    /**
     * 获取支付通道列表
     * @param array $params
     * @return LengthAwarePaginator
     */
    public function getList(array $params): LengthAwarePaginator
    {
        $pageSize = $params['page_size'] ?? 10;
        $page = $params['page'] ?? 1;

        $query = PaymentChannel::with('supplier');

        // 搜索条件
        if (!empty($params['search'])) {
            $search = json_decode($params['search'], true);
            if (is_array($search)) {
                // 处理嵌套的search对象
                if (isset($search['search']) && is_array($search['search'])) {
                    $search = $search['search'];
                }
                
                if (!empty($search['channel_name'])) {
                    $query->where('channel_name', 'like', '%' . trim($search['channel_name']) . '%');
                }
                if (!empty($search['product_code'])) {
                    $query->where('product_code', 'like', '%' . trim($search['product_code']) . '%');
                }
            }
        }

        // 兼容旧的直接参数传递方式
        if (!empty($params['channel_name'])) {
            $query->where('channel_name', 'like', '%' . $params['channel_name'] . '%');
        }
        if (isset($params['supplier_id']) && $params['supplier_id'] !== '') {
            $query->where('supplier_id', $params['supplier_id']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }
        // 接口代码现在在通道表中，直接查询
        if (!empty($params['interface_code'])) {
            $query->where('interface_code', 'like', '%' . $params['interface_code'] . '%');
        }
        
        // 添加产品编码筛选
        if (!empty($params['product_code'])) {
            $query->where('product_code', 'like', '%' . $params['product_code'] . '%');
        }

        // 排序
        $query->orderBy('weight', 'desc')->orderBy('id', 'desc');

        $result = $query->paginate($pageSize, ['*'], 'page', $page);

        return $result;
    }
}





