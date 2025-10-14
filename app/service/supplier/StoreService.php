<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\model\SupplierAdmin;
use app\service\supplier\TelegramNotificationService;
use support\Db;

class StoreService
{
    /**
     * 创建供应商
     * @param array $data
     * @return Supplier
     * @throws MyBusinessException
     */
    public function createSupplier(array $data): Supplier
    {
        // 检查供应商名称是否已存在
        if (Supplier::where('supplier_name', $data['supplier_name'])->exists()) {
            throw new MyBusinessException('供应商名称已存在');
        }

        // 检查接口代码是否已存在
        if (Supplier::where('interface_code', $data['interface_code'])->exists()) {
            throw new MyBusinessException('接口代码已存在');
        }

        try {
            Db::beginTransaction();

            $supplier = new Supplier();
            $supplier->supplier_name = $data['supplier_name'];
            $supplier->interface_code = $data['interface_code'];
            $supplier->status = $data['status'] ?? Supplier::STATUS_ENABLED;
            $supplier->prepayment_check = $data['prepayment_check'] ?? Supplier::PREPAY_CHECK_NOT_REQUIRED;
            $supplier->remark = $data['remark'] ?? '';
            $supplier->telegram_chat_id = $data['telegram_chat_id'] ?? 0;
            // 处理白名单IP，将数组转换为字符串格式
            if (isset($data['callback_whitelist_ips'])) {
                if (is_array($data['callback_whitelist_ips'])) {
                    // 过滤空值和无效IP
                    $validIPs = array_filter($data['callback_whitelist_ips'], function($ip) {
                        return !empty(trim($ip));
                    });
                    $supplier->callback_whitelist_ips = implode('|', $validIPs);
                } else {
                    $supplier->callback_whitelist_ips = $data['callback_whitelist_ips'] ?? '';
                }
            } else {
                $supplier->callback_whitelist_ips = '';
            }
            $supplier->save();

            // 处理供应商管理员关联
            if (isset($data['telegram_chat_ids']) && is_array($data['telegram_chat_ids'])) {
                foreach ($data['telegram_chat_ids'] as $adminId) {
                    if ($adminId > 0) {
                        SupplierAdmin::create([
                            'supplier_id' => $supplier->id,
                            'telegram_user_id' => $adminId
                        ]);
                    }
                }
            }

            Db::commit();

            // 发送Telegram通知
            $this->sendTelegramNotification($supplier, $data);

            return $supplier;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('创建供应商失败：' . $e->getMessage());
        }
    }

    /**
     * 发送Telegram通知
     * @param Supplier $supplier
     * @param array $data
     */
    private function sendTelegramNotification(Supplier $supplier, array $data): void
    {
        try {
            $notificationService = new TelegramNotificationService();

            // 发送到群组（如果指定了群组ID）
            $notificationService->sendToGroup($supplier);
           
            // 发送给所有绑定的管理员
            $supplierAdmins = SupplierAdmin::with('telegramAdmin')
                ->where('supplier_id', $supplier->id)
                ->get();
            
            foreach ($supplierAdmins as $supplierAdmin) {
                if ($supplierAdmin->telegramAdmin) {
                    $notificationService->sendSupplierBindingNotification($supplier, $supplierAdmin->telegramAdmin);
                }
            }


        } catch (\Exception $e) {
            // 通知发送失败不影响主流程，只记录日志
            \support\Log::error('发送供应商绑定通知失败', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
