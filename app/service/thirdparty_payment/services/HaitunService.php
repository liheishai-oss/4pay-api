<?php

namespace app\service\thirdparty_payment\services;

use app\service\thirdparty_payment\AbstractPaymentService;
use app\service\thirdparty_payment\AbstractUnifiedPaymentService;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 海豚支付服务
 * 对接海豚支付平台API
 */
class HaitunService extends AbstractUnifiedPaymentService
{
    private string $apiKey;
    private string $apiSecret;
    private string $gatewayUrl;
    private string $notifyUrl;
    private string $returnUrl;
    private array $callbackIps;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // 海豚支付配置参数 - 优先从传入的配置获取，然后从配置文件获取
        $this->apiKey = $config['appid'] ?? $config['api_key'] ?? config('Haitun.appid', '');
        $this->apiSecret = $config['api_secret'] ?? config('Haitun.api_secret', '');
        $this->gatewayUrl = $config['gateway_url'] ?? config('Haitun.gateway_url', '');
        $this->notifyUrl = $config['notify_url'] ?? config('Haitun.notify_url', '');
        $this->returnUrl = $config['return_url'] ?? config('Haitun.return_url', '');

        // 处理 callback_ips，支持字符串和数组格式
        $callbackIps = $config['callback_ips'] ?? config('Haitun.callback_ips', '');
        if (is_string($callbackIps) && !empty($callbackIps)) {
            $this->callbackIps = array_filter(array_map('trim', explode(',', $callbackIps)));
        } elseif (is_array($callbackIps)) {
            $this->callbackIps = $callbackIps;
        } else {
            $this->callbackIps = [];
        }

        // 验证必要配置
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('HaitunService: appid/api_key 配置不能为空');
        }
        if (empty($this->apiSecret)) {
            throw new \InvalidArgumentException('HaitunService: api_secret 配置不能为空');
        }
        if (empty($this->gatewayUrl)) {
            throw new \InvalidArgumentException('HaitunService: gateway_url 配置不能为空');
        }

        // 记录配置来源
        // \support\Log::info('HaitunService 配置初始化', [
        //     'config_source' => !empty($config) ? 'channel_basic_params' : 'config_file',
        //     'api_key' => $this->apiKey,
        //     'gateway_url' => $this->gatewayUrl,
        //     'config_keys' => array_keys($config)
        // ]);
    }

    /**
     * 静态方法：处理支付
     * @param array $params 支付参数
     * @param array $config 配置参数
     * @return PaymentResult
     */
    public static function processPaymentStatic(array $params, array $config = []): PaymentResult
    {
        $instance = new self($config);
        return $instance->processPaymentInstance($params);
    }

    public function processPayment(array $params): PaymentResult
    {
        return $this->processPaymentInstance($params);
    }

    private function processPaymentInstance(array $params): PaymentResult
    {
        // 支持商户API和测试API的参数格式
        // 检查是否包含必要的参数（支持两种格式）
        $hasOrderNo = isset($params['order_no']) || isset($params['order_id']);
        $hasAmount = isset($params['total_amount']) || isset($params['order_amount']);

        if (!$hasOrderNo || !$hasAmount) {
            throw new PaymentException('缺少必要参数：order_no/order_id, total_amount/order_amount');
        }

        try {
            // 构建支付参数
            $paymentParams = $this->buildPaymentParams($params);

            // 发送支付请求
            $requestUrl = $this->gatewayUrl . '/alipay/order/create';

            // 记录请求参数和最终参数
            \support\Log::info('HaitunService 请求参数', [
                'input_params' => $params,
                'final_payment_params' => $paymentParams,
                'request_url' => $requestUrl
            ]);

            // 添加断点：开始发送请求
            \support\Log::info('HaitunService 开始发送请求', [
                'request_url' => $requestUrl,
                'request_params' => $paymentParams,
                'timestamp' => time()
            ]);

            $response = $this->sendJsonRequest($requestUrl, $paymentParams);

            // 统一响应格式：将第三方API的code: 1000转换为code: 200
            if (isset($response['code']) && $response['code'] === 1000) {
                $response['code'] = 200;
            }

            // 添加断点：请求完成
            \support\Log::info('HaitunService 请求完成', [
                'response_code' => $response['_header']['response_code'] ?? 'unknown',
                'response_data' => $response,
                'timestamp' => time()
            ]);

            // 将最终请求参数添加到响应中
            if (isset($response['_header'])) {
                $response['_header']['final_request_params'] = $paymentParams;
            }

            $this->log('debug', '海豚支付响应', $response);

            // 统一状态封装：使用 status 字段表示成功/失败
            $unifiedResponse = $this->unifyResponseStatus($response);

            // 构建请求头信息
            $headerInfo = [
                'request_url' => $requestUrl,
                'request_method' => 'POST',
                'request_headers' => [
                    'Content-Type: application/json',
                    'User-Agent: PaymentService/1.0'
                ],
                'response_headers' => $response['_header']['response_headers'] ?? '',
                'response_code' => $response['_header']['response_code'] ?? 200,
                'final_request_params' => $paymentParams
            ];

            // 使用基类的统一处理
            $result = $this->processUnifiedResponse($unifiedResponse, $params, $headerInfo);

            if ($unifiedResponse['status'] === true) {
                $this->log('info', '海豚支付请求成功', $response);
            } else {
                $this->log('warning', '海豚支付请求失败', [
                    'message' => $unifiedResponse['message'],
                    'code' => $unifiedResponse['code']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->log('error', '海豚支付失败', ['error' => $e->getMessage()]);

            // 提取HTTP状态码
            $httpStatus = 0;
            if ($e instanceof PaymentException) {
                $context = $e->getContext();
                $httpStatus = $context['http_status'] ?? 0;
            }

            // 构建请求头信息，即使请求失败也要包含最终请求参数
            $headerInfo = [
                'request_url' => $requestUrl ?? '',
                'request_method' => 'POST',
                'request_headers' => [
                    'Content-Type: application/json',
                    'User-Agent: PaymentService/1.0'
                ],
                'response_headers' => [],
                'response_code' => $httpStatus,
                'final_request_params' => $paymentParams ?? []
            ];

            // 简化错误响应信息
            $errorResponse = [
                'code' => $httpStatus > 0 ? $httpStatus : 500,
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];

            $result = PaymentResult::failed('支付处理失败: ' . $e->getMessage(), $errorResponse, $params['order_no'] ?? '', $errorResponse);
            $result->setDebugInfo($headerInfo);
            $result->setHttpStatus($httpStatus);

            return $result;
        }
    }

    public function queryPayment(string $orderNo): PaymentResult
    {
        try {
            $this->log('info', '查询海豚支付状态', ['order_no' => $orderNo]);

            // 根据海豚支付实际配置构建查询URL
            // 海豚支付查单接口：POST /v1/alipay/order/detail
            // gateway_url已经包含了/v1，所以直接拼接路径
            $queryUrl = rtrim($this->gatewayUrl, '/') . '/alipay/order/detail';
            
            // 构建POST参数
            $params = [
                'appid' => $this->apiKey,
                'order_number' => $orderNo
            ];
            
            // 生成签名
            $signature = $this->generateQuerySignature([
                'appid' => $this->apiKey,
                'order_number' => $orderNo
            ]);
            $params['sign'] = $signature;

            $this->log('info', '海豚支付查询请求', [
                'query_url' => $queryUrl,
                'params' => $params,
                'order_no' => $orderNo
            ]);

            // 发送POST请求
            $response = $this->sendHttpRequest($queryUrl, $params, [], 'POST');

            $this->log('debug', '海豚支付查询响应', $response);

            // 根据海豚支付实际响应格式处理
            // 海豚支付查单响应格式：嵌套在data中
            if (isset($response['code']) && $response['code'] === 1000 && isset($response['data'])) {
                $data = $response['data'];
                
                // 海豚支付状态：1=待支付,2=支付中,3=支付成功,4=支付失败
                $paymentStatus = $data['payment_status'];
                $isPaid = ($paymentStatus === '3');

                // 设置rawResponse中的status字段，确保PaymentResult::isSuccess()能正确判断
                $response['status'] = $isPaid;

                $status = $isPaid ? PaymentResult::STATUS_SUCCESS : PaymentResult::STATUS_PROCESSING;
                $message = $isPaid ? '支付成功' : '未支付';

                $result = new PaymentResult(
                    $status,
                    $message,
                    $response,
                    $orderNo,
                    $data['platform_order_number'] ?? '',
                    (float)($data['payment_amount'] ?? 0),
                    'CNY',
                    $response
                );

                $this->log('info', '海豚支付查询成功', [
                    'order_no' => $orderNo,
                    'payment_status' => $paymentStatus,
                    'is_paid' => $isPaid,
                    'status' => $status
                ]);

                return $result;
            } else {
                $errorMessage = $response['msg'] ?? '查询失败';
                $this->log('warning', '海豚支付查询失败', [
                    'order_no' => $orderNo,
                    'error' => $errorMessage,
                    'response' => $response
                ]);

                return PaymentResult::failed(
                    $errorMessage,
                    $response,
                    $orderNo,
                    $response
                );
            }

        } catch (\Exception $e) {
            $this->log('error', '查询海豚支付状态失败', [
                'order_no' => $orderNo,
                'error' => $e->getMessage()
            ]);
            return PaymentResult::failed('查询失败: ' . $e->getMessage(), [], $orderNo, []);
        }
    }

    public function handleCallback(array $callbackData): PaymentResult
    {
        try {
            $this->log('info', '处理海豚支付回调', $callbackData);

            // 验证必要参数
            if (!$this->validateCallbackData($callbackData)) {
                return PaymentResult::failed('回调数据验证失败', $callbackData, '', $callbackData);
            }

            // 验证IP白名单 - 暂时跳过，使用中间件验证
            // if (!$this->verifyCallbackIp()) {
            //     return PaymentResult::failed('IP验证失败', $callbackData, '', $callbackData);
            // }
            
            $this->log('info', '海豚支付回调IP验证已跳过，使用中间件验证', [
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? ''
            ]);

            // 验证签名
            if (!$this->verifyCallbackSignature($callbackData)) {
                return PaymentResult::failed('签名验证失败', $callbackData, '', $callbackData);
            }

            // 海豚支付回调字段映射
            $orderNo = $callbackData['merchant_order_number'] ?? '';
            $status = $this->mapOrderStatus($callbackData['payment_status'] ?? '');
            $message = $this->getStatusMessage($callbackData['payment_status'] ?? '');

            // 构建原始响应数据，包含状态信息
            // 海豚支付状态：1=待支付,2=支付中,3=支付成功,4=支付失败
            $isSuccess = ($callbackData['payment_status'] ?? '') === '3';
            $rawResponse = array_merge($callbackData, [
                'status' => $isSuccess,
                'message' => $message
            ]);
            
            $this->log('info', '海豚支付回调状态映射', [
                'payment_status' => $callbackData['payment_status'] ?? '',
                'mapped_status' => $status,
                'status_message' => $message,
                'is_success' => $isSuccess,
                'raw_response_status' => $rawResponse['status']
            ]);
            
            return new PaymentResult(
                $status,
                $message,
                $callbackData,
                $orderNo,
                $callbackData['payment_order_number'] ?? '',
                (float)($callbackData['payment_amount'] ?? 0),
                'CNY',
                $rawResponse
            );

        } catch (\Exception $e) {
            $this->log('error', '处理海豚支付回调失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('回调处理失败: ' . $e->getMessage(), $callbackData, '', $callbackData);
        }
    }


    public function getServiceName(): string
    {
        return '海豚支付服务';
    }

    public function getServiceType(): string
    {
        return 'Haitun';
    }
    
    /**
     * 获取回调响应格式
     * 海豚支付需要返回纯文本 "success"
     * @param bool $isSuccess 是否成功
     * @return array 响应格式配置
     */
    public function getCallbackResponseFormat(bool $isSuccess = true): array
    {
        return [
            'type' => 'text',
            'content' => 'success',
            'headers' => [
                'Content-Type' => 'text/plain; charset=utf-8'
            ]
        ];
    }

    public function validateParams(array $params): bool
    {
        $required = ['order_no', 'total_amount'];

        try {
            $this->validateRequiredParams($params, $required);
            return true;
        } catch (PaymentException $e) {
            return false;
        }
    }



    /**
     * 构建支付参数
     * @param array $params
     * @return array
     */
    private function buildPaymentParams(array $params): array
    {
        // 支持商户API和测试API的参数格式
        $orderNo = $params['order_no'] ?? $params['order_id'] ?? '';
        $totalAmount = $params['total_amount'] ?? $params['order_amount'] ?? 0;
        $subject = $params['subject'] ?? ''; // 设置默认值

        $baseParams = [
            'appid' => $this->apiKey,
            'merchant_order_number' => $orderNo,
            'payment_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_channel' => $params['product_code'],
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'request_time' => time(),
            'subject' => $subject // 添加subject参数到支付参数中
        ];

        // 生成签名
        $baseParams['sign'] = $this->generateHaitunSignature($baseParams);

        return $baseParams;
    }



    /**
     * 生成海豚支付签名
     * @param array $params
     * @return string
     */
    private function generateHaitunSignature(array $params): string
    {
        // 按照Haitun签名规则：appid + merchant_order_number + payment_amount + payment_channel + notify_url + return_url + request_time + secretKey
        $queryString = $this->apiKey .
            $params['merchant_order_number'] .
            $params['payment_amount'] .
            $params['payment_channel'] .
            $params['notify_url'] .
            $params['return_url'] .
            $params['request_time'] .
            $this->apiSecret;

        return md5($queryString);
    }
    
    /**
     * 生成查询签名
     * @param array $params
     * @return string
     */
    private function generateQuerySignature(array $params): string
    {
        // 查询签名规则：appid + order_number + secretKey
        $queryString = $params['appid'] .
            $params['order_number'] .
            $this->apiSecret;

        return md5($queryString);
    }


    /**
     * 验证回调签名
     * 海豚支付回调签名算法：
     * md5(appid + merchant_order_number + payment_amount + payment_status + created_at + order_success_time + order_expiry_time + secret_key)
     * @param array $data
     * @return bool
     */
    private function verifyCallbackSignature(array $data): bool
    {
        $sign = $data['sign'] ?? '';
        
        // 海豚支付回调签名算法
        $queryString = $this->apiKey . 
                      $data['merchant_order_number'] . 
                      $data['payment_amount'] . 
                      $data['payment_status'] . 
                      $data['created_at'] . 
                      $data['order_success_time'] . 
                      $data['order_expiry_time'] . 
                      $this->apiSecret;
        
        $expectedSign = md5($queryString);
        
        $this->log('info', '海豚支付回调签名验证', [
            'received_sign' => $sign,
            'expected_sign' => $expectedSign,
            'query_string' => $queryString,
            'data_for_sign' => $data
        ]);
        
        return hash_equals($expectedSign, $sign);
    }

    /**
     * 验证回调IP
     * @return bool
     */
    private function verifyCallbackIp(): bool
    {
        // 获取真实客户端IP，考虑代理情况
        $clientIp = $this->getRealClientIp();
        
        $this->log('info', '海豚支付IP验证', [
            'client_ip' => $clientIp,
            'allowed_ips' => $this->callbackIps,
            'is_allowed' => in_array($clientIp, $this->callbackIps)
        ]);
        
        return in_array($clientIp, $this->callbackIps);
    }
    
    /**
     * 获取真实客户端IP
     * @return string
     */
    private function getRealClientIp(): string
    {
        // 按优先级检查各种可能的IP头
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // 处理多个IP的情况（如X-Forwarded-For: client, proxy1, proxy2）
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]); // 取第一个IP（真实客户端IP）
                }
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * 验证回调数据
     * 海豚支付实际回调格式：
     * {
     *   "created_at": "1760108531",
     *   "merchant_order_number": "BY20251010230211C9F05903",
     *   "order_expiry_time": "1760109131",
     *   "order_success_time": "1760108579",
     *   "payment_amount": "1.00",
     *   "payment_order_number": "P732025101023021164720",
     *   "payment_status": "3",
     *   "sign": "b02e50e410c0421dc79eeaed23914ff8"
     * }
     * @param array $data
     * @return bool
     */
    private function validateCallbackData(array $data): bool
    {
        // 验证海豚支付回调的必要字段
        $requiredFields = ['merchant_order_number', 'payment_status', 'payment_amount', 'sign'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->log('error', '海豚支付回调缺少必要字段', ['field' => $field, 'data' => $data]);
                return false;
            }
        }

        // 验证订单状态（支持数字状态和字符串状态）
        $validStatuses = ['1', '2', '3', '4', 'SUCCESS', 'PAID', 'PENDING', 'WAITING', 'FAILED', 'CANCELLED', 'EXPIRED'];
        if (!in_array($data['payment_status'], $validStatuses)) {
            $this->log('error', '海豚支付回调状态无效', ['status' => $data['payment_status']]);
            return false;
        }

        // 验证金额格式
        if (!is_numeric($data['payment_amount']) || $data['payment_amount'] <= 0) {
            $this->log('error', '海豚支付回调金额无效', ['amount' => $data['payment_amount']]);
            return false;
        }

        // 验证订单号格式
        if (empty($data['merchant_order_number']) || strlen($data['merchant_order_number']) < 6) {
            $this->log('error', '海豚支付回调订单号无效', ['order_no' => $data['merchant_order_number']]);
            return false;
        }

        return true;
    }

    /**
     * 映射订单状态
     * @param string $status
     * @return string
     */
    private function mapOrderStatus(string $status): string
    {
        $statusMap = [
            // 海豚支付状态映射：1=待支付,2=支付中,3=支付成功,4=支付失败
            '1' => PaymentResult::STATUS_PENDING,    // 待支付
            '2' => PaymentResult::STATUS_PROCESSING, // 支付中
            '3' => PaymentResult::STATUS_SUCCESS,    // 支付成功
            '4' => PaymentResult::STATUS_FAILED,     // 支付失败
            // 字符串状态（兼容性）
            'SUCCESS' => PaymentResult::STATUS_SUCCESS,
            'PAID' => PaymentResult::STATUS_SUCCESS,
            'PENDING' => PaymentResult::STATUS_PENDING,
            'PROCESSING' => PaymentResult::STATUS_PROCESSING,
            'WAITING' => PaymentResult::STATUS_PROCESSING,
            'FAILED' => PaymentResult::STATUS_FAILED,
            'CANCELLED' => PaymentResult::STATUS_FAILED,
            'EXPIRED' => PaymentResult::STATUS_FAILED,
        ];

        return $statusMap[$status] ?? PaymentResult::STATUS_FAILED;
    }

    /**
     * 获取状态消息
     * @param string $status
     * @return string
     */
    private function getStatusMessage(string $status): string
    {
        $messageMap = [
            // 数字状态（海豚支付实际返回）
            '1' => '待支付',
            '2' => '支付中',
            '3' => '支付成功',
            '4' => '支付失败',
            // 字符串状态（兼容性）
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

    /**
     * 生成随机字符串
     * @param int $length
     * @return string
     */
    private function generateNonce(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nonce = '';
        for ($i = 0; $i < $length; $i++) {
            $nonce .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $nonce;
    }

    /**
     * 判断响应是否成功
     * 海豚支付的成功判断条件：HTTP 200 且 success=true
     *
     * @param array $response 原始响应数据
     * @return bool true=成功，false=失败
     */
    protected function isResponseSuccess(array $response): bool
    {
        // 检查HTTP状态码
        $httpCode = $response['_header']['response_code'] ?? $response['code'] ?? 0;

        // 如果HTTP状态码不是200，直接返回失败
        if ($httpCode !== 200) {
            return false;
        }

        // 检查是否有success字段，如果有则使用它
        if (isset($response['success'])) {
            return $response['success'] === true;
        }

        // 如果没有success字段，但有code字段且为200，则认为是成功
        if (isset($response['code']) && $response['code'] === 200) {
            return true;
        }

        // 如果只有HTTP状态码为200，也认为是成功
        return $httpCode === 200;
    }
}

