<?php

namespace app\common\config;

/**
 * 订单相关配置常量
 * 
 * 统一管理订单创建流程中的硬编码配置
 * 便于维护和修改
 */
class OrderConfig
{
    // 订单过期时间配置
    public const DEFAULT_EXPIRE_MINUTES = 30;
    public const CACHE_TTL_SECONDS = 3600;
    public const LOCK_TIMEOUT_SECONDS = 30;
    
    // 金额限制配置
    public const MIN_ORDER_AMOUNT = 0.01;
    public const MAX_ORDER_AMOUNT = 999999.99;
    
    public const ORDER_NUMBER_CACHE_TTL = 300;
    
    // 激进缓存策略配置
    public const AGGRESSIVE_CACHE_STRATEGY = true;  // 启用激进缓存策略
    
    // 重试配置
    public const MAX_RETRY_ATTEMPTS = 3;
    public const RETRY_DELAY_SECONDS = 1;
    
    // 缓存配置
    public const MERCHANT_ORDER_CACHE_TTL = 3600; // 存在的订单号缓存1小时
    public const MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL = 300; // 不存在的订单号缓存5分钟
    public const PENDING_ORDER_CACHE_TTL = 300; // 创建中订单缓存5分钟
    
    // 缓存雪崩防护配置
    public const CACHE_TTL_RANDOM_RANGE = 60; // TTL随机范围（秒）
    public const CACHE_PREWARM_ENABLED = true; // 是否启用缓存预热
    
    // 调试配置
    public const DEBUG_ORDER_PREFIX = 'DEBUG_ORDER_';
    public const DEBUG_MERCHANT_PREFIX = 'MCH_debug_';
    public const DEBUG_SIGNATURE_PREFIX = 'debug_signature_';
    
    // Admin模块专用配置
    public const ADMIN_MENU_CACHE_TTL = 864000; // 管理员菜单缓存时间（10天）
    public const ADMIN_STATISTICS_CACHE_TTL = 300; // 统计缓存时间（5分钟）
    public const ADMIN_CACHE_PREFIX = 'admin:';
}
