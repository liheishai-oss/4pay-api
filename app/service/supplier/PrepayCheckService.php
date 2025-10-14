<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\common\helpers\SupplierCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class PrepayCheckService
{
    /**
     * 切换预付检验状态
     * @param int $id
     * @param int $prepaymentCheck
     * @return Supplier
     * @throws MyBusinessException
     */
    public function togglePrepayCheck(int $id, int $prepaymentCheck): Supplier
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }

        // 验证预付检验状态值
        if (!in_array($prepaymentCheck, [Supplier::PREPAY_CHECK_NOT_REQUIRED, Supplier::PREPAY_CHECK_REQUIRED])) {
            throw new MyBusinessException('无效的预付检验状态值');
        }

        try {
            Db::beginTransaction();

            $supplier->prepayment_check = $prepaymentCheck;
            $supplier->save();

            // 清除供应商相关缓存
            SupplierCacheHelper::clearSupplierAllCache($supplier->id);
            
            // 清除相关通道缓存
            $channels = \app\model\PaymentChannel::where('supplier_id', $supplier->id)->get();
            foreach ($channels as $channel) {
                ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
            }

            Db::commit();

            return $supplier;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('预付检验状态切换失败：' . $e->getMessage());
        }
    }
}






