<?php

return [
    'test_param' => [
        'order_no'     => 'BY' . date('YmdHis') . rand(1000, 9999),
        'total_amount' => '1.00',
        'payment_method' => 'alipay',
        'subject'      => '海豚支付测试订单',
        'body'         => '这是一个海豚支付测试订单',
        'payer_ip'     => '127.0.0.1',
        'payer_id'     => 'test_user_001',
        'timestamp'    => time(),
        'notify_url'   => env('HAITUN_NOTIFY_URL', 'http://baidu.com/callback/haitun'),
        'return_url'   => env('HAITUN_RETURN_URL', 'http://baidu.com/return/haitun'),
    ]
];

