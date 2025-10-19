<?php
/**
 * 4Pay API 配置文件
 */

return [
    // API基础配置
    'api_base_url' => 'http://127.0.0.1:8787/api/v1',
    
    // 商户配置
    'merchant' => [
        'key' => 'MCH_68F0E79CA6E42_20251016',
        'secret' => 'your_merchant_secret', // 请替换为实际的商户密钥
    ],
    
    // 请求配置
    'request' => [
        'timeout' => 30,
        'connect_timeout' => 10,
    ],
    
    // 产品配置
    'products' => [
        '8416' => '支付宝支付',
        '8417' => '微信支付',
        '8418' => '银联支付',
    ],
    
    // 订单配置
    'order' => [
        'amount_min' => '0.01',
        'amount_max' => '999999.99',
        'title_max_length' => 128,
        'body_max_length' => 256,
    ],
    
    // 回调配置
    'callback' => [
        'notify_url' => 'http://127.0.0.1/notify',
        'return_url' => 'https://example.com/return',
    ],
];
