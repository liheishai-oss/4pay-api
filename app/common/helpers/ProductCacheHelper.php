<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;
use support\Redis;
/**
 * 产品缓存管理辅助类
 * 专门用于清理产品相关的Redis缓存
 */
class ProductCacheHelper
{

    /**
     * 清除指定产品代码的缓存
     * @param string $productCode 产品代码
     */
    public static function clearProductCodeCache(string $productCode): void
    {
        echo "进入了清理";
        try {

            $clearedKeys = [];
            
            // 清除产品代码缓存
            $codeCacheKey = SystemConstants::CACHE_PREFIX . 'product:code:' . $productCode;
            echo "正在清理：{$codeCacheKey}";
            Redis::del($codeCacheKey);
            $clearedKeys[] = $codeCacheKey;
            
            // 清除产品代码查询缓存（创建订单时使用的缓存）
            $queryCacheKey = SystemConstants::CACHE_PREFIX . 'merchant:product_code:' . $productCode;
            Redis::del($queryCacheKey);
            $clearedKeys[] = $queryCacheKey;

            // 记录缓存清除日志
            \support\Log::info('产品代码缓存已清除', [
                'product_code' => $productCode,
                'cleared_keys' => $clearedKeys
            ]);

        } catch (\Exception $e) {
            echo "清除产品代码缓存失败：{$codeCacheKey}";
            \support\Log::error('清除产品代码缓存失败', [
                'product_code' => $productCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 清除指定产品的所有相关缓存（包括产品代码缓存）
     * @param int $productId 产品ID
     * @param string|null $productCode 产品代码（可选）
     */
    public static function clearProductAllCache(?string $productCode = null): void
    {
        // 清除产品缓存
        print_r("准备清理{$productCode}");
        self::clearProductCodeCache($productCode);

    }

    /**
     * 清除所有产品缓存
     */
    public static function clearAllProductCache(): void
    {
        try {
            $redis = Redis::connection();
            $clearedKeys = [];
            
            // 清除产品缓存
            $productPattern = SystemConstants::CACHE_PREFIX . 'merchant:product.*';
            $productKeys = $redis->keys($productPattern);
            if (!empty($productKeys)) {
                $redis->del($productKeys);
                $clearedKeys = array_merge($clearedKeys, $productKeys);
            }
            
            // 清除产品代码缓存
            $codePattern = SystemConstants::CACHE_PREFIX . 'product:code:*';
            $codeKeys = $redis->keys($codePattern);
            if (!empty($codeKeys)) {
                $redis->del($codeKeys);
                $clearedKeys = array_merge($clearedKeys, $codeKeys);
            }
            
            // 清除产品代码查询缓存
            $queryCodePattern = SystemConstants::CACHE_PREFIX . 'merchant:product_code:*';
            $queryCodeKeys = $redis->keys($queryCodePattern);
            if (!empty($queryCodeKeys)) {
                $redis->del($queryCodeKeys);
                $clearedKeys = array_merge($clearedKeys, $queryCodeKeys);
            }


            // 记录缓存清除日志
            \support\Log::info('所有产品缓存已清除', [
                'cleared_keys_count' => count($clearedKeys),
                'product_keys_count' => count($productKeys),
                'code_keys_count' => count($codeKeys),
                'query_code_keys_count' => count($queryCodeKeys)
            ]);

        } catch (\Exception $e) {
            \support\Log::error('清除所有产品缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

