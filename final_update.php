<?php
// 最终更新脚本
$orderNo = 'BY20251011144356C9F02907';

echo "开始更新订单: $orderNo\n";

// 使用PDO直接连接数据库
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=fourth_party_payment', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 先查询当前状态
    $stmt = $pdo->prepare("SELECT order_no, status, paid_time, third_party_order_no FROM orders WHERE order_no = ?");
    $stmt->execute([$orderNo]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "当前订单状态:\n";
        echo "订单号: {$order['order_no']}\n";
        echo "状态: {$order['status']}\n";
        echo "支付时间: {$order['paid_time']}\n";
        echo "第三方订单号: {$order['third_party_order_no']}\n";
        
        // 更新订单状态
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, paid_time = ?, third_party_order_no = ?, updated_at = NOW() WHERE order_no = ?");
        $result = $updateStmt->execute([3, '2025-10-11 14:43:57', '2025101114435749488', $orderNo]);
        
        if ($result) {
            echo "\n订单状态更新成功！\n";
            
            // 验证更新结果
            $stmt->execute([$orderNo]);
            $updatedOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "更新后的订单状态:\n";
            echo "订单号: {$updatedOrder['order_no']}\n";
            echo "状态: {$updatedOrder['status']}\n";
            echo "支付时间: {$updatedOrder['paid_time']}\n";
            echo "第三方订单号: {$updatedOrder['third_party_order_no']}\n";
        } else {
            echo "订单状态更新失败\n";
        }
    } else {
        echo "订单不存在: $orderNo\n";
    }
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
