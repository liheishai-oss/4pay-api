<?php
// 直接更新订单状态的脚本
$orderNo = 'BY20251011144356C9F02907';

// 使用Webman的数据库连接
require_once 'vendor/autoload.php';
use support\Db;

try {
    Db::connection();
    
    // 更新订单状态
    $result = Db::table('orders')
        ->where('order_no', $orderNo)
        ->update([
            'status' => 3, // 支付成功
            'paid_time' => '2025-10-11 14:43:57', // 使用供应商的支付时间
            'third_party_order_no' => '2025101114435749488', // 使用供应商的订单号
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    
    if ($result) {
        echo "订单状态更新成功，影响行数: $result\n";
        
        // 验证更新结果
        $order = Db::table('orders')->where('order_no', $orderNo)->first();
        if ($order) {
            echo "更新后的订单状态:\n";
            echo "状态: {$order['status']}\n";
            echo "支付时间: {$order['paid_time']}\n";
            echo "第三方订单号: {$order['third_party_order_no']}\n";
        }
    } else {
        echo "订单状态更新失败\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
