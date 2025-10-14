<?php
require_once 'vendor/autoload.php';

use app\model\Order;
use app\model\PaymentChannel;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\status\StatusCheckerFactory;
use support\Db;
use support\Log;

// 连接数据库
Db::connection();

$orderNo = 'BY20251011142436C9F03239';
$order = Order::where('order_no', $orderNo)->first();

if (!$order) {
    echo "订单不存在: $orderNo\n";
    exit(1);
}

echo "订单详情:\n";
echo "订单号: {$order->order_no}\n";
echo "状态: {$order->status}\n";
echo "创建时间: {$order->created_at}\n";
echo "支付时间: {$order->paid_time}\n";

// 获取支付通道信息
$channel = PaymentChannel::find($order->channel_id);
if (!$channel) {
    echo "支付通道不存在\n";
    exit(1);
}

echo "支付通道: {$channel->interface_code}\n";

// 模拟供应商查询结果（基于之前的查询结果）
$supplierResponse = [
    'code' => 1,
    'msg' => 'succ',
    'trade_no' => '2025101114243763916',
    'out_trade_no' => $orderNo,
    'status' => 1, // 百易支付：1表示成功
    'money' => '1.00',
    'addtime' => '2025-10-11 14:24:37'
];

// 创建PaymentResult
$result = new PaymentResult(
    PaymentResult::STATUS_SUCCESS,
    '支付成功',
    $supplierResponse,
    $orderNo,
    '2025101114243763916',
    1.00,
    'CNY',
    $supplierResponse
);

// 使用状态检查器判断是否已支付
$statusChecker = StatusCheckerFactory::create($channel->interface_code);
$isPaid = $statusChecker->isPaid($result);

echo "状态检查器: " . get_class($statusChecker) . "\n";
echo "供应商订单是否已支付: " . ($isPaid ? '是' : '否') . "\n";

if ($isPaid) {
    echo "开始更新订单状态...\n";
    
    // 更新订单状态为支付成功
    $oldStatus = $order->status;
    $oldPaidTime = $order->paid_time;
    $oldThirdPartyOrderNo = $order->third_party_order_no;
    
    $order->status = 3; // 支付成功
    $order->paid_time = '2025-10-11 14:24:37'; // 使用供应商的支付时间
    $order->third_party_order_no = '2025101114243763916'; // 使用供应商的订单号
    $order->updated_at = date('Y-m-d H:i:s');
    $order->save();
    
    echo "订单状态更新成功:\n";
    echo "  旧状态: $oldStatus -> 新状态: {$order->status}\n";
    echo "  旧支付时间: $oldPaidTime -> 新支付时间: {$order->paid_time}\n";
    echo "  旧第三方订单号: $oldThirdPartyOrderNo -> 新第三方订单号: {$order->third_party_order_no}\n";
} else {
    echo "供应商订单未支付，无需更新\n";
}
