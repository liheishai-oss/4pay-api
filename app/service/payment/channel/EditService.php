<?php

namespace app\service\payment\channel;

use app\exception\MyBusinessException;
use app\model\PaymentChannel;
use app\model\Supplier;
use app\common\helpers\ChannelCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class EditService
{
    /**
     * 更新支付通道
     * @param int $id
     * @param array $data
     * @return PaymentChannel
     * @throws MyBusinessException
     */
    public function updateChannel(int $id, array $data): PaymentChannel
    {
        Db::beginTransaction();
        try {
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                throw new MyBusinessException('支付通道不存在');
            }

            // 如果更新供应商ID，验证供应商是否存在
            if (isset($data['supplier_id'])) {
                $supplier = Supplier::find($data['supplier_id']);
                if (!$supplier) {
                    throw new MyBusinessException('供应商不存在');
                }
                $channel->supplier_id = $data['supplier_id'];
            }

            // 如果更新供应商ID，需要从新供应商获取接口代码并同步到通道表
            if (isset($data['supplier_id']) && $data['supplier_id'] !== $channel->supplier_id) {
                $newSupplier = Supplier::find($data['supplier_id']);
                if (!$newSupplier) {
                    throw new MyBusinessException('供应商不存在');
                }
                if (empty($newSupplier->interface_code)) {
                    throw new MyBusinessException('供应商未设置接口代码');
                }
                $channel->supplier_id = $data['supplier_id'];
                // 同步供应商的 interface_code 到通道表，避免每次关联查询
                $channel->interface_code = $newSupplier->interface_code;
            }

            if (isset($data['channel_name'])) {
                $channel->channel_name = $data['channel_name'];
            }
            if (isset($data['status'])) {
                $channel->status = $data['status'];
            }
            if (isset($data['weight'])) {
                $channel->weight = $data['weight'];
            }
            if (isset($data['min_amount'])) {
                $channel->min_amount = $data['min_amount'];
            }
            if (isset($data['max_amount'])) {
                $channel->max_amount = $data['max_amount'];
            }
            if (isset($data['cost_rate'])) {
                $channel->cost_rate = $data['cost_rate'];
            }
            if (isset($data['remark'])) {
                $channel->remark = $data['remark'];
            }
            if (isset($data['product_code'])) {
                $channel->product_code = $data['product_code'];
            }
            if (isset($data['basic_params'])) {
                if (empty($data['basic_params'])) {
                    $channel->basic_params = null;
                } else {
                    $channel->basic_params = $data['basic_params'];
                }
            }

            $channel->save();

            // 清除通道相关缓存
            ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);

            // 清理订单处理专用缓存（通道信息 - 通道状态敏感）
            $channelCacheKeys = [
                CacheKeys::getChannelInfo($channel->id),
            ];
            MultiLevelCacheHelper::clearOrderChannelBatch($channelCacheKeys);
            
            // 清理所有可用通道缓存（通道信息 - 通道状态敏感）
            MultiLevelCacheHelper::clearOrderChannelByPattern('*available_channels:*');
            
            // 清理产品通道列表缓存（通道信息变化影响产品可用通道）
            if ($channel->product_code) {
                // 通过product_code获取product_id来清理产品通道列表缓存
                try {
                    $product = \app\model\Product::where('external_code', $channel->product_code)->first();
                    if ($product) {
                        $productChannelsCacheKey = CacheKeys::getProductChannels($product->id);
                        MultiLevelCacheHelper::clearAllLevels($productChannelsCacheKey);
                    }
                } catch (\Exception $e) {
                    // 如果获取产品失败，记录日志但不影响主流程
                    \support\Log::warning('清理产品通道列表缓存失败', [
                        'channel_id' => $channel->id,
                        'product_code' => $channel->product_code,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // 清理通道列表查询缓存
            if ($channel->product_code) {
                $channelListCacheKey = CacheKeys::getChannelListQuery($channel->product_code);
                MultiLevelCacheHelper::clearAllLevels($channelListCacheKey);
            }
            
            // 清理产品代码查询缓存（通道product_code变化可能影响产品查询）
            if ($channel->product_code) {
                $productCodeCacheKey = CacheKeys::getProductCodeQuery($channel->product_code);
                MultiLevelCacheHelper::clearAllLevels($productCodeCacheKey);
            }
            
            // 清理ProductChannel相关的缓存（通道状态变化可能影响产品通道关联）
            try {
                $productChannels = \app\model\ProductChannel::where('channel_id', $channel->id)->get();
                foreach ($productChannels as $productChannel) {
                    $productChannelsCacheKey = CacheKeys::getProductChannels($productChannel->product_id);
                    MultiLevelCacheHelper::clearAllLevels($productChannelsCacheKey);
                }
                \support\Log::info('ProductChannel相关缓存已清理', [
                    'channel_id' => $channel->id,
                    'affected_products_count' => $productChannels->count()
                ]);
            } catch (\Exception $e) {
                \support\Log::warning('清理ProductChannel相关缓存失败', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage()
                ]);
            }

            Db::commit();

            return $channel;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('更新支付通道失败：' . $e->getMessage());
        }
    }
}





