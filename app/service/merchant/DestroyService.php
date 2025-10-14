<?php

namespace app\service\merchant;

use app\exception\MyBusinessException;
use app\model\Merchant;
use app\model\Admin;
use app\common\helpers\MerchantCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class DestroyService
{
    /**
     * 删除商户（单个或批量）
     * @param array $ids
     * @return bool
     * @throws MyBusinessException
     */
    public function deleteMerchants(array $ids): bool
    {
        if (empty($ids)) {
            throw new MyBusinessException('请选择要删除的数据');
        }

        $merchants = Merchant::whereIn('id', $ids)->get();

        if ($merchants->isEmpty()) {
            throw new MyBusinessException('商户不存在9');
        }

        try {
            Db::beginTransaction();

            // 收集所有需要删除的管理员ID
            $adminIds = $merchants->pluck('admin_id')->filter()->toArray();

            // 删除关联的管理员
            if (!empty($adminIds)) {
                Admin::whereIn('id', $adminIds)->delete();
            }

            // 删除商户
            Merchant::whereIn('id', $ids)->delete();

            // 逐个清除被删除商户的缓存
            $merchantKeys = $merchants->pluck('merchant_key')->filter()->toArray();
            if (!empty($merchantKeys)) {
                MerchantCacheHelper::clearMerchantsCacheIndividually($merchantKeys);
            }

            // 清理订单处理专用缓存（商户信息 - 相对稳定）
            $merchantCacheKeys = [];
            foreach ($merchants as $merchant) {
                $merchantCacheKeys[] = CacheKeys::getMerchantInfo($merchant->id);
            }
            
            if (!empty($merchantCacheKeys)) {
                MultiLevelCacheHelper::clearOrderMerchantBatch($merchantCacheKeys);
            }
            
            // 清理商户订单号检查缓存（商户信息 - 相对稳定）
            foreach ($merchants as $merchant) {
                MultiLevelCacheHelper::clearOrderMerchantByPattern('*merchant_order_no:' . $merchant->id . '*');
            }

            Db::commit();

            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('删除商户失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除商户
     * @param array $ids
     * @return int
     * @throws MyBusinessException
     */
    public function batchDeleteMerchants(array $ids): int
    {
        if (empty($ids)) {
            throw new MyBusinessException('请选择要删除的数据');
        }

        $merchants = Merchant::whereIn('id', $ids)->get();

        if ($merchants->isEmpty()) {
            throw new MyBusinessException('商户不存在10');
        }

        try {
            Db::beginTransaction();

            // 收集所有需要删除的管理员ID
            $adminIds = $merchants->pluck('admin_id')->filter()->toArray();

            // 删除关联的管理员
            if (!empty($adminIds)) {
                Admin::whereIn('id', $adminIds)->delete();
            }

            // 删除商户
            $deletedCount = Merchant::whereIn('id', $ids)->delete();

            // 逐个清除被删除商户的缓存
            $merchantKeys = $merchants->pluck('merchant_key')->filter()->toArray();
            if (!empty($merchantKeys)) {
                MerchantCacheHelper::clearMerchantsCacheIndividually($merchantKeys);
            }

            // 清理订单处理专用缓存（商户信息 - 相对稳定）
            $merchantCacheKeys = [];
            foreach ($merchants as $merchant) {
                $merchantCacheKeys[] = CacheKeys::getMerchantInfo($merchant->id);
            }
            
            if (!empty($merchantCacheKeys)) {
                MultiLevelCacheHelper::clearOrderMerchantBatch($merchantCacheKeys);
            }
            
            // 清理商户订单号检查缓存（商户信息 - 相对稳定）
            foreach ($merchants as $merchant) {
                MultiLevelCacheHelper::clearOrderMerchantByPattern('*merchant_order_no:' . $merchant->id . '*');
            }

            Db::commit();

            return $deletedCount;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('批量删除商户失败：' . $e->getMessage());
        }
    }

    /**
     * 删除单个商户（保持向后兼容）
     * @param int $id
     * @return bool
     * @throws MyBusinessException
     */
    public function deleteMerchant(int $id): bool
    {
        return $this->deleteMerchants([$id]);
    }
}




