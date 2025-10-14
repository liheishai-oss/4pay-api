<?php

namespace app\service\thirdparty_payment\status;

use app\service\thirdparty_payment\PaymentResult;

/**
 * 百易支付状态检查器
 */
class BaiyiStatusChecker implements StatusCheckerInterface
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
        
        // 百易支付：status = 1 表示支付成功，0表示未支付
        $status = $data['status'] ?? $rawResponse['status'] ?? '';
        return (int)$status === 1;
    }
    
    /**
     * 获取供应商代码
     * @return string
     */
    public function getInterfaceCode(): string
    {
        return 'baiyi';
    }
}



