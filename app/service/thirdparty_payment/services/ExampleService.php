<?php

namespace app\service\thirdparty_payment\services;

use app\service\thirdparty_payment\AbstractPaymentService;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 示例支付服务
 * 展示如何实现统一的数据格式
 */
class ExampleService extends AbstractPaymentService
{
    private string $apiKey;
    private string $apiSecret;
    private string $gatewayUrl;
    private string $notifyUrl;
    private string $returnUrl;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->apiKey = $config['api_key'] ?? 'example_key';
        $this->apiSecret = $config['api_secret'] ?? 'example_secret';
        $this->gatewayUrl = $config['gateway_url'] ?? 'https://api.example.com/v1';
        $this->notifyUrl = $config['notify_url'] ?? 'https://yourdomain.com/api/v1/callback/example';
        $this->returnUrl = $config['return_url'] ?? 'https://yourdomain.com/return/example';
    }

    public function processPayment(array $params): PaymentResult
    {
        try {
            // 验证必要参数
            $this->validateRequiredParams($params, ['order_no', 'order_amount', 'subject']);

            // 构建支付参数
            $paymentParams = $this->buildPaymentParams($params);
            
            // 发送支付请求
            $requestUrl = $this->gatewayUrl . '/payment/create';
            
            \support\Log::info('ExampleService 请求参数', [
                'input_params' => $params,
                'final_payment_params' => $paymentParams,
                'request_url' => $requestUrl
            ]);
            
            $response = $this->sendJsonRequest($requestUrl, $paymentParams);
            
            \support\Log::info('ExampleService 响应', [
                'response' => $response
            ]);

            if ($response['code'] === 200 && $response['success'] === true) {
                // 统一的数据格式
                $result = PaymentResult::success(
                    '支付请求成功',
                    [
                        'payment_url' => $response['data']['payment_url'] ?? '',
                        'qr_code' => $response['data']['qr_code'] ?? '',
                        'order_id' => $response['data']['order_id'] ?? '',
                        'expire_time' => $response['data']['expire_time'] ?? '',
                        'form_data' => $response['data']['form_data'] ?? [],
                        'redirect_url' => $response['data']['redirect_url'] ?? ''
                    ],
                    $params['order_no'],
                    $response['data']['order_id'] ?? '',
                    (float)$params['order_amount'],
                    'CNY',
                    $response
                );
                
                return $result;
            } else {
                return PaymentResult::failed(
                    $response['message'] ?? '支付请求失败',
                    $response,
                    $params['order_no'],
                    $response
                );
            }

        } catch (\Exception $e) {
            $this->log('error', 'Example支付失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('支付处理失败: ' . $e->getMessage(), [], $params['order_no'] ?? '', []);
        }
    }

    public function queryPayment(string $orderNo): PaymentResult
    {
        try {
            $this->log('info', '查询Example支付状态', ['order_no' => $orderNo]);

            $queryParams = $this->buildQueryParams($orderNo);
            
            $response = $this->sendJsonRequest(
                $this->gatewayUrl . '/payment/query',
                $queryParams
            );

            if ($response['code'] === 200) {
                $orderData = $response['data'] ?? [];
                
                $status = $this->mapOrderStatus($orderData['status'] ?? '');
                $message = $this->getStatusMessage($orderData['status'] ?? '');

                return new PaymentResult(
                    $status,
                    $message,
                    $orderData,
                    $orderNo,
                    $orderData['order_id'] ?? '',
                    (float)($orderData['amount'] ?? 0),
                    'CNY',
                    $response
                );
            } else {
                return PaymentResult::failed(
                    $response['message'] ?? '查询失败',
                    $response,
                    $orderNo,
                    $response
                );
            }

        } catch (\Exception $e) {
            $this->log('error', '查询Example支付状态失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('查询失败: ' . $e->getMessage(), [], $orderNo, []);
        }
    }

    public function handleCallback(array $callbackData): PaymentResult
    {
        try {
            $this->log('info', '处理Example支付回调', $callbackData);

            if (!$this->validateCallbackData($callbackData)) {
                return PaymentResult::failed('回调数据验证失败', $callbackData, '', $callbackData);
            }

            $orderNo = $callbackData['order_no'] ?? '';
            $status = $this->mapOrderStatus($callbackData['status'] ?? '');
            $message = $this->getStatusMessage($callbackData['status'] ?? '');

            return new PaymentResult(
                $status,
                $message,
                $callbackData,
                $orderNo,
                $callbackData['order_id'] ?? '',
                (float)($callbackData['amount'] ?? 0),
                'CNY',
                $callbackData
            );

        } catch (\Exception $e) {
            $this->log('error', '处理Example支付回调失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('回调处理失败: ' . $e->getMessage(), $callbackData, '', $callbackData);
        }
    }

    public function refund(array $refundParams): PaymentResult
    {
        $this->validateRequiredParams($refundParams, ['order_no', 'refund_amount']);

        try {
            $this->log('info', '开始处理Example支付退款', $refundParams);

            $params = $this->buildRefundParams($refundParams);
            
            $response = $this->sendJsonRequest(
                $this->gatewayUrl . '/payment/refund',
                $params
            );

            if ($response['code'] === 200 && $response['success'] === true) {
                return PaymentResult::success(
                    '退款成功',
                    $response['data'],
                    $refundParams['order_no'],
                    $response['data']['refund_id'] ?? '',
                    (float)($response['data']['refund_amount'] ?? 0),
                    'CNY',
                    $response
                );
            } else {
                return PaymentResult::failed(
                    $response['message'] ?? '退款失败',
                    $response,
                    $refundParams['order_no'],
                    $response
                );
            }

        } catch (\Exception $e) {
            $this->log('error', 'Example支付退款失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('退款失败: ' . $e->getMessage(), [], $refundParams['order_no'] ?? '', []);
        }
    }

    public function getServiceName(): string
    {
        return 'Example支付服务';
    }

    public function getServiceType(): string
    {
        return 'example_payment';
    }

    public function validateParams(array $params): bool
    {
        $required = ['order_no', 'order_amount', 'subject'];
        
        try {
            $this->validateRequiredParams($params, $required);
            return true;
        } catch (PaymentException $e) {
            return false;
        }
    }


    /**
     * 构建支付参数
     */
    private function buildPaymentParams(array $params): array
    {
        $orderNo = $params['order_no'] ?? $params['order_id'] ?? '';
        $totalAmount = $params['total_amount'] ?? $params['order_amount'] ?? 0;
        $subject = $params['subject'] ?? '';
        
        $baseParams = [
            'api_key' => $this->apiKey,
            'order_no' => $orderNo,
            'amount' => number_format($totalAmount, 2, '.', ''),
            'subject' => $subject,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'timestamp' => time()
        ];

        $baseParams['sign'] = $this->generateSignature($baseParams, $this->apiSecret);

        return $baseParams;
    }

    /**
     * 构建查询参数
     */
    private function buildQueryParams(string $orderNo): array
    {
        $baseParams = [
            'api_key' => $this->apiKey,
            'order_no' => $orderNo,
            'timestamp' => time()
        ];

        $baseParams['sign'] = $this->generateSignature($baseParams, $this->apiSecret);

        return $baseParams;
    }

    /**
     * 构建退款参数
     */
    private function buildRefundParams(array $params): array
    {
        $baseParams = [
            'api_key' => $this->apiKey,
            'order_no' => $params['order_no'],
            'refund_amount' => number_format($params['refund_amount'], 2, '.', ''),
            'refund_reason' => $params['refund_reason'] ?? '用户申请退款',
            'refund_no' => $params['refund_no'] ?? 'RF' . time() . mt_rand(1000, 9999),
            'timestamp' => time()
        ];

        $baseParams['sign'] = $this->generateSignature($baseParams, $this->apiSecret);

        return $baseParams;
    }

    /**
     * 验证回调数据
     */
    private function validateCallbackData(array $data): bool
    {
        $requiredFields = ['order_no', 'status', 'amount', 'sign'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->log('error', 'Example支付回调缺少必要字段', ['field' => $field, 'data' => $data]);
                return false;
            }
        }

        return true;
    }

    /**
     * 映射订单状态
     */
    private function mapOrderStatus(string $status): string
    {
        $statusMap = [
            'SUCCESS' => PaymentResult::STATUS_SUCCESS,
            'PAID' => PaymentResult::STATUS_SUCCESS,
            'PENDING' => PaymentResult::STATUS_PROCESSING,
            'WAITING' => PaymentResult::STATUS_PROCESSING,
            'FAILED' => PaymentResult::STATUS_FAILED,
            'CANCELLED' => PaymentResult::STATUS_FAILED,
            'EXPIRED' => PaymentResult::STATUS_FAILED,
        ];

        return $statusMap[$status] ?? PaymentResult::STATUS_FAILED;
    }

    /**
     * 获取状态消息
     */
    private function getStatusMessage(string $status): string
    {
        $messageMap = [
            'SUCCESS' => '支付成功',
            'PAID' => '支付成功',
            'PENDING' => '等待支付',
            'WAITING' => '等待支付',
            'FAILED' => '支付失败',
            'CANCELLED' => '订单取消',
            'EXPIRED' => '订单过期',
        ];

        return $messageMap[$status] ?? '未知状态';
    }
}
