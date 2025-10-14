<?php

return [


    // 测试参数
    'test_param' => [
        'order_no' => 'BY' . date('YmdHis') . rand(1000, 9999),
        'total_amount' => '1.00',
        'product_code' => 'alipay',
        'subject' => 'Baiyi测试订单',
        'client_ip' => '127.0.0.1',
        'device' => 'pc',
    ],
];



