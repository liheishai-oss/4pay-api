<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\interfaces\PaymentServiceInterface;
use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 抽象支付服务基类
 * 提供通用的支付服务实现和观察者模式支持
 */
abstract class AbstractPaymentService implements PaymentServiceInterface
{
    protected array $config;
    protected array $observers = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 添加观察者
     * @param PaymentObserverInterface $observer
     * @return void
     */
    public function addObserver(PaymentObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * 移除观察者
     * @param PaymentObserverInterface $observer
     * @return void
     */
    public function removeObserver(PaymentObserverInterface $observer): void
    {
        $key = array_search($observer, $this->observers, true);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    /**
     * 通知所有观察者
     * @param string $event
     * @param PaymentResult $result
     * @return void
     */
    protected function notifyObservers(string $event, PaymentResult $result): void
    {
        foreach ($this->observers as $observer) {
            try {
                switch ($event) {
                    case 'payment_success':
                        $observer->onPaymentSuccess($result);
                        break;
                    case 'payment_failed':
                        $observer->onPaymentFailed($result);
                        break;
                    case 'payment_processing':
                        $observer->onPaymentProcessing($result);
                        break;
                    case 'refund_success':
                        $observer->onRefundSuccess($result);
                        break;
                    case 'refund_failed':
                        $observer->onRefundFailed($result);
                        break;
                }
            } catch (\Exception $e) {
                // 观察者通知失败不应影响主流程
                error_log("Observer notification failed: " . $e->getMessage());
            }
        }
    }

    /**
     * 获取配置值
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 验证必需参数
     * @param array $params
     * @param array $required
     * @return void
     * @throws PaymentException
     */
    protected function validateRequiredParams(array $params, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw PaymentException::invalidParams("缺少必需参数: {$field}", [
                    'required_fields' => $required,
                    'provided_fields' => array_keys($params)
                ]);
            }
        }
    }

    /**
     * 记录日志
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $logData = [
            'service' => $this->getServiceName(),
            'type' => $this->getServiceType(),
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 这里可以集成具体的日志系统
        error_log("[$level] " . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送HTTP请求
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param string $method
     * @return array
     * @throws PaymentException
     */
    protected function sendHttpRequest(string $url, array $data = [], array $headers = [], string $method = 'POST'): array
    {
        return $this->sendHttpRequestWithContentType($url, $data, $headers, $method, 'application/json');
    }

    /**
     * 发送JSON格式的HTTP请求
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param string $method
     * @return array
     * @throws PaymentException
     */
    protected function sendJsonRequest(string $url, array $data = [], array $headers = [], string $method = 'POST'): array
    {
        return $this->sendHttpRequestWithContentType($url, $data, $headers, $method, 'application/json');
    }

    /**
     * 发送表单格式的HTTP请求
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param string $method
     * @return array
     * @throws PaymentException
     */
    protected function sendFormRequest(string $url, array $data = [], array $headers = [], string $method = 'POST'): array
    {
        return $this->sendHttpRequestWithContentType($url, $data, $headers, $method, 'application/x-www-form-urlencoded');
    }

    /**
     * 发送XML格式的HTTP请求
     * @param string $url
     * @param string $xmlData
     * @param array $headers
     * @param string $method
     * @return array
     * @throws PaymentException
     */
    protected function sendXmlRequest(string $url, string $xmlData, array $headers = [], string $method = 'POST'): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/xml',
                'User-Agent: PaymentService/1.0'
            ], $headers)
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw PaymentException::networkError("HTTP请求失败: {$error}");
        }

        if ($httpCode >= 400) {
            throw PaymentException::networkError("HTTP请求失败，状态码: {$httpCode}", [
                'http_status' => $httpCode,
                'url' => $url,
                'method' => $method
            ]);
        }

        // 尝试解析XML响应
        $decodedResponse = $this->parseXmlResponse($response);
        return $decodedResponse;
    }

    /**
     * 发送指定内容类型的HTTP请求
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param string $method
     * @param string $contentType
     * @return array
     * @throws PaymentException
     */
    protected function sendHttpRequestWithContentType(string $url, array $data = [], array $headers = [], string $method = 'POST', string $contentType = 'application/json'): array
    {
        $ch = curl_init();
        
        // 根据内容类型处理数据
        $postData = $this->preparePostData($data, $contentType);
        
        // 构建完整的请求头
        $requestHeaders = array_merge([
            "Content-Type: {$contentType}",
            'User-Agent: PaymentService/1.0'
        ], $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $requestHeaders,
            // 连接健壮性：强制IPv4与HTTP/1.1，TLS优先1.2
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            // 增强SSL兼容性
            CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT, // 让cURL自动协商SSL版本
            CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1', // 降低安全级别以提高兼容性
        ]);

        // TCP keepalive，降低中间网络断开造成的 EOF
        if (defined('CURLOPT_TCP_KEEPALIVE')) {
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        }
        if (defined('CURLOPT_TCP_KEEPIDLE')) {
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 30);
        }
        if (defined('CURLOPT_TCP_KEEPINTVL')) {
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 10);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw PaymentException::networkError("HTTP请求失败: {$error}");
        }

        if ($httpCode >= 400) {
            throw PaymentException::networkError("HTTP请求失败，状态码: {$httpCode}", [
                'http_status' => $httpCode,
                'url' => $url,
                'method' => $method
            ]);
        }

        // 根据内容类型解析响应
        $parsedResponse = $this->parseResponse($response, $contentType);
        return $parsedResponse;
    }

    /**
     * 准备POST数据
     * @param array $data
     * @param string $contentType
     * @return string
     */
    private function preparePostData(array $data, string $contentType): string
    {
        switch ($contentType) {
            case 'application/x-www-form-urlencoded':
                return http_build_query($data);
            case 'application/json':
            default:
                return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 解析响应数据
     * @param string $response
     * @param string $contentType
     * @return array
     * @throws PaymentException
     */
    private function parseResponse(string $response, string $contentType): array
    {
        switch ($contentType) {
            case 'application/json':
                $decodedResponse = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw PaymentException::networkError("JSON响应解析失败: " . json_last_error_msg());
                }
                return $decodedResponse;
            
            case 'application/x-www-form-urlencoded':
                $decodedResponse = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // 如果不是JSON格式，尝试解析为表单数据
                    parse_str($response, $formData);
                    return $formData;
                }
                return $decodedResponse;

            default:
                // 首先尝试JSON解析
                $decodedResponse = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decodedResponse;
                }
                
                // 如果JSON解析失败，尝试表单解析
                parse_str($response, $decodedResponse);
                if (!empty($decodedResponse)) {
                    return $decodedResponse;
                }
                
                // 如果都失败，返回原始响应
                return ['raw_response' => $response];
        }
    }

    /**
     * 解析XML响应
     * @param string $xmlResponse
     * @return array
     * @throws PaymentException
     */
    private function parseXmlResponse(string $xmlResponse): array
    {
        // 禁用外部实体加载以防止XXE攻击
        $oldValue = libxml_disable_entity_loader(true);
        
        try {
            $xml = simplexml_load_string($xmlResponse, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                throw PaymentException::networkError("XML响应解析失败");
            }
            
            // 转换为数组
            $array = json_decode(json_encode($xml), true);
            libxml_disable_entity_loader($oldValue);
            
            return $array;
        } catch (\Exception $e) {
            libxml_disable_entity_loader($oldValue);
            throw PaymentException::networkError("XML响应解析失败: " . $e->getMessage());
        }
    }

    /**
     * 生成XML数据
     * @param array $data
     * @param string $rootElement
     * @return string
     */
    protected function generateXmlData(array $data, string $rootElement = 'request'): string
    {
        $xml = new \SimpleXMLElement("<{$rootElement}></{$rootElement}>");
        $this->arrayToXml($data, $xml);
        return $xml->asXML();
    }

    /**
     * 将数组转换为XML
     * @param array $data
     * @param \SimpleXMLElement $xml
     */
    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subNode = $xml->addChild($key);
                $this->arrayToXml($value, $subNode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * 生成签名
     * @param array $params
     * @param string $secret
     * @return string
     */
    protected function generateSignature(array $params, string $secret): string
    {
        // 移除空值和签名字段
        $params = array_filter($params, function($value) {
            return $value !== '' && $value !== null;
        });
        unset($params['sign']);

        // 按键名排序
        ksort($params);

        // 拼接字符串
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string = rtrim($string, '&');

        // 添加密钥
        $string .= '&key=' . $secret;

        // 生成签名
        return strtoupper(md5($string));
    }

    /**
     * 验证签名
     * @param array $params
     * @param string $secret
     * @param string $signature
     * @return bool
     */
    protected function verifySignature(array $params, string $secret, string $signature): bool
    {
        $expectedSignature = $this->generateSignature($params, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
