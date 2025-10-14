<?php

namespace app\service\thirdparty_payment\services;

use app\service\thirdparty_payment\AbstractPaymentService;
use app\service\thirdparty_payment\AbstractUnifiedPaymentService;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 跑分支付服务
 * 对接跑分支付平台API
 */
class PaofenService extends AbstractUnifiedPaymentService
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
        
        // 跑分支付配置参数
        $this->apiKey = $config['appid'] ?? config('Paofen.appid', '1832');
        $this->apiSecret = $config['api_secret'] ?? config('Paofen.api_secret', '23bcd3520a9b4350a2e4cace07b91bf2');
        $this->gatewayUrl = $config['gateway_url'] ?? 'https://api.pf.yyzcss.com';
        $this->notifyUrl = $config['notify_url'] ?? 'https://yourdomain.com/api/v1/callback/paofen';
        $this->returnUrl = $config['return_url'] ?? 'https://yourdomain.com/return/paofen';
        
        // 处理 callback_ips，支持字符串和数组格式
        $callbackIps = $config['callback_ips'] ?? '127.0.0.1,::1';
        if (is_string($callbackIps)) {
            $this->callbackIps = array_filter(array_map('trim', explode(',', $callbackIps)));
        } else {
            $this->callbackIps = is_array($callbackIps) ? $callbackIps : ['127.0.0.1', '::1'];
        }
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
        print_r($params);
        return $instance->processPaymentInstance($params);
    }

    public function processPayment(array $params): PaymentResult
    {
        return $this->processPaymentInstance($params);
    }

    private function processPaymentInstance(array $params): PaymentResult
    {


        try {
            // 构建支付参数
            $paymentParams = $this->buildPaymentParams($params);
            print_r($params);
            // 发送支付请求
            $requestUrl = $this->gatewayUrl . '/api/create_order';
            print_r($requestUrl);
            // 记录请求参数
            \support\Log::info('PaofenService 请求参数', [
                'input_params' => $params,
                'final_payment_params' => $paymentParams,
                'request_url' => $requestUrl
            ]);
            
            $response = $this->sendFormRequest($requestUrl, $paymentParams);
            
            // 统一响应格式：将第三方API的code: 200转换为统一格式
            if (isset($response['code']) && $response['code'] == 200) {
                $response['status'] = true;
            }
            
            $this->log('debug', '跑分支付响应', $response);
            
            // 统一状态封装
            $unifiedResponse = $this->unifyResponseStatus($response);
            
            // 构建请求头信息
            $headerInfo = [
                'request_url' => $requestUrl,
                'request_method' => 'POST',
                'request_headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: PaymentService/1.0'
                ],
                'response_headers' => $response['_header']['response_headers'] ?? '',
                'response_code' => $response['_header']['response_code'] ?? 200,
                'final_request_params' => $paymentParams
            ];
            
            // 使用基类的统一处理
            $result = $this->processUnifiedResponse($unifiedResponse, $params, $headerInfo);
            
            if ($unifiedResponse['status'] === true) {
                $this->log('info', '跑分支付请求成功', $response);
            } else {
                $this->log('warning', '跑分支付请求失败', [
                    'message' => $unifiedResponse['message'],
                    'code' => $unifiedResponse['code']
                ]);
            }
            
            return $result;

        } catch (\Exception $e) {
            $this->log('error', '跑分支付失败', ['error' => $e->getMessage()]);
            
            // 提取HTTP状态码
            $httpStatus = 0;
            if ($e instanceof PaymentException) {
                $context = $e->getContext();
                $httpStatus = $context['http_status'] ?? 0;
            }
            
            // 构建请求头信息
            $headerInfo = [
                'request_url' => $requestUrl ?? '',
                'request_method' => 'POST',
                'request_headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
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

    /**
     * 查询支付状态
     * @param string $orderNo 订单号
     * @return PaymentResult
     */
    public function queryPayment(string $orderNo): PaymentResult
    {
        try {
            $this->log('info', '查询跑分支付状态', ['order_no' => $orderNo]);

            // 构建查询参数
            $queryParams = $this->buildQueryParams($orderNo);
            
            // 构建完整请求URL
            $requestUrl = $this->gatewayUrl . '/api/pay/query';
  
            // 发送查询请求
            $response = $this->sendJsonRequest(
                $requestUrl,
                $queryParams
            );

            // 统一响应格式
            if (isset($response['code']) && $response['code'] === 200) {
                $response['status'] = true;
            }

            $this->log('debug', '跑分支付查询响应', $response);
            
            // 统一状态封装
            $unifiedResponse = $this->unifyResponseStatus($response);
            
            // 构建请求头信息
            $headerInfo = [
                'request_url' => $requestUrl,
                'request_method' => 'POST',
                'request_headers' => [
                    'User-Agent: PaymentService/1.0'
                ],
                'response_headers' => $response['_header']['response_headers'] ?? '',
                'response_code' => $response['_header']['response_code'] ?? 200,
                'final_request_params' => $queryParams
            ];
            
            if ($unifiedResponse['status'] === true) {
                $this->log('info', '跑分支付查询成功', $response);
                
                // 获取查询数据并进行状态映射
                $queryData = $unifiedResponse['data'] ?? [];
                $originalStatus = $queryData['payment_status'] ?? $queryData['status'] ?? '';
                $mappedStatus = $this->mapOrderStatus($originalStatus);
                $message = $this->getStatusMessage($originalStatus);
                
                return new PaymentResult(
                    $mappedStatus,
                    $message,
                    $queryData,
                    $orderNo,
                    $queryData['order_id'] ?? '',
                    (float)($queryData['amount'] ?? 0),
                    'CNY',
                    $response
                );
            } else {
                $this->log('warning', '跑分支付查询失败', [
                    'message' => $unifiedResponse['message'],
                    'code' => $unifiedResponse['code']
                ]);
                
                return PaymentResult::failed(
                    $unifiedResponse['message'] ?? '查询失败',
                    $unifiedResponse,
                    $orderNo,
                    $response
                );
            }

        } catch (\Exception $e) {
            $this->log('error', '跑分支付查询失败', ['error' => $e->getMessage()]);
            
            // 提取HTTP状态码
            $httpStatus = 0;
            if ($e instanceof PaymentException) {
                $context = $e->getContext();
                $httpStatus = $context['http_status'] ?? 0;
            }
            
            // 构建请求头信息
            $headerInfo = [
                'request_url' => $requestUrl ?? '',
                'request_method' => 'POST',
                'request_headers' => [
                    'User-Agent: PaymentService/1.0'
                ],
                'response_headers' => [],
                'response_code' => $httpStatus,
                'final_request_params' => $queryParams ?? []
            ];
            
            // 简化错误响应信息
            $errorResponse = [
                'code' => $httpStatus > 0 ? $httpStatus : 500,
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
            
            $result = PaymentResult::failed('查询失败: ' . $e->getMessage(), $errorResponse, $orderNo, $errorResponse);
            $result->setDebugInfo($headerInfo);
            $result->setHttpStatus($httpStatus);
            
            return $result;
        }
    }

    /**
     * 构建支付参数
     * @param array $params 原始参数
     * @return array 构建后的参数
     */
    private function buildPaymentParams(array $params): array
    {
        // 根据跑分支付API文档构建参数
        $orderNo = $params['order_no'] ?? $params['order_id'] ?? '';
        $totalAmount = $params['total_amount'] ?? $params['order_amount'] ?? 0;
        $subject = $params['subject'] ?? '订单支付';
        
        $baseParams = [
            'merchantId' => $this->apiKey,                    // 商户ID
            'channelId' => $params['product_code'] ?? $params['payment_channel'] ?? '8001',  // 支付产品ID
            'mchOrderNo' => $orderNo,                         // 商户订单号
            'amount' => (int)($totalAmount * 100),           // 支付金额，单位分
            'notifyUrl' => $this->notifyUrl,                  // 异步回调地址
            'returnUrl' => $this->returnUrl,                  // 同步请求地址
            'subject' => $subject,                           // 商品主题
            'timestamp' => date('YmdHis')                    // 请求时间，yyyyMMddHHmmss格式
        ];

        // 生成签名
        $baseParams['sign'] = $this->generatePaofenSignature($baseParams);

        return $baseParams;
    }

    /**
     * 构建查询参数
     * @param string $orderNo 订单号
     * @return array 查询参数
     */
    private function buildQueryParams(string $orderNo): array
    {
        // 根据跑分支付API文档构建查询参数
        $baseParams = [
            'merchantId' => $this->apiKey,        // 商户ID
            'mchOrderNo' => $orderNo,             // 商户订单号
            'executeNotify' => false,             // 是否执行回调，默认false
            'timestamp' => date('YmdHis')         // 请求时间，yyyyMMddHHmmss格式
        ];

        // 生成签名
        $baseParams['sign'] = $this->generateQuerySignature($baseParams);

        return $baseParams;
    }

    /**
     * 生成跑分支付签名
     * @param array $params 参数数组
     * @return string 签名
     */
    private function generatePaofenSignature(array $params): string
    {
        // 按照跑分支付签名算法：参数按ASCII码排序，拼接成URL键值对格式，最后加上私钥
        $signParams = $params;
        unset($signParams['sign']); // 移除sign参数，不参与签名
        
        // 过滤空值参数
        $signParams = array_filter($signParams, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // 按参数名ASCII码从小到大排序
        ksort($signParams);
        
        // 拼接成URL键值对格式
        $stringA = '';
        foreach ($signParams as $key => $value) {
            $stringA .= $key . '=' . $value . '&';
        }
        
        // 在最后拼接私钥
        $stringSignTemp = $stringA . 'key=' . $this->apiSecret;
        
        // MD5运算并转换为大写
        return strtoupper(md5($stringSignTemp));
    }

    /**
     * 生成查询签名
     * @param array $params 参数数组
     * @return string 签名
     */
    private function generateQuerySignature(array $params): string
    {
        // 使用统一的跑分支付签名算法
        return $this->generatePaofenSignature($params);
    }


    /**
     * 验证回调签名
     * @param array $callbackData 回调数据
     * @return bool
     */
    private function verifyCallbackSignature(array $callbackData): bool
    {
        if (!isset($callbackData['sign'])) {
            return false;
        }
        
        $receivedSign = $callbackData['sign'];
        $calculatedSign = $this->generatePaofenSignature($callbackData);
        
        return $receivedSign === $calculatedSign;
    }

    /**
     * 生成随机字符串
     * @return string
     */
    private function generateNonce(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * 验证回调签名
     * @param array $data 回调数据
     * @return bool 验证结果
     */
    public function verifyCallback(array $data): bool
    {
        if (!isset($data['sign'])) {
            return false;
        }

        $sign = $data['sign'];
        $expectedSign = $this->generateCallbackSignature($data);
        return hash_equals($expectedSign, $sign);
    }

    /**
     * 生成回调签名
     * @param array $data 回调数据
     * @return string 签名
     */
    private function generateCallbackSignature(array $data): string
    {
        // 按照跑分支付回调签名规则：order_no + status + amount + secretKey
        $signString = $data['order_no'] . $data['status'] . $data['amount'] . $this->apiSecret;
        
        return md5($signString);
    }

    /**
     * 验证回调IP
     * @return bool
     */
    private function verifyCallbackIp(): bool
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($clientIp, $this->callbackIps);
    }

    /**
     * 验证回调数据
     * @param array $data
     * @return bool
     */
    private function validateCallbackData(array $data): bool
    {
        // 验证必要字段
        $requiredFields = ['order_no', 'status', 'amount', 'sign'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->log('error', '跑分支付回调缺少必要字段', ['field' => $field, 'data' => $data]);
                return false;
            }
        }

        return true;
    }

    /**
     * 处理回调通知
     * @param array $data 回调数据
     * @return PaymentResult
     */
    public function handleCallback(array $data): PaymentResult
    {
        try {
            // 验证回调数据
            if (!$this->validateCallbackData($data)) {
                throw new PaymentException('回调数据验证失败');
            }

            // 验证签名
            if (!$this->verifyCallback($data)) {
                throw new PaymentException('回调签名验证失败');
            }

            // 验证IP白名单
            if (!$this->verifyCallbackIp()) {
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
                throw new PaymentException('回调IP不在白名单中: ' . $clientIp);
            }

            $this->log('info', '跑分支付回调验证成功', $data);

            // 构建统一响应
            $unifiedResponse = [
                'status' => true,
                'code' => 200,
                'message' => '回调处理成功',
                'data' => $data
            ];

            return PaymentResult::success('回调处理成功', $unifiedResponse, $data['order_no'] ?? '');

        } catch (\Exception $e) {
            $this->log('error', '跑分支付回调处理失败', ['error' => $e->getMessage()]);
            return PaymentResult::failed('回调处理失败: ' . $e->getMessage(), [], $data['order_no'] ?? '');
        }
    }

    /**
     * 映射订单状态
     * @param string $status 原始状态
     * @return string 映射后的状态
     */
    private function mapOrderStatus(string $status): string
    {
        $statusMap = [
            '1' => PaymentResult::STATUS_PENDING,
            '2' => PaymentResult::STATUS_PROCESSING,
            '3' => PaymentResult::STATUS_SUCCESS,
            '4' => PaymentResult::STATUS_FAILED,
            'pending' => PaymentResult::STATUS_PENDING,
            'processing' => PaymentResult::STATUS_PROCESSING,
            'success' => PaymentResult::STATUS_SUCCESS,
            'failed' => PaymentResult::STATUS_FAILED,
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
            '1' => '待支付',
            '2' => '支付中',
            '3' => '支付成功',
            '4' => '支付失败',
            'pending' => '待支付',
            'processing' => '支付中',
            'success' => '支付成功',
            'failed' => '支付失败',
        ];

        return $messageMap[$status] ?? '未知状态';
    }



    /**
     * 获取服务名称
     * @return string
     */
    public function getServiceName(): string
    {
        return '跑分支付服务';
    }

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string
    {
        return 'Paofen';
    }

    /**
     * 验证参数
     * @param array $params 参数
     * @return bool
     */
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
     * 获取支持的支付方式
     * @return array
     */
    public function getSupportedPaymentMethods(): array
    {
        return [
            'alipay' => '支付宝',
            'wechat' => '微信支付',
            'bank' => '银行卡',
            'unionpay' => '银联支付',
            'qq' => 'QQ钱包',
            'jd' => '京东支付'
        ];
    }

    /**
     * 判断响应是否成功
     * @param array $response 响应数据
     * @return bool
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
    