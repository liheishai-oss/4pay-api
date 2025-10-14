<?php

namespace app\admin\controller\v1\robot\commands;

use app\model\Supplier;
use app\model\SupplierAdmin;
use app\model\Order;
use app\model\PaymentChannel;
use support\Db;
use support\Log;

/**
 * 结算统计命令
 * 格式：/结算
 */
class SettlementCommand implements TelegramCommandInterface
{
    /**
     * 执行命令
     */
    public function execute(array $message): array
    {
        $messageText = isset($message['message_text']) ? $message['message_text'] : '';
        $groupId = isset($message['group_id']) ? $message['group_id'] : null;
        $groupName = isset($message['group_name']) ? $message['group_name'] : '';
        $senderId = isset($message['sender_id']) ? $message['sender_id'] : null;

        Log::info('执行结算统计命令', [
            'message_text' => $messageText,
            'group_id' => $groupId,
            'sender_id' => $senderId
        ]);

        try {
            // 检查群组是否绑定了供应商
            if (!$groupId) {
                return [
                    'success' => false,
                    'message' => '❌ 无法获取群组信息',
                    'error_code' => 'NO_GROUP_ID'
                ];
            }

            // 查找绑定到当前群组的供应商
            $supplier = Supplier::where('telegram_chat_id', $groupId)->first();
            if (!$supplier) {
                return [
                    'success' => false,
                    'message' => '❌ 当前群组未绑定供应商',
                    'error_code' => 'NO_BOUND_SUPPLIER'
                ];
            }

            // 获取截止时间（当前时间）
            $endTime = date('Y-m-d H:i:s');
            
            // 获取该供应商的所有通道
            $channels = PaymentChannel::where('supplier_id', $supplier->id)
                ->where('status', 1)
                ->get();

            $message = "📊 结算统计报告\n\n";
            $message .= "🏢 供应商：{$supplier->supplier_name}\n";
            $message .= "⏰ 截止时间：{$endTime}\n\n";

            $totalOrders = 0;
            $totalSuccessOrders = 0;

            // 按通道统计
            foreach ($channels as $channel) {
                $channelStats = $this->getChannelStats($channel->id, $endTime);
                
                $message .= "📈 通道：{$channel->channel_name}\n";
                $message .= "   总订单数：{$channelStats['total_orders']}\n";
                $message .= "   成功订单数：{$channelStats['success_orders']}\n";
                $message .= "   成功率：" . number_format($channelStats['success_rate'], 2) . "%\n\n";

                $totalOrders += $channelStats['total_orders'];
                $totalSuccessOrders += $channelStats['success_orders'];
            }

            // 供应商总体统计
            $overallSuccessRate = $totalOrders > 0 ? ($totalSuccessOrders / $totalOrders) * 100 : 0;
            
            $message .= "📊 供应商总体统计\n";
            $message .= "   总订单数：{$totalOrders}\n";
            $message .= "   成功订单数：{$totalSuccessOrders}\n";
            $message .= "   成功率：" . number_format($overallSuccessRate, 2) . "%\n\n";
            $message .= "⏰ 统计时间：" . date('Y-m-d H:i:s');

            Log::info('结算统计成功', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'total_orders' => $totalOrders,
                'total_success_orders' => $totalSuccessOrders,
                'overall_success_rate' => $overallSuccessRate
            ]);

            return [
                'success' => true,
                'message' => $message
            ];

        } catch (\Exception $e) {
            Log::error('结算统计失败', [
                'message_text' => $messageText,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "❌ 统计失败：{$e->getMessage()}",
                'error_code' => 'STATISTICS_FAILED'
            ];
        }
    }

    /**
     * 获取通道统计信息
     */
    private function getChannelStats(int $channelId, string $endTime): array
    {
        // 总订单数
        $totalOrders = Order::where('channel_id', $channelId)
            ->where('created_at', '<=', $endTime)
            ->count();

        // 成功订单数
        $successOrders = Order::where('channel_id', $channelId)
            ->where('status', 3) // 支付成功
            ->where('created_at', '<=', $endTime)
            ->count();

        // 计算成功率
        $successRate = $totalOrders > 0 ? ($successOrders / $totalOrders) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'success_orders' => $successOrders,
            'success_rate' => $successRate
        ];
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return 'settlement';
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '/结算 - 查看结算统计报告';
    }

    /**
     * 检查消息是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return preg_match('/^\/结算$/', trim($messageText));
    }

    /**
     * 检查用户权限
     */
    public function hasPermission(array $message): bool
    {
        // 权限检查在execute方法中进行
        return true;
    }
}

