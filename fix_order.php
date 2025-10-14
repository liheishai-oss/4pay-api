<?php
require_once 'vendor/autoload.php';

use support\Db;
use app\model\Order;

// 初始化Webman环境
$config = require_once 'config/app.php';
$database = require_once 'config/database.php';

// 连接数据库
Db::connection();

$orderNo = 'BY20251011142436C9F03239';
$order = Order::where('order_no', $orderNo)->first();

if ($order) {
    echo "订单当前状态: {$order->status}\n";
    echo "订单当前支付时间: {$order->paid_time}\n";
    echo "订单当前第三方订单号: {$order->third_party_order_no}\n";
    
    // 更新订单状态为支付成功
    $order->status = 3; // 支付成功
    $order->paid_time = '2025-10-11 14:24:37'; // 使用供应商的支付时间
    $order->third_party_order_no = '2025101114243763916'; // 使用供应商的订单号
    $order->updated_at = date('Y-m-d H:i:s');
    $order->save();
    
    echo "订单状态已更新为支付成功\n";
} else {
    echo "订单不存在\n";
}



