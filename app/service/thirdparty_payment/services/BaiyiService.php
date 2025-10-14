<?php

namespace app\service\thirdparty_payment\services;

use app\service\thirdparty_payment\AbstractUnifiedPaymentService;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * Baiyi(易支付/聚合支付) 服务
 * 文档参考：https://wukiw.paopd.com/doc.html#pay1
 */
class BaiyiService extends AbstractUnifiedPaymentService
{
    private string $gatewayUrl;
    private string $pid;
    private string $key;
    private string $notifyUrl;
    private string $returnUrl;
    private string $defaultDevice;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        print_r($config);
        // Baiyi支付配置参数 - 优先从传入的配置获取，然后从配置文件获取
        $this->gatewayUrl = $config['gateway_url'] ?? config('Baiyi.gateway_url', '');
        $this->pid = (string)($config['pid'] ?? config('Baiyi.pid', ''));
        $this->key = (string)($config['key'] ?? config('Baiyi.key', ''));
        $this->notifyUrl = (string)($config['notify_url'] ?? config('Baiyi.notify_url', ''));
        $this->returnUrl = (string)($config['return_url'] ?? config('Baiyi.return_url', ''));
        $this->defaultDevice = (string)($config['device'] ?? config('Baiyi.device', ''));
        
        // 验证必要配置
        if (empty($this->pid)) {
            throw new \InvalidArgumentException('BaiyiService: pid 配置不能为空');
        }
        if (empty($this->key)) {
            throw new \InvalidArgumentException('BaiyiService: key 配置不能为空');
        }
        if (empty($this->gatewayUrl)) {
            throw new \InvalidArgumentException('BaiyiService: gateway_url 配置不能为空');
        }
        
        // 记录配置来源（暂时注释掉，避免日志配置问题）
        // \support\Log::info('BaiyiService 配置初始化', [
        //     'config_source' => !empty($config) ? 'channel_basic_params' : 'config_file',
        //     'gateway_url' => $this->gatewayUrl,
        //     'pid' => $this->pid,
        //     'config_keys' => array_keys($config)
        // ]);
    }

    public static function processPaymentStatic(array $params, array $config = []): PaymentResult
    {
        $instance = new self($config);
        return $instance->processPayment($params);
    }

    public function processPayment(array $params): PaymentResult
    {
        // 必填：订单号、金额、支付方式（支持两种金额参数格式）
        $hasAmount = isset($params['total_amount']) || isset($params['order_amount']);
        if (!isset($params['order_no']) || !$hasAmount || !isset($params['product_code'])) {
            throw new PaymentException('缺少必需参数: order_no, total_amount/order_amount, product_code');
        }

        // 组装请求参数 (application/x-www-form-urlencoded)
        $requestParams = $this->buildPaymentParams($params);

        $url = rtrim($this->gatewayUrl, '/') . '/mapi.php';

        $this->log('info', 'Baiyi 发起下单', [
            'url' => $url,
            'params' => $requestParams
        ]);

        try {
            // 显式传递较为兼容的Header，避免某些CDN在HTTP2/ALPN上握手异常
            $headers = [
                'Accept: application/json, text/plain, */*',
                'Connection: keep-alive',
            ];
            $response = $this->sendFormRequest($url, $requestParams, $headers, 'POST');
            print_r($response);
            // 调试：记录原始响应
            $this->log('info', 'BaiyiService 原始响应', [
                'response' => $response,
                'response_type' => gettype($response),
                'has_raw_response' => isset($response['raw_response']),
                'trade_no' => $response['trade_no'] ?? 'not_set',
                'payurl' => $response['payurl'] ?? 'not_set',
                'qrcode' => $response['qrcode'] ?? 'not_set',
                'urlscheme' => $response['urlscheme'] ?? 'not_set'
            ]);

            // 统一状态
            $unified = $this->unifyResponseStatus($response);

            // 填充常见返回字段 - 根据百亿支付文档正确映射
            $unified['data'] = [
                'order_id' => $response['trade_no'] ?? '',
                'pay_url' => $response['payurl'] ?? '', // 支付跳转URL（直接跳转支付）
                'qr_code' => $response['qrcode'] ?? '', // 二维码链接（生成二维码）
                'redirect_url' => $response['urlscheme'] ?? '' // 小程序跳转URL（微信小程序支付）
            ];

            // 构建请求头信息
            $headerInfo = [
                'endpoint' => $url,
                'request_method' => 'POST',
                'request_params' => $requestParams,
                'response_data' => $response
            ];
            
            return $this->processUnifiedResponse($unified, [
                'order_no' => $params['order_no'],
                'total_amount' => $params['total_amount'] ?? $params['order_amount'] ?? 0
            ], $headerInfo);
        } catch (\Exception $e) {
            return PaymentResult::failed('Baiyi 下单失败: ' . $e->getMessage(), [
                'exception' => $e->getMessage()
            ], $params['order_no'] ?? '');
        }
    }

    public function queryPayment(string $orderNo): PaymentResult
    {
        if (empty($orderNo)) {
            throw new PaymentException('缺少必要参数：orderNo');
        }

        $query = [
            'act' => 'order',
            'pid' => $this->pid,
            'key' => $this->key,
            'out_trade_no' => $orderNo
        ];

        $url = rtrim($this->gatewayUrl, '/') . '/api.php?' . http_build_query($query);

        $this->log('info', 'Baiyi 查询订单', [
            'url' => $url
        ]);

        try {
            // GET 请求，使用 JSON 解析
            $response = $this->sendHttpRequest($url, [], [], 'GET');

            $isPaid = false;
            if (isset($response['status'])) {
                // 文档：status=1为支付成功，0为未支付
                $isPaid = (int)$response['status'] === 1;
            }

            $unified = [
                'status' => $isPaid,
                'code' => $response['code'] ?? 0,
                'message' => $response['msg'] ?? ($isPaid ? '支付成功' : '未支付'),
                'data' => $response,
                'original_response' => $response
            ];

            if ($isPaid) {
                // 在rawResponse中添加status字段，供PaymentResult::isSuccess()使用
                $rawResponse = $response;
                $rawResponse['status'] = true;
                
                return PaymentResult::success(
                    '支付成功',
                    $response,
                    $orderNo,
                    (string)($response['trade_no'] ?? ''),
                    (float)($response['money'] ?? 0),
                    'CNY',
                    $rawResponse
                );
            }

            // 未支付时也添加status字段
            $rawResponse = $response;
            $rawResponse['status'] = false;
            
            return PaymentResult::processing('未支付', $response, $orderNo, $rawResponse);
        } catch (\Exception $e) {
            return PaymentResult::failed('Baiyi 查询失败: ' . $e->getMessage(), [], $orderNo);
        }
    }

    public function handleCallback(array $callbackData): PaymentResult
    {
        try {
            $this->log('info', 'Baiyi 回调开始', $callbackData);

            // 验证必要参数
            $required = ['pid', 'out_trade_no', 'trade_no', 'type', 'money', 'trade_status', 'sign'];
            $this->validateRequiredParams($callbackData, $required);

            // 验签
            if (!$this->verifyCallbackSignature($callbackData)) {
                return PaymentResult::failed('签名验证失败', $callbackData, (string)($callbackData['out_trade_no'] ?? ''), $callbackData);
            }

            $status = strtoupper((string)$callbackData['trade_status']) === 'TRADE_SUCCESS';
            
            // 在rawResponse中添加status字段，供PaymentResult::isSuccess()使用
            $rawResponse = $callbackData;
            $rawResponse['status'] = $status;

            return new PaymentResult(
                $status ? PaymentResult::STATUS_SUCCESS : PaymentResult::STATUS_FAILED,
                $status ? '支付成功' : '支付失败',
                $callbackData,
                (string)$callbackData['out_trade_no'],
                (string)($callbackData['trade_no'] ?? ''),
                (float)$callbackData['money'],
                'CNY',
                $rawResponse
            );
        } catch (\Exception $e) {
            return PaymentResult::failed('回调处理异常: ' . $e->getMessage(), $callbackData, (string)($callbackData['out_trade_no'] ?? ''));
        }
    }

    public function getServiceName(): string
    {
        return 'Baiyi聚合支付';
    }

    public function getServiceType(): string
    {
        return 'Baiyi';
    }

    public function validateParams(array $params): bool
    {
        try {
            $hasAmount = isset($params['total_amount']) || isset($params['order_amount']);
            if (!isset($params['order_no']) || !$hasAmount || !isset($params['product_code'])) {
                return false;
            }
            return true;
        } catch (PaymentException $e) {
            return false;
        }
    }

    // ---- 统一响应抽象实现 ----
    protected function isResponseSuccess(array $response): bool
    {
        // 文档：code=1 为成功
        if (isset($response['code'])) {
            return (int)$response['code'] === 1;
        }
        
        // 处理HTML错误响应
        if (isset($response['raw_response'])) {
            $htmlResponse = $response['raw_response'];
            // 如果包含HTML错误页面，说明请求失败
            if (strpos($htmlResponse, '<html') !== false) {
                return false;
            }
        }
        
        return false;
    }

    protected function extractResponseMessage(array $response, bool $isSuccess): string
    {
        // 如果有标准的msg字段，直接返回
        if (isset($response['msg'])) {
            return $response['msg'];
        }
        
        // 处理HTML错误响应（如签名校验失败）
        if (isset($response['raw_response'])) {
            $htmlResponse = $response['raw_response'];
            // 提取HTML中的错误信息
            if (preg_match('/签名校验失败[^！]*！/', $htmlResponse, $matches)) {
                return $matches[0];
            }
            if (preg_match('/站点提示信息.*?<h3>站点提示信息<\/h3>([^<]+)/s', $htmlResponse, $matches)) {
                return trim(strip_tags($matches[1]));
            }
            // 如果包含HTML，说明是错误页面
            if (strpos($htmlResponse, '<html') !== false) {
                return '支付服务返回错误页面';
            }
        }
        
        return $isSuccess ? '请求成功' : '请求失败';
    }

    // ---- 参数构建与签名 ----
    private function buildPaymentParams(array $params): array
    {
        $orderNo = (string)$params['order_no'];
        $amount = $params['total_amount'] ?? $params['order_amount'] ?? 0;
        $amountYuan = number_format((float)$amount, 2, '.', '');
        $payType = (string)$params['product_code']; // 如 alipay / wxpay
        $clientIp = (string)($params['client_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        $device = (string)($params['device'] ?? $this->defaultDevice);
        $name = (string)($params['subject'] ?? '订单支付');
        $param = (string)($params['param'] ?? '');

        $base = [
            'pid' => $this->pid, // pid应该是字符串，不需要强制转换为整数
            'type' => $payType,
            'out_trade_no' => $orderNo,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'name' => $name,
            'money' => $amountYuan,
            'clientip' => $clientIp,
            'device' => $device,
            'param' => $param,
            'sign_type' => 'MD5'
        ];

        $base['sign'] = $this->generateBaiyiSignature($base);
        return $base;
    }

    private function generateBaiyiSignature(array $params): string
    {
        // 3、再将拼接好的字符串与商户密钥KEY进行MD5加密得出sign签名参数，sign = md5 ( a=b&c=d&e=f + KEY ) （注意：+ 为各语言的拼接符，不是字符！），md5结果为小写。
        $signString = $this->buildSignString($params) . $this->key;
        
        // 记录签名调试信息
        \support\Log::info('BaiyiService 签名生成', [
            'sign_string' => $signString,
            'key' => $this->key,
            'params' => $params
        ]);
        
        return strtolower(md5($signString));
    }

    private function verifyCallbackSignature(array $callbackData): bool
    {
        $sign = (string)($callbackData['sign'] ?? '');
        $signString = $this->buildSignString($callbackData) . $this->key;
        $calc = strtolower(md5($signString));
        
        // 记录回调签名验证调试信息
        \support\Log::info('BaiyiService 回调签名验证', [
            'received_sign' => $sign,
            'calculated_sign' => $calc,
            'sign_string' => $signString,
            'callback_data' => $callbackData
        ]);
        
        return hash_equals($calc, strtolower($sign));
    }

    private function buildSignString(array $params): string
    {
        // 1、将发送或接收到的所有参数按照参数名ASCII码从小到大排序（a-z），sign、sign_type、和空值不参与签名！
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type' || $v === '' || $v === null) {
                continue;
            }
            $filtered[$k] = $v;
        }
        
        // 按参数名ASCII码从小到大排序
        ksort($filtered);
        
        // 2、将排序后的参数拼接成URL键值对的格式，例如 a=b&c=d&e=f，参数值不要进行url编码。
        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        
        return implode('&', $pairs);
    }
}



