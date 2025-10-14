<?php

namespace app\common\helpers;

use app\common\AppConstants;
use app\common\constants\OrderConstants;

class BloomFilterHelper
{
    public static  function addToNoGetOrderBloomFilter(string $orderId): void
    {
        $filterKey = OrderConstants::BLOOM_KEY_NO_GET_ORDER_PREFIX . date('YmdHi');
        try {

            // 初始化布隆过滤器（如果已存在不会重复创建），设置 TTL 为 10 分钟
            RedisBloomHelper::init($filterKey, 0.01, 100000, OrderConstants::BLOOM_FILTER_NO_GET_TTL);

            // 添加订单号到布隆过滤器中
            RedisBloomHelper::add($orderId, $filterKey);


        } catch (\Throwable $e) {
            $fallbackKey = 'fallback_bloom_' . $orderId;

            apcu_store($fallbackKey, 1, OrderConstants::BLOOM_FILTER_NO_GET_TTL);
        }
    }
}