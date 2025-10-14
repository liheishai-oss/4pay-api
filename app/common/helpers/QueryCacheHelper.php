<?php

namespace app\common\helpers;

use support\Redis;
use support\Log;

/**
 * 查询结果缓存助手类
 * 用于缓存数据库查询结果，减少数据库压力
 */
class QueryCacheHelper
{
    /**
     * 获取缓存或执行查询
     * @param string $cacheKey 缓存键
     * @param callable $queryCallback 查询回调函数
     * @param int $ttl 缓存时间（秒）
     * @param bool $useCompression 是否使用压缩
     * @return mixed
     */
    public static function getCacheOrQuery(string $cacheKey, callable $queryCallback, int $ttl = 300, bool $useCompression = false)
    {
        try {
            // 1. 尝试从缓存获取
            $cached = Redis::get($cacheKey);
            if ($cached !== null) {
                $data = $useCompression ? gzuncompress($cached) : $cached;
                $result = json_decode($data, true);
                
                Log::info('查询缓存命中', [
                    'cache_key' => $cacheKey,
                    'ttl' => $ttl
                ]);
                
                return $result;
            }
        } catch (\Throwable $e) {
            Log::warning('Redis读取查询缓存失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }

        // 2. 缓存未命中，执行查询
        try {
            $result = $queryCallback();
            
            // 3. 缓存查询结果（仅在Redis正常时写入）
            if ($result !== null) {
                try {
                    $data = json_encode($result);
                    $data = $useCompression ? gzcompress($data) : $data;
                    
                    Redis::setex($cacheKey, $ttl, $data);
                    
                    Log::info('查询结果已缓存', [
                        'cache_key' => $cacheKey,
                        'ttl' => $ttl,
                        'compressed' => $useCompression
                    ]);
                } catch (\Throwable $redisException) {
                    // Redis写入失败，记录警告但不影响业务逻辑
                    Log::warning('Redis写入失败，跳过缓存', [
                        'cache_key' => $cacheKey,
                        'error' => $redisException->getMessage()
                    ]);
                }
            }
            
            return $result;
        } catch (\Throwable $e) {
            Log::error('查询执行失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 批量获取缓存或执行查询
     * @param array $cacheKeys 缓存键数组
     * @param callable $queryCallback 查询回调函数
     * @param int $ttl 缓存时间（秒）
     * @return array
     */
    public static function getBatchCacheOrQuery(array $cacheKeys, callable $queryCallback, int $ttl = 300): array
    {
        $results = [];
        $missedKeys = [];

        try {
            // 1. 批量获取缓存
            $cachedData = Redis::mget($cacheKeys);
            
            foreach ($cacheKeys as $index => $cacheKey) {
                if ($cachedData[$index] !== null) {
                    $results[$cacheKey] = json_decode($cachedData[$index], true);
                } else {
                    $missedKeys[] = $cacheKey;
                }
            }
            
            if (!empty($missedKeys)) {
                Log::info('批量查询缓存部分命中', [
                    'total_keys' => count($cacheKeys),
                    'hit_keys' => count($cacheKeys) - count($missedKeys),
                    'miss_keys' => count($missedKeys)
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Redis批量读取查询缓存失败', [
                'cache_keys' => $cacheKeys,
                'error' => $e->getMessage()
            ]);
            $missedKeys = $cacheKeys;
        }

        // 2. 对未命中的键执行查询
        if (!empty($missedKeys)) {
            try {
                $queryResults = $queryCallback($missedKeys);
                
                // 3. 缓存查询结果
                $cacheData = [];
                foreach ($missedKeys as $cacheKey) {
                    if (isset($queryResults[$cacheKey])) {
                        $results[$cacheKey] = $queryResults[$cacheKey];
                        $cacheData[$cacheKey] = json_encode($queryResults[$cacheKey]);
                    }
                }
                
                if (!empty($cacheData)) {
                    try {
                        $pipe = Redis::pipeline();
                        foreach ($cacheData as $key => $value) {
                            $pipe->setex($key, $ttl, $value);
                        }
                        $pipe->exec();
                        
                        Log::info('批量查询结果已缓存', [
                            'cached_count' => count($cacheData),
                            'ttl' => $ttl
                        ]);
                    } catch (\Throwable $redisException) {
                        // Redis批量写入失败，记录警告但不影响业务逻辑
                        Log::warning('Redis批量写入失败，跳过缓存', [
                            'cache_count' => count($cacheData),
                            'error' => $redisException->getMessage()
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('批量查询执行失败', [
                    'missed_keys' => $missedKeys,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $results;
    }

    /**
     * 清除查询缓存
     * @param string|array $cacheKeys 缓存键或键数组
     * @return bool
     */
    public static function clearQueryCache($cacheKeys): bool
    {
        try {
            if (is_string($cacheKeys)) {
                $cacheKeys = [$cacheKeys];
            }
            
            $deleted = Redis::del($cacheKeys);
            
            Log::info('查询缓存已清除', [
                'cache_keys' => $cacheKeys,
                'deleted_count' => $deleted
            ]);
            
            return $deleted > 0;
        } catch (\Throwable $e) {
            Log::warning('清除查询缓存失败', [
                'cache_keys' => $cacheKeys,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除模式匹配的查询缓存
     * @param string $pattern 模式
     * @return int 清除的缓存数量
     */
    public static function clearQueryCacheByPattern(string $pattern): int
    {
        try {
            $keys = Redis::keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            
            $deleted = Redis::del($keys);
            
            Log::info('模式匹配查询缓存已清除', [
                'pattern' => $pattern,
                'deleted_count' => $deleted
            ]);
            
            return $deleted;
        } catch (\Throwable $e) {
            Log::warning('清除模式匹配查询缓存失败', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 预热查询缓存
     * @param array $preloadData 预加载数据 [cacheKey => queryCallback]
     * @param int $ttl 缓存时间（秒）
     * @return array 预热结果
     */
    public static function warmupQueryCache(array $preloadData, int $ttl = 300): array
    {
        $results = [];
        
        foreach ($preloadData as $cacheKey => $queryCallback) {
            try {
                $result = self::getCacheOrQuery($cacheKey, $queryCallback, $ttl);
                $results[$cacheKey] = $result !== null;
            } catch (\Throwable $e) {
                Log::warning('查询缓存预热失败', [
                    'cache_key' => $cacheKey,
                    'error' => $e->getMessage()
                ]);
                $results[$cacheKey] = false;
            }
        }
        
        Log::info('查询缓存预热完成', [
            'total_keys' => count($preloadData),
            'success_count' => array_sum($results)
        ]);
        
        return $results;
    }
}

