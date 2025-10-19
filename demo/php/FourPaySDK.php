<?php
/**
 * 4Pay API SDK
 * 
 * 提供完整的API对接功能
 */

class FourPaySDK
{
    private $config;
    private $apiBaseUrl;
    private $merchantKey;
    private $merchantSecret;
    
    public function __construct(array $config = null)
    {
        $this->config = $config ?: require __DIR__ . '/config.php';
        $this->apiBaseUrl = $this->config['api_base_url'];
        $this->merchantKey = $this->config['merchant']['key'];
        $this->merchantSecret = $this->config['merchant']['secret'];
    }
    
    /**
     * 生成签名
     */
    public function generateSign(array $data): string
    {
        // 移除sign字段
        unset($data['sign']);
        
        // 按键名排序
        ksort($data);
        
        // 构建签名字符串
        $signString = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= $key . '=' . $value . '&';
            }
        }
        
        // 添加密钥
        $signString .= 'key=' . $this->merchantSecret;
        
        // 生成MD5签名
        return strtoupper(md5($signString));
    }
    
    
    /**
     * 发送HTTP请求
     */
    private function sendRequest(string $endpoint, array $data): array
    {
        $url = $this->apiBaseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => $this->config['request']['timeout'],
            CURLOPT_CONNECTTIMEOUT => $this->config['request']['connect_timeout']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL错误: " . $error);
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON解析错误: " . json_last_error_msg());
        }
        
        return [
            'http_code' => $httpCode,
            'data' => $result
        ];
    }
    
    /**
     * 创建订单
     */
    public function createOrder(array $orderData): array
    {
        // 基础数据
        $data = [
            'merchant_key' => $this->merchantKey,
            'merchant_order_no' => $orderData['merchant_order_no'] ?? 'ORDER_' . date('YmdHis') . '_' . rand(1000, 9999),
            'order_amount' => $orderData['order_amount'] ?? '1.00',
            'product_code' => $orderData['product_code'] ?? '8416',
            'notify_url' => $orderData['notify_url'] ?? $this->config['callback']['notify_url'],
            'return_url' => $orderData['return_url'] ?? $this->config['callback']['return_url'],
            'is_form' => $orderData['is_form'] ?? 2,
            'terminal_ip' => $orderData['terminal_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'payer_id' => $orderData['payer_id'] ?? 'USER_' . rand(1000, 9999),
            'order_title' => $orderData['order_title'] ?? '订单支付',
            'order_body' => $orderData['order_body'] ?? '订单描述',
            'debug' => $orderData['debug'] ?? 0
        ];
        
        // 添加扩展数据
        if (isset($orderData['extra_data'])) {
            $data['extra_data'] = is_array($orderData['extra_data']) 
                ? json_encode($orderData['extra_data']) 
                : $orderData['extra_data'];
        }
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        return $this->sendRequest('/order/create', $data);
    }
    
    /**
     * 查询订单
     */
    public function queryOrder(string $orderNo): array
    {
        $data = [
            'merchant_key' => $this->merchantKey,
            'order_no' => $orderNo,
            'debug' => 0
        ];
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        return $this->sendRequest('/order/query', $data);
    }
    
    /**
     * 查询余额
     */
    public function queryBalance(): array
    {
        $data = [
            'merchant_key' => $this->merchantKey,
            'debug' => 0
        ];
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        return $this->sendRequest('/merchant/balance', $data);
    }
    
    /**
     * 验证回调签名
     */
    public function verifyCallback(array $callbackData): bool
    {
        if (!isset($callbackData['sign'])) {
            return false;
        }
        
        $sign = $callbackData['sign'];
        unset($callbackData['sign']);
        
        $expectedSign = $this->generateSign($callbackData);
        
        return $sign === $expectedSign;
    }
    
    /**
     * 获取产品列表
     */
    public function getProducts(): array
    {
        return $this->config['products'];
    }
    
    /**
     * 格式化金额
     */
    public function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
    
    /**
     * 验证订单数据
     */
    public function validateOrderData(array $orderData): array
    {
        $errors = [];
        
        // 验证金额
        if (isset($orderData['order_amount'])) {
            $amount = floatval($orderData['order_amount']);
            if ($amount < floatval($this->config['order']['amount_min'])) {
                $errors[] = '订单金额不能小于' . $this->config['order']['amount_min'];
            }
            if ($amount > floatval($this->config['order']['amount_max'])) {
                $errors[] = '订单金额不能大于' . $this->config['order']['amount_max'];
            }
        }
        
        // 验证标题长度
        if (isset($orderData['order_title'])) {
            if (strlen($orderData['order_title']) > $this->config['order']['title_max_length']) {
                $errors[] = '订单标题不能超过' . $this->config['order']['title_max_length'] . '个字符';
            }
        }
        
        // 验证描述长度
        if (isset($orderData['order_body'])) {
            if (strlen($orderData['order_body']) > $this->config['order']['body_max_length']) {
                $errors[] = '订单描述不能超过' . $this->config['order']['body_max_length'] . '个字符';
            }
        }
        
        return $errors;
    }
}
