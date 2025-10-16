<?php

namespace app\service\merchant;

use app\exception\MyBusinessException;
use app\model\Merchant;
use app\model\Admin;
use app\common\helpers\MerchantCacheHelper;
use support\Db;

class StoreService
{
    /**
     * 创建商户
     * @param array $data
     * @return Merchant
     * @throws MyBusinessException
     */
    public function createMerchant(array $data): Merchant
    {
        // 检查登录账号是否已存在
        if (Merchant::where('login_account', $data['login_account'])->exists()) {
            throw new MyBusinessException('登录账号已存在');
        }

        // 检查商户名称是否已存在
        if (isset($data['merchant_name']) && Merchant::where('merchant_name', $data['merchant_name'])->exists()) {
            throw new MyBusinessException('商户名称已存在');
        }

        try {
            Db::beginTransaction();

            // 自动创建管理员
            $admin = $this->createAdmin($data);

            // 创建商户
            $merchant = new Merchant();
            $merchant->login_account = $data['login_account'];
            $merchant->merchant_name = $data['merchant_name'] ?? $data['login_account']; // 如果没有提供商户名称，使用登录账号
            $merchant->status = $data['status'] ?? Merchant::STATUS_ENABLED;
            $merchant->admin_id = $admin ? $admin->id : null;
            $merchant->merchant_key = $this->generateMerchantKey();
            $merchant->merchant_secret = $this->generateMerchantSecret();
            // 将|分隔的字符串转换为数组
            $whitelistIps = isset($data['whitelist_ips']) && is_string($data['whitelist_ips'])
                ? array_filter(array_map('trim', explode('|', $data['whitelist_ips'])))
                : ($data['whitelist_ips'] ?? []);
            $merchant->whitelist_ips = implode('|', $whitelistIps);
            $merchant->save();

            // 逐个清除所有商户缓存（新创建商户时清除所有缓存以确保数据一致性）
            MerchantCacheHelper::clearAllMerchantCacheIndividually();

            Db::commit();

            return $merchant;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('创建商户失败：' . $e->getMessage());
        }
    }

    /**
     * 生成商户Key
     * @return string
     */
    private function generateMerchantKey(): string
    {
        return 'MCH_' . strtoupper(uniqid()) . '_' . date('Ymd');
    }

    /**
     * 生成商户密钥
     * @return string
     */
    private function generateMerchantSecret(): string
    {
        return bin2hex(random_bytes(32));
    }


    /**
     * 自动创建管理员
     * @param array $data
     * @return Admin
     * @throws MyBusinessException
     */
    private function createAdmin(array $data): Admin
    {
        try {
            // 直接使用商户登录账号作为管理员账号
            $adminUsername = $data['login_account'];
            
            // 检查管理员账号是否已存在，如果存在则添加数字后缀
            $originalUsername = $adminUsername;
            $counter = 1;
            while (Admin::where('username', $adminUsername)->exists()) {
                $adminUsername = $originalUsername . '_' . $counter;
                $counter++;
            }
            
            // 使用商户名称作为管理员昵称，如果没有提供商户名称则使用登录账号
            $adminNickname = $data['merchant_name'] ?? $data['login_account'];
            
            $admin = new Admin();
            $admin->username = $adminUsername;
            $admin->password = password_hash('123456', PASSWORD_DEFAULT); // 默认密码
            $admin->nickname = $adminNickname; // 昵称使用商户名称
            $admin->group_id = 3; // 分配分组id=3
            $admin->status = 1;
            $admin->save();
            
            return $admin;
        } catch (\Exception $e) {
            throw new MyBusinessException('创建管理员失败：' . $e->getMessage());
        }
    }
}
