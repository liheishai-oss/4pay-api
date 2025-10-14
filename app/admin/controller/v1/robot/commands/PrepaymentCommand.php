<?php

namespace app\admin\controller\v1\robot\commands;

use app\model\Supplier;
use app\model\SupplierAdmin;
use app\service\supplier\SupplierBalanceService;
use support\Db;
use support\Log;

/**
 * 预存款管理命令
 * 格式：/预付 金额表达式 或 /下发 金额表达式
 */
class PrepaymentCommand implements TelegramCommandInterface
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

        Log::info('执行预存款管理命令', [
            'message_text' => $messageText,
            'group_id' => $groupId,
            'sender_id' => $senderId
        ]);

        try {
            // 解析命令和金额表达式
            $parsedCommand = $this->parseCommand($messageText);
            if (!$parsedCommand) {
                return [
                    'success' => false,
                    'message' => '❌ 命令格式错误，请使用：/预付 金额表达式 或 /下发 金额表达式',
                    'error_code' => 'INVALID_COMMAND_FORMAT'
                ];
            }

            // 计算金额（以元为单位）
            $amountYuan = $this->calculateAmount($parsedCommand['expression']);
            if ($amountYuan === false) {
                return [
                    'success' => false,
                    'message' => '❌ 金额计算错误，请检查表达式格式',
                    'error_code' => 'CALCULATION_ERROR'
                ];
            }

            // 转换为分
            $amount = (int) round($amountYuan * 100);

            // 校验管理员权限
            $permissionResult = $this->checkAdminPermission($groupId, $senderId);
            if (!$permissionResult['success']) {
                return $permissionResult;
            }

            $supplier = $permissionResult['supplier'];

            // 执行预存款操作
            $result = $this->executePrepaymentOperation($supplier, $parsedCommand['type'], $amount, $message);

            Log::info('预存款操作完成', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'operation_type' => $parsedCommand['type'],
                'amount' => $amount,
                'new_balance' => $result['new_balance']
            ]);

            return [
                'success' => true,
                'message' => $result['message']
            ];

        } catch (\Exception $e) {
            Log::error('预存款操作失败', [
                'message_text' => $messageText,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "❌ 操作失败：{$e->getMessage()}",
                'error_code' => 'OPERATION_FAILED'
            ];
        }
    }

    /**
     * 解析命令
     */
    private function parseCommand(string $messageText): ?array
    {
        // 匹配格式：/预付 表达式 或 /下发 表达式
        if (preg_match('/^\/(预付|下发)\s+(.+)$/', trim($messageText), $matches)) {
            return [
                'type' => $matches[1],
                'expression' => $matches[2]
            ];
        }

        return null;
    }

    /**
     * 计算金额表达式
     */
    private function calculateAmount(string $expression): float|false
    {
        try {
            // 移除所有空格
            $expression = str_replace(' ', '', $expression);
            
            // 验证表达式只包含数字、+、-、*、/、.、()
            if (!preg_match('/^[0-9+\-*\/.()]+$/', $expression)) {
                return false;
            }

            // 安全的数学表达式计算
            $result = eval("return {$expression};");
            
            // 确保结果是数字
            if (!is_numeric($result)) {
                return false;
            }

            return (float) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 校验管理员权限
     */
    private function checkAdminPermission(?int $groupId, ?int $senderId): array
    {
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

            // 检查发送者是否为该供应商的管理员
            if (!$senderId) {
                return [
                    'success' => false,
                    'message' => '❌ 无法获取发送者信息',
                    'error_code' => 'NO_SENDER_ID'
                ];
            }

            // 通过TelegramAdmin关联表查询管理员关系
            $telegramAdmin = \app\model\TelegramAdmin::where('telegram_id', $senderId)->first();
            if (!$telegramAdmin) {
                return [
                    'success' => false,
                    'message' => '❌ 您不是系统管理员',
                    'error_code' => 'NOT_TELEGRAM_ADMIN'
                ];
            }

            $isAdmin = \app\model\SupplierAdmin::where('supplier_id', $supplier->id)
                ->where('telegram_user_id', $telegramAdmin->id)
                ->exists();

            if (!$isAdmin) {
                return [
                    'success' => false,
                    'message' => "❌ 您不是供应商（{$supplier->supplier_name}）的管理员",
                    'error_code' => 'NOT_SUPPLIER_ADMIN'
                ];
            }

            Log::info('预存款管理员权限校验通过', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'sender_id' => $senderId,
                'telegram_admin_id' => $telegramAdmin->id
            ]);

            return [
                'success' => true,
                'supplier' => $supplier
            ];

        } catch (\Exception $e) {
            Log::error('预存款权限校验异常', [
                'group_id' => $groupId,
                'sender_id' => $senderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '❌ 权限校验异常',
                'error_code' => 'PERMISSION_CHECK_ERROR'
            ];
        }
    }

    /**
     * 执行预存款操作（使用余额服务记录变动）
     */
    private function executePrepaymentOperation(Supplier $supplier, string $type, int $amount, array $message): array
    {
        try {
            $balanceService = new SupplierBalanceService();
            
            // 获取操作人信息
            $operatorInfo = $this->getOperatorInfo($message);
            
            if ($type === '预付') {
                // 增加预存款
                $log = $balanceService->addPrepayment(
                    $supplier->id,
                    $amount,
                    $operatorInfo,
                    "Telegram机器人操作：{$message['message_text']}",
                    json_encode($message, JSON_UNESCAPED_UNICODE)
                );
                
                $message = "✅ 预存款增加成功\n\n";
                $message .= "🏢 供应商：{$supplier->supplier_name}\n";
                $message .= "💰 增加金额：¥" . number_format($amount / 100, 2) . "\n";
                $message .= "📊 原余额：¥" . number_format($log->balance_before / 100, 2) . "\n";
                $message .= "📊 新余额：¥" . number_format($log->balance_after / 100, 2) . "\n";
                $message .= "📝 记录ID：{$log->id}";
                
            } else {
                // 扣除预存款
                $log = $balanceService->withdrawPrepayment(
                    $supplier->id,
                    $amount,
                    $operatorInfo,
                    "Telegram机器人操作：{$message['message_text']}",
                    json_encode($message, JSON_UNESCAPED_UNICODE)
                );
                
                $message = "✅ 预存款扣除成功\n\n";
                $message .= "🏢 供应商：{$supplier->supplier_name}\n";
                $message .= "💰 扣除金额：¥" . number_format($amount / 100, 2) . "\n";
                $message .= "📊 原余额：¥" . number_format($log->balance_before / 100, 2) . "\n";
                $message .= "📊 新余额：¥" . number_format($log->balance_after / 100, 2) . "\n";
                $message .= "📝 记录ID：{$log->id}";
            }

            return [
                'new_balance' => $log->balance_after,
                'message' => $message,
                'log_id' => $log->id
            ];

        } catch (\Exception $e) {
            Log::error('预存款操作失败', [
                'supplier_id' => $supplier->id,
                'operation_type' => $type,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取操作人信息
     */
    private function getOperatorInfo(array $message): array
    {
        $senderId = isset($message['sender_id']) ? $message['sender_id'] : null;
        $operatorName = isset($message['first_name']) ? $message['first_name'] : 'Unknown';
        
        // 获取Telegram管理员信息
        $telegramAdmin = \app\model\TelegramAdmin::where('telegram_id', $senderId)->first();
        
        return [
            'operator_id' => $telegramAdmin ? $telegramAdmin->id : null,
            'operator_name' => $operatorName,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Telegram Bot'
        ];
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return 'prepayment';
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '/预付 金额表达式 - 增加预存款，/下发 金额表达式 - 扣除预存款';
    }

    /**
     * 检查消息是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return preg_match('/^\/(预付|下发)\s+.+$/', trim($messageText));
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
