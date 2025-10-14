<?php

namespace app\service\telegram;

use app\model\Supplier;
use support\Db;
use support\Log;

/**
 * 供应商绑定服务
 * 直接更新供应商表的telegram_chat_id字段
 */
class SupplierBindService
{
    /**
     * 绑定供应商到群组
     */
    public function bindSupplierToGroup(Supplier $supplier, int $groupId, string $groupName): array
    {
        try {
            Db::beginTransaction();

            // 如果当前供应商已绑定到同一群组，提示已绑定
            if (!empty($supplier->telegram_chat_id) && (int)$supplier->telegram_chat_id === (int)$groupId) {
                Db::rollBack();
                return [
                    'success' => false,
                    'message' => '该群组已与当前供应商绑定',
                    'error_code' => 'ALREADY_BOUND'
                ];
            }

            // 检查该群组是否已被其他供应商绑定
            $existing = Supplier::where('telegram_chat_id', $groupId)
                ->where('id', '!=', $supplier->id)
                ->first();
            if ($existing) {
                Db::rollBack();
                return [
                    'success' => false,
                    'message' => '该群组已绑定至供应商：' . $existing->supplier_name,
                    'error_code' => 'GROUP_ALREADY_BOUND'
                ];
            }

            // 检查是否已经绑定到其他群组
            if (!empty($supplier->telegram_chat_id) && $supplier->telegram_chat_id != $groupId) {
                Log::info('供应商已绑定其他群组，将更新绑定', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->supplier_name,
                    'old_chat_id' => $supplier->telegram_chat_id,
                    'new_chat_id' => $groupId
                ]);
            }

            // 更新供应商的telegram_chat_id
            $supplier->telegram_chat_id = $groupId;
            $supplier->save();

            Log::info('供应商群组绑定成功', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'chat_id' => $groupId,
                'group_name' => $groupName
            ]);

            Db::commit();

            return [
                'success' => true,
                'message' => '绑定成功',
                'supplier' => $supplier,
                'group_id' => $groupId,
                'group_name' => $groupName
            ];

        } catch (\Exception $e) {
            Db::rollback();
            
            Log::error('供应商绑定失败', [
                'supplier_id' => $supplier->id,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '绑定失败：' . $e->getMessage(),
                'error_code' => 'BIND_ERROR'
            ];
        }
    }

    /**
     * 解绑供应商群组
     */
    public function unbindSupplierFromGroup(Supplier $supplier): array
    {
        try {
            $oldChatId = $supplier->telegram_chat_id;
            $supplier->telegram_chat_id = null;
            $supplier->save();

            Log::info('供应商群组解绑成功', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'old_chat_id' => $oldChatId
            ]);

            return [
                'success' => true,
                'message' => '解绑成功'
            ];

        } catch (\Exception $e) {
            Log::error('供应商解绑失败', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '解绑失败：' . $e->getMessage(),
                'error_code' => 'UNBIND_ERROR'
            ];
        }
    }

    /**
     * 获取供应商的绑定群组信息
     */
    public function getSupplierBinding(Supplier $supplier): ?array
    {
        if (empty($supplier->telegram_chat_id)) {
            return null;
        }

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'chat_id' => $supplier->telegram_chat_id,
            'interface_code' => $supplier->interface_code
        ];
    }

    /**
     * 根据群组ID查找绑定的供应商
     */
    public function getSupplierByChatId(int $chatId): ?Supplier
    {
        return Supplier::where('telegram_chat_id', $chatId)
            ->where('status', 1)
            ->first();
    }
}
