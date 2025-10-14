<?php

namespace app\admin\controller\v1\robot\commands;

use app\model\Supplier;
use app\model\SupplierAdmin;
use support\Log;

/**
 * 余额查询命令
 * 格式：/查余额
 */
class BalanceQueryCommand implements TelegramCommandInterface
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

        Log::info('执行余额查询命令', [
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

            // 获取当前余额信息
            $prepaymentTotal = $supplier->prepayment_total ?? 0;
            $prepaymentRemaining = $supplier->prepayment_remaining ?? 0;
            $withdrawableBalance = $supplier->withdrawable_balance ?? 0;
            $todayReceipt = $supplier->today_receipt ?? 0;

            $message = "💰 供应商余额查询\n\n";
            $message .= "🏢 供应商：{$supplier->supplier_name}\n";
            $message .= "📊 预存款总额：¥" . number_format($prepaymentTotal / 100, 2) . "\n";
            $message .= "💳 可用余额：¥" . number_format($prepaymentRemaining / 100, 2) . "\n";
            $message .= "💸 可提现余额：¥" . number_format($withdrawableBalance / 100, 2) . "\n";
            $message .= "📈 今日收款：¥" . number_format($todayReceipt / 100, 2) . "\n";
            $message .= "⏰ 查询时间：" . date('Y-m-d H:i:s');

            Log::info('余额查询成功', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'prepayment_total' => $prepaymentTotal,
                'prepayment_remaining' => $prepaymentRemaining
            ]);

            return [
                'success' => true,
                'message' => $message
            ];

        } catch (\Exception $e) {
            Log::error('余额查询失败', [
                'message_text' => $messageText,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "❌ 查询失败：{$e->getMessage()}",
                'error_code' => 'QUERY_FAILED'
            ];
        }
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return 'balance_query';
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '/查余额 - 查询供应商当前余额信息';
    }

    /**
     * 检查消息是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return preg_match('/^\/查余额$/', trim($messageText));
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

