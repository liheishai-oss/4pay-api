<?php

namespace app\service\thirdparty_payment\status;

use app\service\thirdparty_payment\PaymentResult;
use support\Log;

/**
 * 默认状态检查器（通用逻辑）
 */
class DefaultStatusChecker implements StatusCheckerInterface
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
        
        // 通用判断：检查常见的成功状态字段
        $successFields = [
            'status' => ['success', 'paid', 'completed', '3', '1'],
            'payment_status' => ['success', 'paid', 'completed', '3', '1'],
            'trade_status' => ['TRADE_SUCCESS', 'SUCCESS', 'PAID'],
            'order_status' => ['success', 'paid', 'completed', '3', '1']
        ];
        
        foreach ($successFields as $field => $successValues) {
            $fieldValue = $data[$field] ?? $rawResponse[$field] ?? '';
            if (in_array($fieldValue, $successValues)) {
                Log::info('通过通用字段判断订单已支付', [
                    'field' => $field,
                    'value' => $fieldValue,
                    'success_values' => $successValues
                ]);
                return true;
            }
        }
        
        // 如果都没有匹配，记录详细信息用于调试
        Log::info('未找到支付成功状态字段', [
            'data' => $data,
            'raw_response' => $rawResponse
        ]);
        
        return false;
    }
    
    /**
     * 获取供应商代码
     * @return string
     */
    public function getInterfaceCode(): string
    {
        return 'default';
    }
}



