<?php

return [
    // Telegram Bot Token
    'bot_token' => env('TELEGRAM_BOT_TOKEN', '8300442547:AAF90LaHrg6P_pFM76G88G2U_xtHE6WOT4c'),
    
    // 告警接收群组ID列表
    'alert_chat_ids' => [
        'admin' => env('TELEGRAM_ADMIN_CHAT_ID', '-4960001064'),
        'monitor' => env('TELEGRAM_MONITOR_CHAT_ID', '-4960001064'),
    ],
    
    // 告警类型开关
    'alert_types' => [
        'all_channels_failed' => true,
        'product_not_configured' => true,
        'no_available_polling_pool' => true,
    ],
    
    // 系统级错误（立即推送，不受频率限制）
    'critical_alerts' => [
        'all_channels_failed' => true,        // 所有支付通道失败
        'product_not_configured' => true,     // 产品未配置
        'no_available_polling_pool' => true,  // 轮询池不可用
        'supplier_request_failed' => true,    // 供应商请求失败
        'supplier_timeout' => true,           // 供应商请求超时
        'supplier_connection_error' => true,  // 供应商连接错误
        'supplier_auth_failed' => true,       // 供应商认证失败
        'supplier_config_error' => true,      // 供应商配置错误
        'abnormal_response' => true,          // 非正常响应（包括404错误）
        'database_error' => true,             // 数据库错误
        'cache_error' => true,                // 缓存错误
        'payment_gateway_down' => true,       // 支付网关宕机
        // 新增的异常类型
        'http_404' => true,                   // HTTP 404错误
        'server_error' => true,               // 服务器错误（5xx）
        'client_error' => true,               // 客户端错误（4xx）
        'network_error' => true,              // 网络错误
        'config_error' => true,               // 配置错误
        'invalid_params' => true,             // 参数错误
        'business_error' => true,             // 业务错误
        'service_not_found' => true,          // 服务未找到
        'payment_exception' => true,          // 支付异常
        'general_exception' => true,          // 通用异常
    ],
    
    // 频率限制配置
    'rate_limit' => [
        'max_alerts_per_minute' => 10,
        'cooldown_seconds' => 60,
    ],
    
    // 消息模板配置
    'templates' => [
        'all_channels_failed' => [
            'title' => '🚨 支付通道全部失败',
            'enabled' => true
        ],
        'product_not_configured' => [
            'title' => '⚠️ 商品未配置',
            'enabled' => true
        ],
        'no_available_polling_pool' => [
            'title' => '⚠️ 商品轮询池不可用',
            'enabled' => true
        ]
    ]
];