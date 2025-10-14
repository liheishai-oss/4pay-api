<?php

namespace app\common\helpers;

use app\common\config\OrderConfig;
use support\Redis;
use support\Log;

/**
 * 缓存防护工具类
 * 防止缓存穿透和雪崩
 */
class CacheProtectionHelper
{
    /**
     * 获取随机TTL，防止缓存雪崩
     * @param int $baseTtl 基础TTL
     * @return int 随机TTL
     */
    public static function getRandomTtl(int $baseTtl): int
    {
        $randomRange = OrderConfig::CACHE_TTL_RANDOM_RANGE;
        $randomOffset = mt_rand(-$randomRange, $randomRange);
        return max(60, $baseTtl + $randomOffset); // 最小60秒
    }
    
    /**
     * 安全设置缓存，防止雪崩
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $baseTtl 基础TTL
     * @return bool 是否成功
     */
    public static function setCacheWithRandomTtl(string $key, $value, int $baseTtl): bool
    {
        try {
            $randomTtl = self::getRandomTtl($baseTtl);
            try {
                $result = Redis::setex($key, $randomTtl, is_string($value) ? $value : json_encode($value));
            } catch (\Throwable $redisException) {
                Log::warning('Redis写入失败，跳过防雪崩缓存', [
                    'key' => $key,
                    'error' => $redisException->getMessage()
                ]);
                return false;
            }
            
            Log::debug('设置缓存（防雪崩）', [
                'key' => $key,
                'base_ttl' => $baseTtl,
                'random_ttl' => $randomTtl,
                'result' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('设置缓存失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 安全获取缓存，防止穿透
     * @param string $key 缓存键
     * @param callable $fallback 回退函数
     * @param int $baseTtl 基础TTL
     * @return mixed 缓存值
     */
    public static function getCacheWithFallback(string $key, callable $fallback, int $baseTtl)
    {
        try {
            // 尝试从缓存获取
            $cached = Redis::get($key);
            
            if ($cached !== false) {
                // 缓存命中
                if ($cached === 'NULL') {
                    Log::debug('缓存命中（空值）', ['key' => $key]);
                    return null;
                }
                
                Log::debug('缓存命中', ['key' => $key]);
                return json_decode($cached, true) ?: $cached;
            }
            
            // 缓存未命中，执行回退函数
            Log::debug('缓存未命中，执行回退函数', ['key' => $key]);
            $result = $fallback();
            
            // 缓存结果
            if ($result !== null) {
                self::setCacheWithRandomTtl($key, $result, $baseTtl);
            } else {
                // 缓存空值
                self::setCacheWithRandomTtl($key, 'NULL', OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('缓存操作异常', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // 异常时直接执行回退函数
            return $fallback();
        }
    }
    
    /**
     * 检查缓存穿透风险
     * @param string $key 缓存键
     * @return bool 是否有风险
     */
    public static function checkCachePenetrationRisk(string $key): bool
    {
        try {
            $pattern = $key . '*';
            $keys = Redis::keys($pattern);
            $nullCount = 0;
            
            foreach ($keys as $k) {
                $value = Redis::get($k);
                if ($value === 'NULL') {
                    $nullCount++;
                }
            }
            
            $penetrationRate = $nullCount / max(1, count($keys));
            
            if ($penetrationRate > 0.8) {
                Log::warning('检测到缓存穿透风险', [
                    'key' => $key,
                    'penetration_rate' => $penetrationRate,
                    'null_count' => $nullCount,
                    'total_keys' => count($keys)
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('检查缓存穿透风险失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
