<?php

namespace app\service\thirdparty_payment\status;

use app\service\thirdparty_payment\PaymentResult;

/**
 * 支付状态检查器接口
 */
interface StatusCheckerInterface
{
    /**
     * 检查订单是否已支付
     * @param PaymentResult $result
     * @return bool
     */
    public function isPaid(PaymentResult $result): bool;
    
    /**
     * 获取供应商代码
     * @return string
     */
    public function getInterfaceCode(): string;
}



