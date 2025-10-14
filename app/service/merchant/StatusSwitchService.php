<?php

namespace app\service\merchant;

use app\exception\MyBusinessException;
use app\model\Merchant;
use app\common\helpers\MerchantCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class StatusSwitchService
{
    /**
     * 切换商户状态
     * @param int $id
     * @return Merchant
     * @throws MyBusinessException
     */
    public function switchStatus(int $id): Merchant
    {
        $merchant = Merchant::find($id);

        if (!$merchant) {
            throw new MyBusinessException('商户不存在1');
        }

        try {
            Db::beginTransaction();

            // 切换状态
            $merchant->status = $merchant->status == Merchant::STATUS_ENABLED 
                ? Merchant::STATUS_DISABLED 
                : Merchant::STATUS_ENABLED;
            $merchant->save();

            // 逐个清除商户相关缓存
            if (!empty($merchant->merchant_key)) {
                MerchantCacheHelper::clearMerchantsCacheIndividually([$merchant->merchant_key]);
            }

            // 清理订单处理专用缓存（商户信息 - 相对稳定）
            $merchantCacheKeys = [
                CacheKeys::getMerchantInfo($merchant->id),
            ];
            MultiLevelCacheHelper::clearOrderMerchantBatch($merchantCacheKeys);
            
            // 清理商户订单号检查缓存（商户信息 - 相对稳定）
            MultiLevelCacheHelper::clearOrderMerchantByPattern('*merchant_order_no:' . $merchant->id . '*');

            Db::commit();

            return $merchant;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('切换状态失败：' . $e->getMessage());
        }
    }
}




