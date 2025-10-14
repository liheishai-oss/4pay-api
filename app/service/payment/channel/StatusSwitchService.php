<?php

namespace app\service\payment\channel;

use app\exception\MyBusinessException;
use app\model\PaymentChannel;
use app\common\helpers\ChannelCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class StatusSwitchService
{
    /**
     * 切换支付通道状态
     * @param int $id
     * @return PaymentChannel
     * @throws MyBusinessException
     */
    public function toggleStatus(int $id): PaymentChannel
    {
        Db::beginTransaction();
        try {
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                throw new MyBusinessException('支付通道不存在');
            }

            $channel->status = $channel->status === PaymentChannel::STATUS_ENABLED ? PaymentChannel::STATUS_DISABLED : PaymentChannel::STATUS_ENABLED;
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
            
            // 清理产品通道列表缓存（通道状态变化影响产品可用通道）
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
            throw new MyBusinessException('切换支付通道状态失败：' . $e->getMessage());
        }
    }
}





