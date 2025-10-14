<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;
use support\Redis;
use support\Log;

/**
 * 关系查询缓存管理辅助类
 * 专门用于清理商户产品关系相关的Redis缓存
 */
class RelationCacheHelper
{

    /**
     * 清除指定商户费率缓存
     * @param int $merchantId 商户ID
     * @param int $productId 产品ID
     */
    public static function clearMerchantRateCache(int $merchantId, int $productId): void
    {
        try {
            $cacheKey = SystemConstants::CACHE_PREFIX . 'merchant:merchant_rate:' . $merchantId . ':' . $productId;
            
            $redis = Redis::connection();
            $redis->del($cacheKey);

            Log::info('商户费率缓存已清除', [
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'cache_key' => $cacheKey
            ]);

        } catch (\Exception $e) {
            Log::error('清除商户费率缓存失败', [
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除商户产品关系相关的所有缓存
     * @param int $merchantId 商户ID
     * @param int $productId 产品ID
     */

    /**
     * 清除指定商户的所有产品关系缓存
     * @param int $merchantId 商户ID
     */
    public static function clearMerchantAllProductCache(int $merchantId): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            
            // 清除该商户的所有费率缓存
            $ratePattern = SystemConstants::CACHE_PREFIX . 'merchant:merchant_rate:' . $merchantId . ':*';
            $rateKeys = $redis->keys($ratePattern);
            if (!empty($rateKeys)) {
                $redis->del($rateKeys);
                $clearedKeys = array_merge($clearedKeys, $rateKeys);
            }

            Log::info('商户所有产品关系缓存已清除', [
                'merchant_id' => $merchantId,
                'cleared_keys_count' => count($clearedKeys),
                'merchant_keys_count' => count($merchantKeys),
                'rate_keys_count' => count($rateKeys)
            ]);

        } catch (\Exception $e) {
            Log::error('清除商户所有产品关系缓存失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除指定产品的所有商户关系缓存
     * @param int $productId 产品ID
     */
    public static function clearProductAllMerchantCache(int $productId): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            
            // 清除该产品的所有费率缓存
            $productRatePattern = SystemConstants::CACHE_PREFIX . 'merchant:merchant_rate:*:' . $productId;
            $productRateKeys = $redis->keys($productRatePattern);
            if (!empty($productRateKeys)) {
                $redis->del($productRateKeys);
                $clearedKeys = array_merge($clearedKeys, $productRateKeys);
            }

            Log::info('产品所有商户关系缓存已清除', [
                'product_id' => $productId,
                'cleared_keys_count' => count($clearedKeys),
                'product_rate_keys_count' => count($productRateKeys)
            ]);

        } catch (\Exception $e) {
            Log::error('清除产品所有商户关系缓存失败', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除所有关系查询缓存
     */
    public static function clearAllRelationCache(): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            
            // 清除所有商户费率缓存
            $ratePattern = SystemConstants::CACHE_PREFIX . 'merchant:merchant_rate:*';
            $rateKeys = $redis->keys($ratePattern);
            if (!empty($rateKeys)) {
                $redis->del($rateKeys);
                $clearedKeys = array_merge($clearedKeys, $rateKeys);
            }

            Log::info('所有关系查询缓存已清除', [
                'cleared_keys_count' => count($clearedKeys),
                'rate_keys_count' => count($rateKeys)
            ]);

        } catch (\Exception $e) {
            Log::error('清除所有关系查询缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
