<?php

namespace app\service\thirdparty_payment\status;

use app\service\thirdparty_payment\PaymentResult;

/**
 * 跑分支付状态检查器
 */
class PaofenStatusChecker implements StatusCheckerInterface
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
        
        // 跑分支付：status = 'success' 表示支付成功
        $status = $data['status'] ?? $rawResponse['status'] ?? '';
        return $status === 'success';
    }
    
    /**
     * 获取供应商代码
     * @return string
     */
    public function getInterfaceCode(): string
    {
        return 'paofen';
    }
}



