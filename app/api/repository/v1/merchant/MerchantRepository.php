<?php

namespace app\api\repository\v1\merchant;

use app\model\Merchant;

class MerchantRepository
{
    /**
     * 根据ID获取商户信息
     * @param int $merchantId
     * @return Merchant|null
     */
    public function getMerchantById(int $merchantId): ?Merchant
    {
        return Merchant::where('id', $merchantId)->first();
    }

    /**
     * 根据登录账号获取商户信息
     * @param string $loginAccount
     * @return Merchant|null
     */
    public function getMerchantByLoginAccount(string $loginAccount): ?Merchant
    {
        return Merchant::where('login_account', $loginAccount)->first();
    }

    /**
     * 根据商户密钥获取商户信息
     * @param string $merchantKey
     * @return Merchant|null
     */
    public function getMerchantByKey(string $merchantKey): ?Merchant
    {
        return Merchant::where('merchant_key', $merchantKey)->first();
    }

    /**
     * 更新商户余额
     * @param int $merchantId
     * @param array $balanceData
     * @return bool
     */
    public function updateBalance(int $merchantId, array $balanceData): bool
    {
        return Merchant::where('id', $merchantId)->update($balanceData) > 0;
    }

    /**
     * 检查商户是否存在且启用
     * @param int $merchantId
     * @return bool
     */
    public function isMerchantActive(int $merchantId): bool
    {
        return Merchant::where('id', $merchantId)
            ->where('status', 1)
            ->exists();
    }
}
