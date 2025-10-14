<?php

namespace app\admin\controller\v1\robot\commands;

use app\model\Supplier;
use app\model\SupplierAdmin;
use app\model\Order;
use app\model\PaymentChannel;
use support\Db;
use support\Log;

/**
 * 成功率统计命令
 * 格式：/成功率
 */
class SuccessRateCommand implements TelegramCommandInterface
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

        Log::info('执行成功率统计命令', [
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

            // 获取该供应商的所有通道
            $channels = PaymentChannel::where('supplier_id', $supplier->id)
                ->where('status', 1)
                ->get();

            $message = "📈 成功率统计报告\n\n";
            $message .= "🏢 供应商：{$supplier->supplier_name}\n";
            $message .= "⏰ 统计时间：" . date('Y-m-d H:i:s') . "\n\n";

            // 获取不同时间段的统计
        $timeRanges = [
            '1分钟' => 1,
            '3分钟' => 3,
            '5分钟' => 5,
            '10分钟' => 10,
            '全天' => null
        ];

        foreach ($timeRanges as $rangeName => $minutes) {
            $message .= "📊 {$rangeName}统计\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            $totalOrders = 0;
            $totalSuccessOrders = 0;

            // 按通道统计
            foreach ($channels as $channel) {
                $channelStats = $this->getChannelStats($channel->id, $minutes);
                
                // 根据成功率选择表情符号
                $successEmoji = $channelStats['success_rate'] >= 90 ? '🟢' : 
                               ($channelStats['success_rate'] >= 70 ? '🟡' : '🔴');
                
                $message .= "   {$successEmoji} {$channel->channel_name}\n";
                $message .= "      📋 订单数：{$channelStats['total_orders']}\n";
                $message .= "      ✅ 成功数：{$channelStats['success_orders']}\n";
                $message .= "      📈 成功率：{$successEmoji} " . number_format($channelStats['success_rate'], 2) . "%\n";
                $message .= "      " . str_repeat('█', min(20, intval($channelStats['success_rate'] / 5))) . 
                           str_repeat('░', max(0, 20 - intval($channelStats['success_rate'] / 5))) . "\n\n";

                $totalOrders += $channelStats['total_orders'];
                $totalSuccessOrders += $channelStats['success_orders'];
            }

            // 总体统计
            $overallSuccessRate = $totalOrders > 0 ? ($totalSuccessOrders / $totalOrders) * 100 : 0;
            $overallEmoji = $overallSuccessRate >= 90 ? '🟢' : 
                           ($overallSuccessRate >= 70 ? '🟡' : '🔴');
            
            $message .= "   🎯 总体统计\n";
            $message .= "      📋 总订单：{$totalOrders}\n";
            $message .= "      ✅ 总成功：{$totalSuccessOrders}\n";
            $message .= "      📈 总成功率：{$overallEmoji} " . number_format($overallSuccessRate, 2) . "%\n";
            $message .= "      " . str_repeat('█', min(20, intval($overallSuccessRate / 5))) . 
                       str_repeat('░', max(0, 20 - intval($overallSuccessRate / 5))) . "\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }

            Log::info('成功率统计成功', [
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
            Log::error('成功率统计失败', [
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
    private function getChannelStats(int $channelId, ?int $minutes = null): array
    {
        $query = Order::where('channel_id', $channelId);
        
        // 如果指定了分钟数，则添加时间条件
        if ($minutes !== null) {
            $startTime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            $query->where('created_at', '>=', $startTime);
        } else {
            // 如果是全天统计，只统计当天的数据
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $query->whereBetween('created_at', [$todayStart, $todayEnd]);
        }

        // 总订单数
        $totalOrders = $query->count();

        // 成功订单数
        $successOrders = $query->where('status', 3)->count(); // 支付成功

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
        return 'success_rate';
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '/查成功率 - 查看成功率统计报告';
    }

    /**
     * 检查消息是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return preg_match('/^\/查成功率$/', trim($messageText));
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
