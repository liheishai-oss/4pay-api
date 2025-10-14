<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;
use support\Redis;
use support\Log;

/**
 * 通道缓存管理辅助类
 * 专门用于清理通道相关的Redis缓存
 */
class ChannelCacheHelper
{
    /**
     * 清除指定通道的缓存
     * @param int $channelId 通道ID
     */
    public static function clearChannelCache(int $channelId): void
    {
        try {
            $cacheKey = SystemConstants::CACHE_PREFIX . 'channel:info:' . $channelId;
            
            $redis = Redis::connection();
            $redis->del($cacheKey);

            Log::info('通道缓存已清除', [
                'channel_id' => $channelId,
                'cache_key' => $cacheKey
            ]);

        } catch (\Exception $e) {
            Log::error('清除通道缓存失败', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除指定产品的可用通道列表缓存
     * @param int $productId 产品ID
     */
    public static function clearAvailableChannelsCache(int $productId): void
    {
        try {
            $redis = Redis::connection();
            
            // 清除该产品下所有金额的可用通道缓存
            $pattern = SystemConstants::CACHE_PREFIX . 'available_channels:' . $productId . ':*';
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
                Log::info('可用通道列表缓存已清除', [
                    'product_id' => $productId,
                    'pattern' => $pattern,
                    'cleared_keys' => count($keys)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('清除可用通道列表缓存失败', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除指定产品代码的通道列表查询缓存
     * @param string $productCode 产品代码
     */
    public static function clearChannelListCache(string $productCode): void
    {
        try {
            $cacheKey = SystemConstants::CACHE_PREFIX . 'merchant:channel_list:' . $productCode;
            
            $redis = Redis::connection();
            $redis->del($cacheKey);

            Log::info('通道列表查询缓存已清除', [
                'product_code' => $productCode,
                'cache_key' => $cacheKey
            ]);

        } catch (\Exception $e) {
            Log::error('清除通道列表查询缓存失败', [
                'product_code' => $productCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除通道相关的所有缓存
     * @param int $channelId 通道ID
     * @param int|null $productId 产品ID（可选）
     * @param string|null $productCode 产品代码（可选）
     */
    public static function clearChannelAllCache(int $channelId, ?int $productId = null, ?string $productCode = null): void
    {
        // 清除通道信息缓存
        self::clearChannelCache($channelId);
        
        // 如果有产品ID，清除指定产品的可用通道列表缓存
        if ($productId) {
            self::clearAvailableChannelsCache($productId);
        } else {
            // 如果没有产品ID，清除所有产品的可用通道列表缓存
            // 因为通道状态变化可能影响所有使用该通道的产品
            try {
                $redis = Redis::connection();
                $pattern = SystemConstants::CACHE_PREFIX . 'available_channels:*';
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $redis->del($keys);
                    Log::info('所有可用通道列表缓存已清除', [
                        'channel_id' => $channelId,
                        'cleared_keys_count' => count($keys)
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('清除所有可用通道列表缓存失败', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // 如果有产品代码，清除通道列表查询缓存
        if ($productCode) {
            self::clearChannelListCache($productCode);
        }
    }

    /**
     * 清除所有通道缓存
     */
    public static function clearAllChannelCache(): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            // 清除通道信息缓存
            $channelPattern = SystemConstants::CACHE_PREFIX . 'channel:info:*';
            $channelKeys = $redis->keys($channelPattern);
            if (!empty($channelKeys)) {
                $redis->del($channelKeys);
                $clearedKeys = array_merge($clearedKeys, $channelKeys);
            }
            
            // 清除可用通道列表缓存
            $availablePattern = SystemConstants::CACHE_PREFIX . 'available_channels:*';
            $availableKeys = $redis->keys($availablePattern);
            if (!empty($availableKeys)) {
                $redis->del($availableKeys);
                $clearedKeys = array_merge($clearedKeys, $availableKeys);
            }

            // 清除通道列表查询缓存
            $listPattern = SystemConstants::CACHE_PREFIX . 'merchant:channel_list:*';
            $listKeys = $redis->keys($listPattern);
            if (!empty($listKeys)) {
                $redis->del($listKeys);
                $clearedKeys = array_merge($clearedKeys, $listKeys);
            }


            Log::info('所有通道缓存已清除', [
                'cleared_keys_count' => count($clearedKeys),
                'channel_keys_count' => count($channelKeys),
                'available_keys_count' => count($availableKeys),
                'list_keys_count' => count($listKeys)
            ]);

        } catch (\Exception $e) {
            Log::error('清除所有通道缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
