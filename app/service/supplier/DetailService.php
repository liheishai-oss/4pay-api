<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\model\SupplierAdmin;

class DetailService
{
    /**
     * 获取供应商详情
     * @param int $id
     * @return array|mixed[]
     * @throws MyBusinessException
     */
    public function getSupplierDetail(int $id): array
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }

        // 获取供应商绑定的管理员ID列表
        $telegramChatIds = SupplierAdmin::where('supplier_id', $id)
            ->pluck('telegram_user_id')
            ->toArray();

        // 将供应商数据转换为数组
        $supplierData = $supplier->toArray();
        
        // 处理回调白名单IP字段
        if (empty($supplierData['callback_whitelist_ips'])) {
            $supplierData['callback_whitelist_ips'] = [];
        } else {
            // 将字符串格式的IP列表转换为数组
            $ipString = trim($supplierData['callback_whitelist_ips'], '"');
            if (strpos($ipString, '|') !== false) {
                // 按|分割并过滤空值
                $supplierData['callback_whitelist_ips'] = array_filter(explode('|', $ipString));
            } else {
                // 单个IP
                $supplierData['callback_whitelist_ips'] = [$ipString];
            }
        }
        
        // 添加绑定的管理员ID列表
        $supplierData['telegram_chat_ids'] = $telegramChatIds;

        return $supplierData;
    }
}




