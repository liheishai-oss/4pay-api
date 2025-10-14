<?php

namespace app\service\thirdparty_payment\status;

use app\service\thirdparty_payment\PaymentResult;

/**
 * 海豚支付状态检查器
 */
class HaitunStatusChecker implements StatusCheckerInterface
{
    /**
     * 检查订单是否已支付
     * @param PaymentResult $result
     * @return bool
     */
    public function isPaid(PaymentResult $result): bool
    {
        $data = $result->getData();
        $rawResponse = $result->getRawResponse();
        
        // 海豚支付：payment_status = '3' 表示支付成功
        $paymentStatus = $data['payment_status'] ?? $rawResponse['payment_status'] ?? '';
        return $paymentStatus === '3';
    }
    
    /**
     * 获取供应商代码
     * @return string
     */
    public function getInterfaceCode(): string
    {
        return 'haitun';
    }
}



