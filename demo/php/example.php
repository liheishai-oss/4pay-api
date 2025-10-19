<?php
/**
 * 4Pay SDK 使用示例
 */

require_once __DIR__ . '/FourPaySDK.php';

// 创建SDK实例
$sdk = new FourPaySDK();

try {
    echo "=== 4Pay SDK 使用示例 ===\n\n";
    
    // 1. 查询余额
    echo "1. 查询余额\n";
    echo "------------\n";
    $balanceResult = $sdk->queryBalance();
    echo "HTTP状态码: " . $balanceResult['http_code'] . "\n";
    echo "响应数据: " . json_encode($balanceResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 2. 创建订单
    echo "2. 创建订单\n";
    echo "------------\n";
    $orderData = [
        'merchant_order_no' => 'DEMO_' . date('YmdHis') . '_' . rand(1000, 9999),
        'order_amount' => '10.00',
        'product_code' => '8416',
        'order_title' => '演示订单',
        'order_body' => '这是一个使用SDK创建的演示订单',
        'payer_id' => 'DEMO_USER_' . rand(1000, 9999),
        'extra_data' => [
            'source' => 'demo',
            'version' => '1.0'
        ]
    ];
    
    // 验证订单数据
    $errors = $sdk->validateOrderData($orderData);
    if (!empty($errors)) {
        echo "订单数据验证失败:\n";
        foreach ($errors as $error) {
            echo "- " . $error . "\n";
        }
        exit;
    }
    
    $createResult = $sdk->createOrder($orderData);
    echo "HTTP状态码: " . $createResult['http_code'] . "\n";
    echo "响应数据: " . json_encode($createResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 3. 如果订单创建成功，查询订单状态
    if (isset($createResult['data']['data']['order_no'])) {
        $orderNo = $createResult['data']['data']['order_no'];
        echo "3. 查询订单状态\n";
        echo "----------------\n";
        echo "订单号: {$orderNo}\n\n";
        
        // 等待2秒后查询
        sleep(2);
        
        $queryResult = $sdk->queryOrder($orderNo);
        echo "HTTP状态码: " . $queryResult['http_code'] . "\n";
        echo "响应数据: " . json_encode($queryResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "订单创建失败，跳过订单查询\n\n";
    }
    
    // 4. 显示产品列表
    echo "4. 可用产品列表\n";
    echo "----------------\n";
    $products = $sdk->getProducts();
    foreach ($products as $code => $name) {
        echo "- {$code}: {$name}\n";
    }
    echo "\n";
    
    // 5. 回调验证示例
    echo "5. 回调验证示例\n";
    echo "----------------\n";
    $callbackData = [
        'order_no' => 'BY20251019091434C4CA8582',
        'merchant_order_no' => 'DEMO_20251019091434_1234',
        'order_amount' => '10.00',
        'order_status' => 'success',
        'sign' => 'EXAMPLE_SIGN'
    ];
    
    $isValid = $sdk->verifyCallback($callbackData);
    echo "回调数据验证结果: " . ($isValid ? '有效' : '无效') . "\n\n";
    
    echo "示例完成！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
