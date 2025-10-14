<?php

namespace app\common\helpers;

use support\Redis;
use support\Log;

/**
 * 多级缓存助手类
 * 实现L1(内存) + L2(Redis) + L3(数据库)的多级缓存策略
 */
class MultiLevelCacheHelper
{
    // L1缓存：内存缓存（使用静态变量模拟）
    private static $memoryCache = [];
    private static $memoryCacheExpiry = [];
    
    // 缓存配置 - 针对订单处理业务优化
    private const L1_TTL = 30;      // L1缓存30秒（高频访问，快速响应）
    private const L2_TTL = 120;      // L2缓存2分钟（平衡性能和实时性）
    private const L3_TTL = 600;      // L3缓存10分钟（订单支付时间窗口）
    
    // 业务场景特定缓存时间
    private const ORDER_PRODUCT_L1_TTL = 15;    // 产品信息L1缓存15秒（状态变化敏感）
    private const ORDER_PRODUCT_L2_TTL = 60;    // 产品信息L2缓存1分钟
    private const ORDER_CHANNEL_L1_TTL = 20;    // 通道信息L1缓存20秒（通道状态敏感）
    private const ORDER_CHANNEL_L2_TTL = 90;    // 通道信息L2缓存1.5分钟
    private const ORDER_MERCHANT_L1_TTL = 30;   // 商户信息L1缓存30秒（相对稳定）
    private const ORDER_MERCHANT_L2_TTL = 180;  // 商户信息L2缓存3分钟

    /**
     * 多级缓存获取
     * @param string $cacheKey 缓存键
     * @param callable $queryCallback 数据库查询回调
     * @param int $l2Ttl L2缓存时间（秒）
     * @param int $l3Ttl L3缓存时间（秒）
     * @return mixed
     */
    public static function get(string $cacheKey, callable $queryCallback, int $l2Ttl = self::L2_TTL, int $l3Ttl = self::L3_TTL)
    {
        // L1: 内存缓存
        $l1Result = self::getL1Cache($cacheKey);
        if ($l1Result !== null) {
            Log::debug('L1缓存命中', ['cache_key' => $cacheKey]);
            return $l1Result;
        }

        // L2: Redis缓存
        $l2Result = self::getL2Cache($cacheKey);
        if ($l2Result !== null) {
            // 回填L1缓存
            self::setL1Cache($cacheKey, $l2Result);
            Log::debug('L2缓存命中', ['cache_key' => $cacheKey]);
            return $l2Result;
        }

        // L3: 数据库查询
        try {
            $l3Result = $queryCallback();
            
            if ($l3Result !== null) {
                // 回填L2和L1缓存
                self::setL2Cache($cacheKey, $l3Result, $l2Ttl);
                self::setL1Cache($cacheKey, $l3Result);
                Log::debug('L3数据库查询', ['cache_key' => $cacheKey]);
            }
            
            return $l3Result;
        } catch (\Throwable $e) {
            Log::error('多级缓存查询失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 多级缓存设置
     * @param string $cacheKey 缓存键
     * @param mixed $data 数据
     * @param int $l2Ttl L2缓存时间（秒）
     * @return bool
     */
    public static function set(string $cacheKey, $data, int $l2Ttl = self::L2_TTL): bool
    {
        $success = true;
        
        // 设置L1缓存
        if (!self::setL1Cache($cacheKey, $data)) {
            $success = false;
        }
        
        // 设置L2缓存
        if (!self::setL2Cache($cacheKey, $data, $l2Ttl)) {
            $success = false;
        }
        
        return $success;
    }

    /**
     * 多级缓存删除
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function delete(string $cacheKey): bool
    {
        $success = true;
        
        // 删除L1缓存
        if (!self::deleteL1Cache($cacheKey)) {
            $success = false;
        }
        
        // 删除L2缓存
        if (!self::deleteL2Cache($cacheKey)) {
            $success = false;
        }
        
        return $success;
    }

    /**
     * 清理3级缓存（L1 + L2 + L3）
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function clearAllLevels(string $cacheKey): bool
    {
        $success = true;
        
        // 清理L1缓存
        if (!self::deleteL1Cache($cacheKey)) {
            $success = false;
        }
        
        // 清理L2缓存
        if (!self::deleteL2Cache($cacheKey)) {
            $success = false;
        }
        
        // L3缓存不需要清理（数据库数据）
        
        Log::info('3级缓存清理完成', [
            'cache_key' => $cacheKey,
            'success' => $success
        ]);
        
        return $success;
    }
    
    /**
     * 批量清理3级缓存
     * @param array $cacheKeys 缓存键数组
     * @return bool
     */
    public static function clearAllLevelsBatch(array $cacheKeys): bool
    {
        $success = true;
        $clearedCount = 0;
        
        foreach ($cacheKeys as $cacheKey) {
            if (self::clearAllLevels($cacheKey)) {
                $clearedCount++;
            } else {
                $success = false;
            }
        }
        
        Log::info('批量3级缓存清理完成', [
            'total_keys' => count($cacheKeys),
            'cleared_count' => $clearedCount,
            'success' => $success
        ]);
        
        return $success;
    }

    /**
     * 清理模式匹配的3级缓存
     * @param string $pattern 模式
     * @return int 清理的缓存数量
     */
    public static function clearAllLevelsByPattern(string $pattern): int
    {
        try {
            $keys = Redis::keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            
            $clearedCount = 0;
            foreach ($keys as $key) {
                if (self::clearAllLevels($key)) {
                    $clearedCount++;
                }
            }
            
            Log::info('模式匹配3级缓存清理完成', [
                'pattern' => $pattern,
                'cleared_count' => $clearedCount
            ]);
            
            return $clearedCount;
        } catch (\Throwable $e) {
            Log::error('模式匹配3级缓存清理失败', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 订单处理专用缓存获取 - 产品信息（状态变化敏感）
     * @param string $cacheKey 缓存键
     * @param callable $queryCallback 数据库查询回调
     * @return mixed
     */
    public static function getOrderProduct(string $cacheKey, callable $queryCallback)
    {
        return self::get($cacheKey, $queryCallback, self::ORDER_PRODUCT_L2_TTL, self::L3_TTL);
    }

    /**
     * 订单处理专用缓存获取 - 通道信息（通道状态敏感）
     * @param string $cacheKey 缓存键
     * @param callable $queryCallback 数据库查询回调
     * @return mixed
     */
    public static function getOrderChannel(string $cacheKey, callable $queryCallback)
    {
        return self::get($cacheKey, $queryCallback, self::ORDER_CHANNEL_L2_TTL, self::L3_TTL);
    }

    /**
     * 订单处理专用缓存获取 - 商户信息（相对稳定）
     * @param string $cacheKey 缓存键
     * @param callable $queryCallback 数据库查询回调
     * @return mixed
     */
    public static function getOrderMerchant(string $cacheKey, callable $queryCallback)
    {
        return self::get($cacheKey, $queryCallback, self::ORDER_MERCHANT_L2_TTL, self::L3_TTL);
    }

    /**
     * 订单处理专用缓存设置 - 产品信息
     * @param string $cacheKey 缓存键
     * @param mixed $data 数据
     * @return bool
     */
    public static function setOrderProduct(string $cacheKey, $data): bool
    {
        return self::set($cacheKey, $data, self::ORDER_PRODUCT_L2_TTL);
    }

    /**
     * 订单处理专用缓存设置 - 通道信息
     * @param string $cacheKey 缓存键
     * @param mixed $data 数据
     * @return bool
     */
    public static function setOrderChannel(string $cacheKey, $data): bool
    {
        return self::set($cacheKey, $data, self::ORDER_CHANNEL_L2_TTL);
    }

    /**
     * 订单处理专用缓存设置 - 商户信息
     * @param string $cacheKey 缓存键
     * @param mixed $data 数据
     * @return bool
     */
    public static function setOrderMerchant(string $cacheKey, $data): bool
    {
        return self::set($cacheKey, $data, self::ORDER_MERCHANT_L2_TTL);
    }

    /**
     * 订单处理专用缓存清理 - 产品信息（状态变化敏感）
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function clearOrderProduct(string $cacheKey): bool
    {
        return self::clearAllLevels($cacheKey);
    }

    /**
     * 订单处理专用缓存清理 - 通道信息（通道状态敏感）
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function clearOrderChannel(string $cacheKey): bool
    {
        return self::clearAllLevels($cacheKey);
    }

    /**
     * 订单处理专用缓存清理 - 商户信息（相对稳定）
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function clearOrderMerchant(string $cacheKey): bool
    {
        return self::clearAllLevels($cacheKey);
    }

    /**
     * 订单处理专用批量缓存清理 - 产品信息
     * @param array $cacheKeys 缓存键数组
     * @return bool
     */
    public static function clearOrderProductBatch(array $cacheKeys): bool
    {
        return self::clearAllLevelsBatch($cacheKeys);
    }

    /**
     * 订单处理专用批量缓存清理 - 通道信息
     * @param array $cacheKeys 缓存键数组
     * @return bool
     */
    public static function clearOrderChannelBatch(array $cacheKeys): bool
    {
        return self::clearAllLevelsBatch($cacheKeys);
    }

    /**
     * 订单处理专用批量缓存清理 - 商户信息
     * @param array $cacheKeys 缓存键数组
     * @return bool
     */
    public static function clearOrderMerchantBatch(array $cacheKeys): bool
    {
        return self::clearAllLevelsBatch($cacheKeys);
    }

    /**
     * 订单处理专用模式匹配缓存清理 - 产品信息
     * @param string $pattern 模式
     * @return int 清理的缓存数量
     */
    public static function clearOrderProductByPattern(string $pattern): int
    {
        return self::clearAllLevelsByPattern($pattern);
    }

    /**
     * 订单处理专用模式匹配缓存清理 - 通道信息
     * @param string $pattern 模式
     * @return int 清理的缓存数量
     */
    public static function clearOrderChannelByPattern(string $pattern): int
    {
        return self::clearAllLevelsByPattern($pattern);
    }

    /**
     * 订单处理专用模式匹配缓存清理 - 商户信息
     * @param string $pattern 模式
     * @return int 清理的缓存数量
     */
    public static function clearOrderMerchantByPattern(string $pattern): int
    {
        return self::clearAllLevelsByPattern($pattern);
    }

    /**
     * 批量多级缓存获取
     * @param array $cacheKeys 缓存键数组
     * @param callable $queryCallback 批量查询回调
     * @param int $l2Ttl L2缓存时间（秒）
     * @return array
     */
    public static function getBatch(array $cacheKeys, callable $queryCallback, int $l2Ttl = self::L2_TTL): array
    {
        $results = [];
        $l1MissedKeys = [];
        $l2MissedKeys = [];

        // L1: 批量获取内存缓存
        foreach ($cacheKeys as $cacheKey) {
            $l1Result = self::getL1Cache($cacheKey);
            if ($l1Result !== null) {
                $results[$cacheKey] = $l1Result;
            } else {
                $l1MissedKeys[] = $cacheKey;
            }
        }

        if (empty($l1MissedKeys)) {
            Log::debug('批量L1缓存全部命中', ['total_keys' => count($cacheKeys)]);
            return $results;
        }

        // L2: 批量获取Redis缓存
        try {
            $l2Results = Redis::mget($l1MissedKeys);
            
            foreach ($l1MissedKeys as $index => $cacheKey) {
                if ($l2Results[$index] !== null) {
                    $data = json_decode($l2Results[$index], true);
                    $results[$cacheKey] = $data;
                    // 回填L1缓存
                    self::setL1Cache($cacheKey, $data);
                } else {
                    $l2MissedKeys[] = $cacheKey;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('批量L2缓存获取失败', [
                'missed_keys' => $l1MissedKeys,
                'error' => $e->getMessage()
            ]);
            $l2MissedKeys = $l1MissedKeys;
        }

        if (empty($l2MissedKeys)) {
            Log::debug('批量L2缓存全部命中', ['total_keys' => count($cacheKeys)]);
            return $results;
        }

        // L3: 批量数据库查询
        try {
            $l3Results = $queryCallback($l2MissedKeys);
            
            // 回填L2和L1缓存
            $l2CacheData = [];
            foreach ($l2MissedKeys as $cacheKey) {
                if (isset($l3Results[$cacheKey])) {
                    $results[$cacheKey] = $l3Results[$cacheKey];
                    $l2CacheData[$cacheKey] = json_encode($l3Results[$cacheKey]);
                    // 回填L1缓存
                    self::setL1Cache($cacheKey, $l3Results[$cacheKey]);
                }
            }
            
            if (!empty($l2CacheData)) {
                try {
                    $pipe = Redis::pipeline();
                    foreach ($l2CacheData as $key => $value) {
                        $pipe->setex($key, $l2Ttl, $value);
                    }
                    $pipe->exec();
                    
                    Log::debug('批量L2缓存写入成功', [
                        'cached_count' => count($l2CacheData),
                        'ttl' => $l2Ttl
                    ]);
                } catch (\Throwable $redisException) {
                    // Redis批量写入失败，记录警告但不影响业务逻辑
                    Log::warning('批量L2缓存写入失败，跳过缓存', [
                        'cache_count' => count($l2CacheData),
                        'error' => $redisException->getMessage()
                    ]);
                }
            }
            
            Log::debug('批量L3数据库查询完成', [
                'total_keys' => count($cacheKeys),
                'l1_hit' => count($cacheKeys) - count($l1MissedKeys),
                'l2_hit' => count($l1MissedKeys) - count($l2MissedKeys),
                'l3_hit' => count($l2MissedKeys)
            ]);
        } catch (\Throwable $e) {
            Log::error('批量L3数据库查询失败', [
                'missed_keys' => $l2MissedKeys,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * 获取L1缓存（内存）
     * @param string $cacheKey
     * @return mixed|null
     */
    private static function getL1Cache(string $cacheKey)
    {
        if (isset(self::$memoryCache[$cacheKey]) && isset(self::$memoryCacheExpiry[$cacheKey])) {
            if (time() < self::$memoryCacheExpiry[$cacheKey]) {
                return self::$memoryCache[$cacheKey];
            } else {
                // 过期，清理
                unset(self::$memoryCache[$cacheKey], self::$memoryCacheExpiry[$cacheKey]);
            }
        }
        return null;
    }

    /**
     * 设置L1缓存（内存）
     * @param string $cacheKey
     * @param mixed $data
     * @return bool
     */
    private static function setL1Cache(string $cacheKey, $data): bool
    {
        try {
            self::$memoryCache[$cacheKey] = $data;
            self::$memoryCacheExpiry[$cacheKey] = time() + self::L1_TTL;
            return true;
        } catch (\Throwable $e) {
            Log::warning('L1缓存设置失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 删除L1缓存（内存）
     * @param string $cacheKey
     * @return bool
     */
    private static function deleteL1Cache(string $cacheKey): bool
    {
        try {
            unset(self::$memoryCache[$cacheKey], self::$memoryCacheExpiry[$cacheKey]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('L1缓存删除失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取L2缓存（Redis）
     * @param string $cacheKey
     * @return mixed|null
     */
    private static function getL2Cache(string $cacheKey)
    {
        try {
            $data = Redis::get($cacheKey);
            return $data !== null ? json_decode($data, true) : null;
        } catch (\Throwable $e) {
            Log::warning('L2缓存获取失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 设置L2缓存（Redis）
     * @param string $cacheKey
     * @param mixed $data
     * @param int $ttl
     * @return bool
     */
    private static function setL2Cache(string $cacheKey, $data, int $ttl): bool
    {
        try {
            Redis::setex($cacheKey, $ttl, json_encode($data));
            return true;
        } catch (\Throwable $e) {
            Log::warning('L2缓存设置失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 删除L2缓存（Redis）
     * @param string $cacheKey
     * @return bool
     */
    private static function deleteL2Cache(string $cacheKey): bool
    {
        try {
            Redis::del($cacheKey);
            return true;
        } catch (\Throwable $e) {
            Log::warning('L2缓存删除失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清理过期的L1缓存
     * @return int 清理的缓存数量
     */
    public static function cleanExpiredL1Cache(): int
    {
        $cleaned = 0;
        $currentTime = time();
        
        foreach (self::$memoryCacheExpiry as $cacheKey => $expiry) {
            if ($currentTime >= $expiry) {
                unset(self::$memoryCache[$cacheKey], self::$memoryCacheExpiry[$cacheKey]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            Log::info('L1缓存过期清理完成', ['cleaned_count' => $cleaned]);
        }
        
        return $cleaned;
    }

    /**
     * 获取缓存统计信息
     * @return array
     */
    public static function getCacheStats(): array
    {
        return [
            'l1_cache_count' => count(self::$memoryCache),
            'l1_cache_keys' => array_keys(self::$memoryCache),
            'l1_expired_count' => count(array_filter(self::$memoryCacheExpiry, fn($expiry) => time() >= $expiry))
        ];
    }
}

