<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\interfaces\PaymentServiceInterface;
use app\service\thirdparty_payment\PaymentResult;

/**
 * 支付服务适配器
 * 统一不同供应商的返回数据格式
 */
class PaymentServiceAdapter
{
    /**
     * 统一支付结果格式
     * @param PaymentResult $result
     * @param string $supplierName
     * @return PaymentResult
     */
    public static function normalizePaymentResult(PaymentResult $result, string $supplierName = ''): PaymentResult
    {
        $data = $result->getData();
        $rawResponse = $result->getRawResponse();
        
        // 统一的基础字段
        $normalizedData = [
            'payment_url' => self::extractPaymentUrl($data, $rawResponse),
            'payment_data' => self::extractPaymentData($data, $rawResponse),
            'supplier_name' => $supplierName,
            'supplier_response' => $rawResponse
        ];

        // 保留原有的其他字段
        $normalizedData = array_merge($data, $normalizedData);

        // 根据原始结果状态创建标准化结果
        if ($result->isSuccess()) {
            return PaymentResult::success(
                $result->getMessage(),
                $normalizedData,
                $result->getOrderNo(),
                $result->getTransactionId(),
                $result->getAmount(),
                $result->getCurrency(),
                $result->getRawResponse()
            );
        } else {
            return PaymentResult::failed(
                $result->getMessage(),
                $normalizedData,
                $result->getOrderNo(),
                $result->getRawResponse()
            );
        }
    }

    /**
     * 提取支付URL
     * @param array $data
     * @param array $rawResponse
     * @return string
     */
    private static function extractPaymentUrl(array $data, array $rawResponse): string
    {
        // 优先从data中获取
        $urlFields = ['payment_url', 'pay_url', 'redirect_url', 'url'];
        foreach ($urlFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return (string)$data[$field];
            }
        }

        // 从raw_response中获取
        if (isset($rawResponse['data']) && is_array($rawResponse['data'])) {
            foreach ($urlFields as $field) {
                if (isset($rawResponse['data'][$field]) && !empty($rawResponse['data'][$field])) {
                    return (string)$rawResponse['data'][$field];
                }
            }
        }

        return '';
    }

    /**
     * 提取支付数据
     * @param array $data
     * @param array $rawResponse
     * @return array
     */
    private static function extractPaymentData(array $data, array $rawResponse): array
    {
        $paymentData = [];
        
        // 支付相关字段
        $paymentFields = [
            'qr_code', 'order_id', 'expire_time', 'payment_url', 'pay_url',
            'form_data', 'redirect_url', 'transaction_id', 'merchant_order_no',
            'amount', 'currency', 'status', 'message'
        ];

        // 从data中提取
        foreach ($paymentFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $paymentData[$field] = $data[$field];
            }
        }

        // 从raw_response中提取
        if (isset($rawResponse['data']) && is_array($rawResponse['data'])) {
            foreach ($paymentFields as $field) {
                if (isset($rawResponse['data'][$field]) && !empty($rawResponse['data'][$field])) {
                    $paymentData[$field] = $rawResponse['data'][$field];
                }
            }
        }

        return $paymentData;
    }

    /**
     * 标准化供应商响应
     * @param array $response
     * @param string $supplierName
     * @return array
     */
    public static function normalizeSupplierResponse(array $response, string $supplierName = ''): array
    {
        $normalized = [
            'code' => $response['code'] ?? 200,
            'success' => $response['success'] ?? true,
            'message' => $response['message'] ?? '操作成功',
            'data' => $response['data'] ?? [],
            'supplier_name' => $supplierName,
            'timestamp' => time()
        ];

        // 确保data中包含基础字段
        if (isset($normalized['data']) && is_array($normalized['data'])) {
            $normalized['data']['payment_url'] = self::extractPaymentUrl($normalized['data'], $response);
            $normalized['data']['payment_data'] = self::extractPaymentData($normalized['data'], $response);
        }

        return $normalized;
    }
}
