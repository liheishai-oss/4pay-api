<?php

namespace app\common\helpers;

use app\model\Merchant;
use app\model\Product;
use app\model\ProductMerchant;
use app\model\PaymentChannel;
use support\Log;

/**
 * 缓存预热服务
 * 在系统启动时预热关键数据到缓存中
 */
class CacheWarmupService
{
    /**
     * 预热所有关键缓存
     * @return array 预热结果统计
     */
    public static function warmupAll(): array
    {
        $results = [
            'merchant_cache' => 0,
            'product_cache' => 0,
            'product_merchant_cache' => 0,
            'channel_cache' => 0,
            'query_cache' => 0,
            'total_time' => 0
        ];

        $startTime = microtime(true);

        try {
            Log::info('开始缓存预热');

            // 1. 预热商户缓存
            $results['merchant_cache'] = self::warmupMerchantCache();

            // 2. 预热产品缓存
            $results['product_cache'] = self::warmupProductCache();

            // 3. 预热商户产品关系缓存
            $results['product_merchant_cache'] = self::warmupProductMerchantCache();

            // 4. 预热通道缓存
            $results['channel_cache'] = self::warmupChannelCache();

            // 5. 预热查询缓存
            $results['query_cache'] = self::warmupQueryCache();

            $results['total_time'] = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('缓存预热完成', $results);

        } catch (\Throwable $e) {
            Log::error('缓存预热失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * 预热商户缓存
     * @return int 预热的商户数量
     */
    public static function warmupMerchantCache(): int
    {
        $count = 0;
        
        try {
            $merchants = Merchant::where('status', 1)
                ->where('is_deleted', 0)
                ->get();

            foreach ($merchants as $merchant) {
                $cacheKey = CacheKeys::getMerchantInfo($merchant->merchant_key);
                CacheHelper::setCache($cacheKey, $merchant->toArray(), 3600); // 1小时
                $count++;
            }

            Log::info('商户缓存预热完成', ['count' => $count]);

        } catch (\Throwable $e) {
            Log::error('商户缓存预热失败', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * 预热产品缓存
     * @return int 预热的产品数量
     */
    public static function warmupProductCache(): int
    {
        $count = 0;
        
        try {
            $products = Product::where('status', 1)->get();

            foreach ($products as $product) {
                // 预热产品信息缓存
                $productCacheKey = CacheKeys::getProductInfo($product->id);
                CacheHelper::setCache($productCacheKey, $product->toArray(), 3600);

                // 预热产品代码查询缓存
                $codeCacheKey = CacheKeys::getProductCodeQuery($product->external_code);
                QueryCacheHelper::getCacheOrQuery($codeCacheKey, fn() => $product->toArray(), 3600);

                $count++;
            }

            Log::info('产品缓存预热完成', ['count' => $count]);

        } catch (\Throwable $e) {
            Log::error('产品缓存预热失败', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * 预热商户产品关系缓存
     * @return int 预热的关系数量
     */
    public static function warmupProductMerchantCache(): int
    {
        $count = 0;
        
        try {
            $relations = ProductMerchant::where('status', 1)->get();

            foreach ($relations as $relation) {
                // 预热商户费率查询缓存
                $rateCacheKey = CacheKeys::getMerchantRateQuery($relation->merchant_id, $relation->product_id);
                QueryCacheHelper::getCacheOrQuery($rateCacheKey, fn() => $relation->merchant_rate, 1800);

                $count++;
            }

            Log::info('商户产品关系缓存预热完成', ['count' => $count]);

        } catch (\Throwable $e) {
            Log::error('商户产品关系缓存预热失败', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * 预热通道缓存
     * @return int 预热的通道数量
     */
    public static function warmupChannelCache(): int
    {
        $count = 0;
        
        try {
            $channels = PaymentChannel::where('status', 1)->get();

            foreach ($channels as $channel) {
                // 预热通道信息缓存
                $channelCacheKey = CacheKeys::getChannelInfo($channel->id);
                CacheHelper::setCache($channelCacheKey, $channel->toArray(), 1800);

                // 预热通道列表查询缓存
                $listCacheKey = CacheKeys::getChannelListQuery($channel->product_code);
                QueryCacheHelper::getCacheOrQuery($listCacheKey, function() use ($channel) {
                    return PaymentChannel::where('product_code', $channel->product_code)
                        ->where('status', 1)
                        ->orderBy('weight', 'desc')
                        ->get()
                        ->toArray();
                }, 1800);

                $count++;
            }

            Log::info('通道缓存预热完成', ['count' => $count]);

        } catch (\Throwable $e) {
            Log::error('通道缓存预热失败', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * 预热查询缓存
     * @return int 预热的查询数量
     */
    public static function warmupQueryCache(): int
    {
        $count = 0;
        
        try {
            // 预热常用查询组合
            $merchants = Merchant::where('status', 1)->get();
            $products = Product::where('status', 1)->get();

            foreach ($merchants as $merchant) {
                foreach ($products as $product) {
                    // 预热订单创建数据缓存
                    $orderCreationKey = CacheKeys::getOrderCreationData($merchant->merchant_key, $product->id);
                    QueryCacheHelper::getCacheOrQuery($orderCreationKey, function() use ($merchant, $product) {
                        // 模拟订单创建数据查询
                        return [
                            'merchant' => $merchant->toArray(),
                            'product' => $product->toArray(),
                            'merchant_rate' => 0,
                            'product_merchant_status' => 1
                        ];
                    }, 300);

                    $count++;
                }
            }

            Log::info('查询缓存预热完成', ['count' => $count]);

        } catch (\Throwable $e) {
            Log::error('查询缓存预热失败', ['error' => $e->getMessage()]);
        }

        return $count;
    }

}

