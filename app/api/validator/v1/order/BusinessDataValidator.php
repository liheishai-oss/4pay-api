<?php

namespace app\api\validator\v1\order;

use app\enums\MerchantStatus;
use app\exception\MyBusinessException;

class BusinessDataValidator
{
    /**
     * 数据验证 - 验证业务数据
     * @param array $data
     * @param object $merchant
     * @param object $order
     * @throws MyBusinessException
     */
    public function validate(array $data, object $merchant, object $order): void
    {
        // 验证商户状态
        if ($merchant->status != MerchantStatus::ENABLED) {
            throw new MyBusinessException('商户已禁用');
        }

        // 验证订单是否属于该商户
        if ($order->merchant_id != $merchant->id) {
            throw new MyBusinessException('订单不存在');
        }

        // 验证签名
        $signatureHelper = new \app\common\helpers\SignatureHelper();
        $isValid = $signatureHelper->verify($data, $merchant->merchant_secret);

        if (!$isValid) {
//            throw new MyBusinessException('签名验证失败');
        }
    }
}
