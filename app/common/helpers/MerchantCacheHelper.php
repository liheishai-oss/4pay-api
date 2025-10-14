<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;
use support\Redis;

/**
 * 商户缓存管理辅助类
 * 专门用于清理商户相关的Redis缓存
 */
class MerchantCacheHelper
{
    /**
     * 清除指定商户的缓存
     * @param string $merchantKey 商户key
     */
    public static function clearMerchantCache(string $merchantKey): void
    {
        try {
            $cacheKey = CacheKeys::getMerchantInfo($merchantKey);
            
            $redis = Redis::connection();
            
            // 检查缓存是否存在
            $exists = $redis->exists($cacheKey);
            
            if ($exists) {
                $result = $redis->del($cacheKey);
                
                // 记录缓存清除日志
                \support\Log::info('商户缓存已清除', [
                    'merchant_key' => $merchantKey,
                    'cache_key' => $cacheKey,
                    'deleted_count' => $result
                ]);
            } else {
                // 记录缓存不存在的日志
                \support\Log::info('商户缓存不存在，无需清除', [
                    'merchant_key' => $merchantKey,
                    'cache_key' => $cacheKey
                ]);
            }

        } catch (\Exception $e) {
            \support\Log::error('清除商户缓存失败', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 逐个清除指定商户列表的缓存
     * @param array $merchantKeys 商户key数组
     */
    public static function clearMerchantsCacheIndividually(array $merchantKeys): void
    {
        try {
            $redis = Redis::connection();
            $clearedCount = 0;
            $notFoundCount = 0;
            
            foreach ($merchantKeys as $merchantKey) {
                if (!empty($merchantKey)) {
                    $cacheKey = CacheKeys::getMerchantInfo($merchantKey);
                    
                    // 检查缓存是否存在
                    $exists = $redis->exists($cacheKey);
                    
                    if ($exists) {
                        $result = $redis->del($cacheKey);
                        $clearedCount += $result;
                        
                        // 记录每个商户缓存清除日志
                        \support\Log::info('商户缓存已清除', [
                            'merchant_key' => $merchantKey,
                            'cache_key' => $cacheKey,
                            'deleted_count' => $result
                        ]);
                    } else {
                        $notFoundCount++;
                        
                        // 记录缓存不存在的日志
                        \support\Log::info('商户缓存不存在，无需清除', [
                            'merchant_key' => $merchantKey,
                            'cache_key' => $cacheKey
                        ]);
                    }
                }
            }

            // 清理商户费率缓存
            foreach ($merchantKeys as $merchantKey) {
                if (!empty($merchantKey)) {
                    // 获取商户ID（这里需要根据实际情况调整）
                    $merchant = \app\model\Merchant::where('merchant_key', $merchantKey)->first();
                    if ($merchant) {
                        // 清理该商户的所有费率缓存
                        $ratePattern = SystemConstants::CACHE_PREFIX . 'merchant:merchant_rate:' . $merchant->id . ':*';
                        $rateKeys = $redis->keys($ratePattern);
                        if (!empty($rateKeys)) {
                            $redis->del($rateKeys);
                        }
                    }
                }
            }

            // 记录总体缓存清除日志
            \support\Log::info('商户列表缓存已逐个清除', [
                'total_merchants' => count($merchantKeys),
                'cleared_count' => $clearedCount,
                'not_found_count' => $notFoundCount
            ]);

        } catch (\Exception $e) {
            \support\Log::error('逐个清除商户列表缓存失败', [
                'merchant_keys' => $merchantKeys,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 逐个清除所有商户缓存
     * 不使用批量删除，而是逐个清理每个商户的缓存
     */
    public static function clearAllMerchantCacheIndividually(): void
    {
        try {
            // 获取所有商户的merchant_key
            $merchants = \app\model\Merchant::select('merchant_key')
                ->where('merchant_key', '!=', '')
                ->get();
            
            $clearedCount = 0;
            $notFoundCount = 0;
            $redis = Redis::connection();
            
            foreach ($merchants as $merchant) {
                if (!empty($merchant->merchant_key)) {
                    $cacheKey = CacheKeys::getMerchantInfo($merchant->merchant_key);
                    
                    // 检查缓存是否存在
                    $exists = $redis->exists($cacheKey);
                    
                    if ($exists) {
                        $result = $redis->del($cacheKey);
                        $clearedCount += $result;
                        
                        // 记录每个商户缓存清除日志
                        \support\Log::info('商户缓存已清除', [
                            'merchant_key' => $merchant->merchant_key,
                            'cache_key' => $cacheKey,
                            'deleted_count' => $result
                        ]);
                    } else {
                        $notFoundCount++;
                        
                        // 记录缓存不存在的日志
                        \support\Log::info('商户缓存不存在，无需清除', [
                            'merchant_key' => $merchant->merchant_key,
                            'cache_key' => $cacheKey
                        ]);
                    }
                }
            }

            // 记录总体缓存清除日志
            \support\Log::info('所有商户缓存已逐个清除', [
                'total_merchants' => $merchants->count(),
                'cleared_count' => $clearedCount,
                'not_found_count' => $notFoundCount
            ]);

        } catch (\Exception $e) {
            \support\Log::error('逐个清除所有商户缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除所有商户缓存（保留原方法以兼容现有代码）
     * @deprecated 请使用 clearAllMerchantCacheIndividually() 方法
     */
    public static function clearAllMerchantCache(): void
    {
        // 调用新的逐个清理方法
        self::clearAllMerchantCacheIndividually();
    }

}
