<?php

namespace app\api\service\v1\order;

use app\model\Product;
use app\model\ProductChannel;
use app\model\PaymentChannel;
use app\model\Supplier;
use app\exception\MyBusinessException;

class PollingPoolSelector
{
    /**
     * 根据产品ID获取轮询池中的通道列表
     * @param int $productId
     * @return array
     * @throws MyBusinessException
     */
    public function getChannelsForProduct(int $productId): array
    {
        // 1. 验证产品是否存在
        $product = Product::find($productId);
        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        // 2. 获取产品的轮询池配置
        $productChannels = ProductChannel::where('product_id', $productId)
            ->where('status', ProductChannel::STATUS_ENABLED)
            ->with(['channel.supplier'])
            ->orderBy('weight', 'desc')
            ->orderBy('channel_id', 'asc')
            ->get();

        if ($productChannels->isEmpty()) {
            throw new MyBusinessException('产品轮询池为空或所有通道都被禁用');
        }

        // 3. 过滤可用的通道
        $availableChannels = [];
        foreach ($productChannels as $productChannel) {
            $paymentChannel = $productChannel->channel;
            if (!$paymentChannel || $paymentChannel->status !== PaymentChannel::STATUS_ENABLED) {
                continue;
            }

            $availableChannels[] = [
                'id' => $paymentChannel->id,
                'name' => $paymentChannel->channel_name,
                'interface_code' => $paymentChannel->interface_code, // 直接从通道表获取接口代码
                'product_code' => $paymentChannel->product_code, // 添加产品编码字段
                'weight' => $productChannel->weight,
                'cost_rate' => $paymentChannel->cost_rate,
                'min_amount' => $productChannel->min_amount ?? 0,
                'max_amount' => $productChannel->max_amount ?? 999999,
                'supplier_name' => $paymentChannel->supplier ? $paymentChannel->supplier->supplier_name : '未知供应商',
                'product_channel_id' => $productChannel->id
            ];
        }

        if (empty($availableChannels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }

        return $availableChannels;
    }

    /**
     * 根据产品ID和通道类型获取通道列表
     * @param int $productId
     * @param string $channelType
     * @return array
     * @throws MyBusinessException
     */
    public function getChannelsForProductByType(int $productId, string $channelType): array
    {
        $allChannels = $this->getChannelsForProduct($productId);
        
        // 根据通道类型过滤
        $filteredChannels = array_filter($allChannels, function($channel) use ($channelType) {
            return $channel['interface_code'] === $channelType;
        });

        if (empty($filteredChannels)) {
            throw new MyBusinessException("产品中没有可用的{$channelType}类型通道");
        }

        return array_values($filteredChannels);
    }

    /**
     * 获取产品信息
     * @param int $productId
     * @return array
     * @throws MyBusinessException
     */
    public function getProductInfo(int $productId): array
    {
        $product = Product::find($productId);
        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        return [
            'id' => $product->id,
            'product_name' => $product->product_name,
            'external_code' => $product->external_code,
            'status' => $product->status
        ];
    }
}
