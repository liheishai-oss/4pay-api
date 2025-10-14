<?php

namespace app\admin\controller\v1\robot\commands;

use app\model\Supplier;
use app\service\telegram\SupplierBindService;
use support\Log;

/**
 * 供应商绑定命令
 * 处理 /绑定=供应商名称 格式的命令
 * 只有该供应商的管理员才能绑定
 */
class SupplierBindCommand implements TelegramCommandInterface
{
    private SupplierBindService $bindService;

    public function __construct()
    {
        $this->bindService = new SupplierBindService();
    }

    /**
     * 执行绑定命令
     */
    public function execute(array $message): array
    {
        $messageText = isset($message['message_text']) ? $message['message_text'] : '';
        $groupId = isset($message['group_id']) ? $message['group_id'] : null;
        $groupName = isset($message['group_name']) ? $message['group_name'] : '';
        $senderId = isset($message['sender_id']) ? $message['sender_id'] : null;

        Log::info('执行供应商绑定命令', [
            'message_text' => $messageText,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'sender_id' => $senderId
        ]);

        // 解析命令参数
        $bindInfo = $this->parseBindCommand($messageText);
        print_r($bindInfo);
        if (!$bindInfo) {
            return [
                'success' => false,
                'message' => '❌ 命令格式错误，请使用：/绑定供应商=名称 或 /绑定商户=名称',
                'error_code' => 'INVALID_FORMAT'
            ];
        }

        $bindType = $bindInfo['type']; // 'supplier' 或 'merchant'
        $name = $bindInfo['name'];

        if ($bindType === 'supplier') {
            // 处理供应商绑定
            $supplier = $this->findSupplierByName($name);
            if (!$supplier) {
                return [
                    'success' => false,
                    'message' => "❌ 供应商 '{$name}' 不存在",
                    'error_code' => 'SUPPLIER_NOT_FOUND'
                ];
            }

            // 验证发送者是否为该供应商的管理员
            if (!$this->isSupplierAdmin($supplier, $senderId)) {
                return [
                    'success' => false,
                    'message' => "❌ 您不是供应商 '{$name}' 的管理员，无法执行绑定操作",
                    'error_code' => 'PERMISSION_DENIED'
                ];
            }

            // 在执行绑定前进行重复绑定校验，直接返回用户可见提示
            if (!empty($supplier->telegram_chat_id) && (int)$supplier->telegram_chat_id === (int)$groupId) {
                return [
                    'success' => true,
                    'message' => "ℹ️ 该群组已与当前供应商绑定：{$supplier->supplier_name}",
                    'error_code' => 'ALREADY_BOUND'
                ];
            }

            $existing = \app\model\Supplier::where('telegram_chat_id', $groupId)
                ->where('id', '!=', $supplier->id)
                ->first();
            if ($existing) {
                return [
                    'success' => true,
                    'message' => "ℹ️ 该群组已绑定至供应商：{$existing->supplier_name}",
                    'error_code' => 'GROUP_ALREADY_BOUND'
                ];
            }

            // 执行供应商绑定
            try {
                $result = $this->bindService->bindSupplierToGroup($supplier, $groupId, $groupName);
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'message' => "✅ 供应商 '{$name}' 绑定成功！\n" .
                                   "📋 群组：{$groupName}\n" .
                                   "🏢 供应商：{$supplier->supplier_name}\n" .
                                   "🔗 接口类型：{$supplier->interface_code}\n" .
                                   "⏰ 绑定时间：" . date('Y-m-d H:i:s')
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => "❌ 绑定失败：{$result['message']}",
                        'error_code' => 'BIND_FAILED'
                    ];
                }
            } catch (\Exception $e) {
                Log::error('供应商绑定异常', [
                    'supplier_name' => $name,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);

                return [
                    'success' => false,
                    'message' => "❌ 绑定操作异常：{$e->getMessage()}",
                    'error_code' => 'BIND_EXCEPTION'
                ];
            }
        } else {
            // 处理商户绑定
            $merchant = $this->findMerchantByName($name);
            if (!$merchant) {
                return [
                    'success' => false,
                    'message' => "❌ 商户 '{$name}' 不存在",
                    'error_code' => 'MERCHANT_NOT_FOUND'
                ];
            }

            // 执行商户绑定
            try {
                $result = $this->bindMerchantToGroup($merchant, $groupId, $groupName);
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'message' => "✅ 商户 '{$name}' 绑定成功！\n" .
                                   "📋 群组：{$groupName}\n" .
                                   "🏪 商户：{$merchant->merchant_name}\n" .
                                   "🔑 商户ID：{$merchant->merchant_id}\n" .
                                   "⏰ 绑定时间：" . date('Y-m-d H:i:s')
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => "❌ 绑定失败：{$result['message']}",
                        'error_code' => 'BIND_FAILED'
                    ];
                }
            } catch (\Exception $e) {
                Log::error('商户绑定异常', [
                    'merchant_name' => $name,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);

                return [
                    'success' => false,
                    'message' => "❌ 绑定操作异常：{$e->getMessage()}",
                    'error_code' => 'BIND_EXCEPTION'
                ];
            }
        }
    }

    /**
     * 解析绑定命令
     */
    private function parseBindCommand(string $messageText): ?array
    {
        $messageText = trim($messageText);
        
        if (preg_match('/^\/绑定供应商=(.+)$/', $messageText, $matches)) {
            return [
                'type' => 'supplier',
                'name' => trim($matches[1])
            ];
        }
        
        if (preg_match('/^\/绑定商户=(.+)$/', $messageText, $matches)) {
            return [
                'type' => 'merchant',
                'name' => trim($matches[1])
            ];
        }
        
        return null;
    }

    /**
     * 根据名称查找供应商
     */
    private function findSupplierByName(string $supplierName): ?Supplier
    {
        return Supplier::where('supplier_name', 'like', "%{$supplierName}%")
            ->where('status', 1)
            ->first();
    }

    /**
     * 验证发送者是否为该供应商的管理员
     */
    private function isSupplierAdmin(Supplier $supplier, ?int $senderId): bool
    {
        if (!$senderId) {
            return false;
        }

        // 通过SupplierAdmin关联表查询管理员关系
        // supplier_admin.telegram_user_id 关联到 telegram_admin.id
        // 需要先找到对应的telegram_admin记录
        $telegramAdmin = \app\model\TelegramAdmin::where('telegram_id', $senderId)->first();
        if (!$telegramAdmin) {
            Log::info('未找到对应的Telegram管理员记录', [
                'sender_id' => $senderId
            ]);
            return false;
        }

        $isAdmin = \app\model\SupplierAdmin::where('supplier_id', $supplier->id)
            ->where('telegram_user_id', $telegramAdmin->id)
            ->exists();

        Log::info('验证供应商管理员权限', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'sender_id' => $senderId,
            'telegram_admin_id' => $telegramAdmin->id,
            'is_admin' => $isAdmin
        ]);

        return $isAdmin;
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return 'supplier_bind';
    }

    /**
     * 根据名称查找商户
     */
    private function findMerchantByName(string $merchantName): ?\app\model\Merchant
    {
        return \app\model\Merchant::where('merchant_name', $merchantName)->first();
    }

    /**
     * 绑定商户到群组
     */
    private function bindMerchantToGroup($merchant, $groupId, $groupName): array
    {
        try {
            // 更新商户的telegram_chat_id
            $merchant->telegram_chat_id = $groupId;
            $merchant->save();

            return [
                'success' => true,
                'message' => '商户绑定成功'
            ];
        } catch (\Exception $e) {
            Log::error('商户绑定失败', [
                'merchant_id' => $merchant->id,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '绑定失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '绑定供应商或商户到群组：/绑定供应商=名称 或 /绑定商户=名称';
    }

    /**
     * 验证命令权限
     */
    public function hasPermission(array $message): bool
    {
        return true; // 权限验证在execute方法中进行
    }

    /**
     * 检查是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return str_starts_with(trim($messageText), '/绑定供应商=') || 
               str_starts_with(trim($messageText), '/绑定商户=');
    }
}
