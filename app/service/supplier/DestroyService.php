<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\common\helpers\SupplierCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class DestroyService
{
    /**
     * 删除供应商
     * @param array $ids
     * @return bool
     * @throws MyBusinessException
     */
    public function deleteSuppliers(array $ids): bool
    {
        if (empty($ids)) {
            throw new MyBusinessException('请选择要删除的供应商');
        }

        try {
            Db::beginTransaction();

            // 检查供应商是否存在
            $suppliers = Supplier::whereIn('id', $ids)->get();
            if ($suppliers->count() !== count($ids)) {
                throw new MyBusinessException('部分供应商不存在');
            }

            // 清除供应商相关缓存
            foreach ($suppliers as $supplier) {
                SupplierCacheHelper::clearSupplierAllCache($supplier->id);
                
                // 清除相关通道缓存
                $channels = \app\model\PaymentChannel::where('supplier_id', $supplier->id)->get();
                foreach ($channels as $channel) {
                    ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
                }
            }

            // 删除供应商
            $deletedCount = Supplier::whereIn('id', $ids)->delete();

            Db::commit();

            return $deletedCount > 0;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('删除供应商失败：' . $e->getMessage());
        }
    }
}






