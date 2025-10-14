<?php

namespace app\service\payment\channel;

use app\exception\MyBusinessException;
use app\model\PaymentChannel;
use app\common\helpers\ChannelCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class DestroyService
{
    /**
     * 删除支付通道
     * @param int $id
     * @return bool
     * @throws MyBusinessException
     */
    public function destroyChannel(int $id): bool
    {
        Db::beginTransaction();
        try {
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                throw new MyBusinessException('支付通道不存在');
            }

            // 清除通道相关缓存
            ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);

            // 清理订单处理专用缓存（通道信息 - 通道状态敏感）
            $channelCacheKeys = [
                CacheKeys::getChannelInfo($channel->id),
            ];
            MultiLevelCacheHelper::clearOrderChannelBatch($channelCacheKeys);
            
            // 清理所有可用通道缓存（通道信息 - 通道状态敏感）
            MultiLevelCacheHelper::clearOrderChannelByPattern('*available_channels:*');
            
            // 清理产品通道列表缓存（通道删除影响产品可用通道）
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
            
            // 清理ProductChannel相关的缓存（通道删除可能影响产品通道关联）
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

            $channel->delete();

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('删除支付通道失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除支付通道
     * @param array $ids
     * @return int
     * @throws MyBusinessException
     */
    public function batchDestroyChannels(array $ids): int
    {
        if (empty($ids)) {
            throw new MyBusinessException('未指定要删除的支付通道ID');
        }

        Db::beginTransaction();
        try {
            // 获取要删除的通道信息，用于清理缓存
            $channels = PaymentChannel::whereIn('id', $ids)->get();
            
            // 清除所有相关缓存
            $channelCacheKeys = [];
            $productCodes = [];
            foreach ($channels as $channel) {
                ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
                $channelCacheKeys[] = CacheKeys::getChannelInfo($channel->id);
                
                // 收集产品代码，用于清理相关缓存
                if ($channel->product_code && !in_array($channel->product_code, $productCodes)) {
                    $productCodes[] = $channel->product_code;
                }
            }
            
            // 批量清理订单处理专用缓存（通道信息 - 通道状态敏感）
            if (!empty($channelCacheKeys)) {
                MultiLevelCacheHelper::clearOrderChannelBatch($channelCacheKeys);
            }
            
            // 清理所有可用通道缓存（通道信息 - 通道状态敏感）
            MultiLevelCacheHelper::clearOrderChannelByPattern('*available_channels:*');
            
            // 清理产品通道列表缓存和通道列表查询缓存
            foreach ($productCodes as $productCode) {
                // 清理产品通道列表缓存 - 通过product_code获取product_id
                try {
                    $product = \app\model\Product::where('external_code', $productCode)->first();
                    if ($product) {
                        $productChannelsCacheKey = CacheKeys::getProductChannels($product->id);
                        MultiLevelCacheHelper::clearAllLevels($productChannelsCacheKey);
                    }
                } catch (\Exception $e) {
                    \support\Log::warning('批量清理产品通道列表缓存失败', [
                        'product_code' => $productCode,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // 清理通道列表查询缓存
                $channelListCacheKey = CacheKeys::getChannelListQuery($productCode);
                MultiLevelCacheHelper::clearAllLevels($channelListCacheKey);
                
                // 清理产品代码查询缓存
                $productCodeCacheKey = CacheKeys::getProductCodeQuery($productCode);
                MultiLevelCacheHelper::clearAllLevels($productCodeCacheKey);
            }
            
            $count = PaymentChannel::destroy($ids);
            Db::commit();
            return $count;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('批量删除支付通道失败：' . $e->getMessage());
        }
    }
}





