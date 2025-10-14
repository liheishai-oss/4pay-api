<?php

return [
    // 跑分支付配置
    'appid' => env('PAOFEN_APPID', '1832'),
    'api_key' => env('PAOFEN_API_KEY', '1832'),
    'api_secret' => env('PAOFEN_API_SECRET', '23bcd3520a9b4350a2e4cace07b91bf2'),
    'gateway_url' => env('PAOFEN_GATEWAY_URL', 'https://api.pf.yyzcss.com'),
    'notify_url' => env('PAOFEN_NOTIFY_URL', 'https://yourdomain.com/api/v1/callback/paofen'),
    'return_url' => env('PAOFEN_RETURN_URL', 'https://yourdomain.com/return/paofen'),
    'callback_ips' => env('PAOFEN_CALLBACK_IPS', '127.0.0.1,::1'),
    
    // 测试配置
    'test_param' => [
        'order_no'     => 'BY' . date('YmdHis') . rand(1000, 9999),
        'total_amount' => '1.00',
        'payment_method' => 'alipay',
        'subject'      => '跑分支付测试订单',
        'body'         => '这是一个跑分支付测试订单',
        'payer_ip'     => '127.0.0.1',
        'payer_id'     => 'test_user_001',
        'timestamp'    => time(),
        'notify_url'   => env('PAOFEN_NOTIFY_URL', 'https://yourdomain.com/api/v1/callback/paofen'),
        'return_url'   => env('PAOFEN_RETURN_URL', 'https://yourdomain.com/return/paofen'),
    ]
];

