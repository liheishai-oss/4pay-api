<?php
/**
 * 修复被误关闭的订单
 * 将供应商已支付成功但本地被误关闭的订单恢复为支付成功状态
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\model\Order;
use support\Db;
use support\Log;

// 被误关闭的订单号
$misclosedOrderNo = 'BY20251011103556C9F02467';

try {
    echo "开始修复被误关闭的订单: {$misclosedOrderNo}\n";
    
    // 查找订单
    $order = Order::where('order_no', $misclosedOrderNo)->first();
    if (!$order) {
        echo "订单不存在: {$misclosedOrderNo}\n";
        exit(1);
    }
    
    echo "当前订单状态: {$order->status}\n";
    echo "第三方订单号: " . ($order->third_party_order_no ?? 'null') . "\n";
    echo "支付时间: " . ($order->paid_time ?? 'null') . "\n";
    
    if ($order->status !== 6) {
        echo "订单状态不是已关闭(6)，无需修复\n";
        exit(0);
    }
    
    // 开始事务
    Db::beginTransaction();
    
    // 更新订单状态为支付成功
    $order->status = 3; // 3-支付成功
    $order->paid_time = '2025-10-11 10:35:56'; // 使用供应商的支付时间
    $order->third_party_order_no = '2025101110355629853'; // 供应商的交易号
    $order->updated_at = date('Y-m-d H:i:s');
    
    $order->save();
    
    Db::commit();
    
    echo "订单修复成功！\n";
    echo "新状态: 3 (支付成功)\n";
    echo "支付时间: 2025-10-11 10:35:56\n";
    echo "第三方订单号: 2025101110355629853\n";
    
    // 记录修复日志
    Log::info('修复被误关闭的订单', [
        'order_no' => $misclosedOrderNo,
        'old_status' => 6,
        'new_status' => 3,
        'paid_time' => '2025-10-11 10:35:56',
        'third_party_order_no' => '2025101110355629853',
        'reason' => '供应商已支付成功，但被订单超时检查进程误关闭'
    ]);
    
} catch (\Exception $e) {
    Db::rollBack();
    echo "修复失败: " . $e->getMessage() . "\n";
    echo "错误追踪: " . $e->getTraceAsString() . "\n";
    exit(1);
}



