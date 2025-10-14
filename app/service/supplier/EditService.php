<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\common\helpers\SupplierCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class EditService
{
    /**
     * 更新供应商
     * @param int $id
     * @param array $data
     * @return Supplier
     * @throws MyBusinessException
     */
    public function updateSupplier(int $id, array $data): Supplier
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }

        // 检查供应商名称是否已存在（排除当前记录）
        if (isset($data['supplier_name']) && 
            Supplier::where('supplier_name', $data['supplier_name'])
                   ->where('id', '!=', $id)
                   ->exists()) {
            throw new MyBusinessException('供应商名称已存在');
        }

        // 检查接口代码是否已存在（排除当前记录）
        if (isset($data['interface_code']) && 
            Supplier::where('interface_code', $data['interface_code'])
                   ->where('id', '!=', $id)
                   ->exists()) {
            throw new MyBusinessException('接口代码已存在');
        }

        try {
            Db::beginTransaction();

            // 更新字段
            if (isset($data['supplier_name'])) {
                $supplier->supplier_name = $data['supplier_name'];
            }
            if (isset($data['interface_code'])) {
                $supplier->interface_code = $data['interface_code'];
            }
            if (isset($data['status'])) {
                $supplier->status = $data['status'];
            }
            if (isset($data['prepayment_check'])) {
                $supplier->prepayment_check = $data['prepayment_check'];
            }
            if (isset($data['remark'])) {
                $supplier->remark = $data['remark'];
            }
            if (isset($data['telegram_chat_id'])) {
                $supplier->telegram_chat_id = $data['telegram_chat_id'];
            }
            if (isset($data['callback_whitelist_ips'])) {
                // 处理白名单IP，将数组转换为字符串格式
                if (is_array($data['callback_whitelist_ips'])) {
                    // 过滤空值和无效IP
                    $validIPs = array_filter($data['callback_whitelist_ips'], function($ip) {
                        return !empty(trim($ip));
                    });
                    $supplier->callback_whitelist_ips = implode('|', $validIPs);
                } else {
                    $supplier->callback_whitelist_ips = $data['callback_whitelist_ips'] ?? '';
                }
            }

            $supplier->save();

            // 清除供应商相关缓存
            SupplierCacheHelper::clearSupplierAllCache($supplier->id);
            
            // 清除相关通道缓存（如果供应商有通道）
            $channels = \app\model\PaymentChannel::where('supplier_id', $supplier->id)->get();
            foreach ($channels as $channel) {
                ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
            }

            Db::commit();

            return $supplier;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('更新供应商失败：' . $e->getMessage());
        }
    }
}
