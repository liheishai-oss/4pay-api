<?php

namespace app\service\product;

use app\model\Product;
use app\model\ProductChannel;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexService
{
    /**
     * 获取产品列表
     * @param array $params
     * @return LengthAwarePaginator
     */
    public function getProductList(array $params): LengthAwarePaginator
    {
        $query = Product::query();

        // 搜索条件
        if (!empty($params['search'])) {
            $search = json_decode($params['search'], true);
            if (is_array($search)) {
                // 处理嵌套的search对象
                if (isset($search['search']) && is_array($search['search'])) {
                    $search = $search['search'];
                }
                
                if (!empty($search['product_name'])) {
                    $query->where('product_name', 'like', '%' . trim($search['product_name']) . '%');
                }
                if (!empty($search['external_code'])) {
                    $query->where('external_code', 'like', '%' . trim($search['external_code']) . '%');
                }
                if (isset($search['status']) && $search['status'] !== '') {
                    $query->where('status', $search['status']);
                }
            }
        }

        // 排序
        $query->orderBy('sort', 'desc')->orderBy('id', 'desc');

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 10;

        $result = $query->paginate($pageSize, ['*'], 'page', $page);
        
        // 为每个产品添加轮询池信息
        $result->getCollection()->transform(function ($product) {
            $poolInfo = $this->getProductPoolInfo($product->id);
            $product->pool_info = $poolInfo;
            return $product;
        });

        return $result;
    }

    /**
     * 获取产品轮询池信息
     * @param int $productId
     * @return array
     */
    private function getProductPoolInfo(int $productId): array
    {
        // 获取已分配的通道数量
        $assignedCount = ProductChannel::where('product_id', $productId)
            ->where('status', ProductChannel::STATUS_ENABLED)
            ->count();

        // 获取轮询池中的通道详情（用于显示）
        $assignedChannels = ProductChannel::with('channel.supplier')
            ->where('product_id', $productId)
            ->where('status', ProductChannel::STATUS_ENABLED)
            ->get()
            ->map(function ($productChannel) {
                $channel = $productChannel->channel;
                if (!$channel) {
                    return null;
                }
                
                $supplierName = $channel->supplier->supplier_name ?? '未知供应商';
                return [
                    'id' => $channel->id,
                    'name' => $channel->channel_name,
                    'supplier' => $supplierName,
                    'display_name' => $supplierName . '-' . $channel->channel_name,
                    'cost_rate' => $channel->cost_rate,
                    'weight' => $channel->weight
                ];
            })
            ->filter() // 过滤掉null值
            ->values()
            ->toArray();

        return [
            'total_count' => $assignedCount,
            'channels' => $assignedChannels,
            'summary' => $assignedCount > 0 
                ? "已配置 {$assignedCount} 个通道" 
                : "暂未配置通道"
        ];
    }
}

