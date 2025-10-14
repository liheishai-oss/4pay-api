<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\common\helpers\SupplierCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class StatusSwitchService
{
    /**
     * 切换供应商状态
     * @param int $id
     * @param int $status
     * @return Supplier
     * @throws MyBusinessException
     */
    public function toggleStatus(int $id, int $status): Supplier
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }

        // 验证状态值
        if (!in_array($status, [Supplier::STATUS_DISABLED, Supplier::STATUS_ENABLED])) {
            throw new MyBusinessException('无效的状态值');
        }

        try {
            Db::beginTransaction();

            $supplier->status = $status;
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
            throw new MyBusinessException('状态切换失败：' . $e->getMessage());
        }
    }
}






