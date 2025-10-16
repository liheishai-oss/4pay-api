<?php

namespace app\service\merchant;

use app\exception\MyBusinessException;
use app\model\Merchant;
use app\model\Admin;
use app\common\helpers\MerchantCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;
use support\Db;

class EditService
{
    /**
     * 更新商户
     * @param int $id
     * @param array $data
     * @return Merchant
     * @throws MyBusinessException
     */
    public function updateMerchant(int $id, array $data): Merchant
    {
        $merchant = Merchant::find($id);

        if (!$merchant) {
            throw new MyBusinessException('商户不存在12');
        }

        // 检查登录账号是否已存在（排除自己）
        if (isset($data['login_account']) && 
            Merchant::where('login_account', $data['login_account'])
                   ->where('id', '!=', $id)
                   ->exists()) {
            throw new MyBusinessException('登录账号已存在');
        }

        // 检查商户名称是否已存在（排除自己）
        if (isset($data['merchant_name']) && 
            Merchant::where('merchant_name', $data['merchant_name'])
                   ->where('id', '!=', $id)
                   ->exists()) {
            throw new MyBusinessException('商户名称已存在');
        }

        // 检查管理员是否存在
        if (!empty($data['admin_id']) && !Admin::find($data['admin_id'])) {
            throw new MyBusinessException('管理员不存在');
        }

        try {
            Db::beginTransaction();

            // 更新商户信息
            if (isset($data['login_account'])) {
                $merchant->login_account = $data['login_account'];
            }
            if (isset($data['merchant_name'])) {
                $merchant->merchant_name = $data['merchant_name'];
            }
            if (isset($data['withdrawable_amount'])) {
                $merchant->withdrawable_amount = $data['withdrawable_amount'];
            }
            if (isset($data['frozen_amount'])) {
                $merchant->frozen_amount = $data['frozen_amount'];
            }
            if (isset($data['prepayment_total'])) {
                $merchant->prepayment_total = $data['prepayment_total'];
            }
            if (isset($data['prepayment_remaining'])) {
                $merchant->prepayment_remaining = $data['prepayment_remaining'];
            }
            if (isset($data['status'])) {
                $merchant->status = $data['status'];
            }
            if (isset($data['admin_id'])) {
                $merchant->admin_id = $data['admin_id'];
            }
            if (isset($data['merchant_key'])) {
                $merchant->merchant_key = $data['merchant_key'];
            }
            if (isset($data['merchant_secret'])) {
                $merchant->merchant_secret = $data['merchant_secret'];
            }
            if (isset($data['whitelist_ips'])) {
                // 将|分隔的字符串转换为数组
                $whitelistIps = is_string($data['whitelist_ips']) 
                    ? array_filter(array_map('trim', explode('|', $data['whitelist_ips'])))
                    : $data['whitelist_ips'];
                $merchant->whitelist_ips = implode('|', $whitelistIps);
            }

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
            throw new MyBusinessException('更新商户失败：' . $e->getMessage());
        }
    }
}




