<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;
use support\Redis;
use support\Log;

/**
 * 供应商缓存管理辅助类
 * 专门用于清理供应商相关的Redis缓存
 */
class SupplierCacheHelper
{
    /**
     * 清除指定供应商的缓存
     * @param int $supplierId 供应商ID
     */
    public static function clearSupplierCache(int $supplierId): void
    {
        try {
            $cacheKey = SystemConstants::CACHE_PREFIX . 'supplier:info:' . $supplierId;
            
            $redis = Redis::connection();
            $redis->del($cacheKey);

            Log::info('供应商缓存已清除', [
                'supplier_id' => $supplierId,
                'cache_key' => $cacheKey
            ]);

        } catch (\Exception $e) {
            Log::error('清除供应商缓存失败', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除指定供应商的查询缓存
     * @param int $supplierId 供应商ID
     */
    public static function clearSupplierQueryCache(int $supplierId): void
    {
        try {
            $cacheKey = SystemConstants::CACHE_PREFIX . 'merchant:supplier:' . $supplierId;
            
            $redis = Redis::connection();
            $redis->del($cacheKey);

            Log::info('供应商查询缓存已清除', [
                'supplier_id' => $supplierId,
                'cache_key' => $cacheKey
            ]);

        } catch (\Exception $e) {
            Log::error('清除供应商查询缓存失败', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除供应商相关的所有缓存
     * @param int $supplierId 供应商ID
     */
    public static function clearSupplierAllCache(int $supplierId): void
    {
        // 清除供应商信息缓存
        self::clearSupplierCache($supplierId);
        
        // 清除供应商查询缓存
        self::clearSupplierQueryCache($supplierId);
    }

    /**
     * 清除所有供应商缓存
     */
    public static function clearAllSupplierCache(): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            // 清除供应商信息缓存
            $supplierPattern = SystemConstants::CACHE_PREFIX . 'supplier:info:*';
            $supplierKeys = $redis->keys($supplierPattern);
            if (!empty($supplierKeys)) {
                $redis->del($supplierKeys);
                $clearedKeys = array_merge($clearedKeys, $supplierKeys);
            }
            
            // 清除供应商查询缓存
            $queryPattern = SystemConstants::CACHE_PREFIX . 'merchant:supplier:*';
            $queryKeys = $redis->keys($queryPattern);
            if (!empty($queryKeys)) {
                $redis->del($queryKeys);
                $clearedKeys = array_merge($clearedKeys, $queryKeys);
            }

            Log::info('所有供应商缓存已清除', [
                'cleared_keys_count' => count($clearedKeys),
                'supplier_keys_count' => count($supplierKeys),
                'query_keys_count' => count($queryKeys)
            ]);

        } catch (\Exception $e) {
            Log::error('清除所有供应商缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
