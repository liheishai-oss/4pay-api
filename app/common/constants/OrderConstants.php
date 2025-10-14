<?php

namespace app\common\constants;

/**
 * 订单相关常量
 */
class OrderConstants
{
    // 订单号生成重试限制
    const ORDER_NUMBER_RETRY_LIMIT = 10;
    
    // 订单号Redis缓存过期时间（秒）
    const ORDER_NUMBER_EXPIRE = 300; // 5分钟
    
    // 布隆过滤器相关常量
    const BLOOM_KEY_NO_GET_ORDER_PREFIX = 'bloom:no_get_order:';
    const BLOOM_FILTER_NO_GET_TTL = 3600; // 1小时
    
    // 商户订单号布隆过滤器相关常量
    const BLOOM_MERCHANT_ORDER_NO_ERROR_RATE = 0.01; // 错误率1%
    const BLOOM_MERCHANT_ORDER_NO_CAPACITY = 100000; // 容量10万
    const BLOOM_MERCHANT_ORDER_NO_TTL = 86400; // 24小时
    
    // 订单状态
    const STATUS_PENDING = 1;
    const STATUS_PAID = 2;
    const STATUS_FAILED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_REFUNDED = 5;
    const STATUS_EXPIRED = 6;
}
