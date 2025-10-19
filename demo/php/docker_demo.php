<?php
/**
 * 4Pay API Docker环境演示
 * 在Docker容器内运行：docker exec -it php82 bash -c "cd /www/4pay/4pay-api/demo/php && php docker_demo.php"
 */

class FourPayDockerDemo
{
    private $apiBaseUrl = 'http://127.0.0.1:8787/api/v1';
    private $merchantKey = 'MCH_68F0E79CA6E42_20251016';
    private $merchantSecret = 'test_secret_key_123456';
    
    /**
     * 生成签名
     */
    private function generateSign(array $data): string
    {
        // 移除sign字段和其他不需要签名的字段
        unset($data['sign']);
        unset($data['client_ip']);
        unset($data['entities_id']);
        
        // 按键名排序
        ksort($data);
        
        // 构建签名字符串（只包含值，不包含键名）
        $signString = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= (string)$value;
            }
        }
        
        // 使用正确的签名算法：md5(hash_hmac('sha256', $stringToSign, $secretKey))
        return md5(hash_hmac('sha256', $signString, $this->merchantSecret));
    }
    
    /**
     * 发送HTTP请求
     */
    private function sendRequest(string $url, array $data): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL错误: " . $error);
        }
        
        return [
            'http_code' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * 查询余额
     */
    public function queryBalance(): array
    {
        echo "=== 查询余额 ===\n";
        
        $data = [
            'merchant_key' => $this->merchantKey,
            'debug' => 0
        ];
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        echo "请求数据: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/merchant/balance', $data);
            echo "HTTP状态码: " . $response['http_code'] . "\n";
            echo "响应数据: " . json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            return $response['data'];
        } catch (Exception $e) {
            echo "请求失败: " . $e->getMessage() . "\n\n";
            return [];
        }
    }
    
    /**
     * 创建订单
     */
    public function createOrder(): array
    {
        echo "=== 创建订单 ===\n";
        
        $data = [
            'merchant_key' => $this->merchantKey,
            'merchant_order_no' => 'DEMO_' . date('YmdHis') . '_' . rand(1000, 9999),
            'order_amount' => '1.00',
            'product_code' => '8416',
            'notify_url' => 'http://127.0.0.1/notify',
            'return_url' => 'https://example.com/return',
            'is_form' => 2,
            'terminal_ip' => '127.0.0.1',
            'payer_id' => 'DEMO_USER_' . rand(1000, 9999),
            'order_title' => '演示订单',
            'order_body' => '这是一个演示订单',
            'debug' => 0
        ];
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        echo "请求数据: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/order/create', $data);
            echo "HTTP状态码: " . $response['http_code'] . "\n";
            echo "响应数据: " . json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            return $response['data'];
        } catch (Exception $e) {
            echo "请求失败: " . $e->getMessage() . "\n\n";
            return [];
        }
    }
    
    /**
     * 查询订单
     */
    public function queryOrder(string $orderNo): array
    {
        echo "=== 查询订单 ===\n";
        
        $data = [
            'merchant_key' => $this->merchantKey,
            'order_no' => $orderNo,
            'debug' => 0
        ];
        
        // 生成签名
        $data['sign'] = $this->generateSign($data);
        
        echo "请求数据: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/order/query', $data);
            echo "HTTP状态码: " . $response['http_code'] . "\n";
            echo "响应数据: " . json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            return $response['data'];
        } catch (Exception $e) {
            echo "请求失败: " . $e->getMessage() . "\n\n";
            return [];
        }
    }
    
    /**
     * 运行完整演示
     */
    public function runDemo(): void
    {
        echo "4Pay API Docker环境演示\n";
        echo "======================\n\n";
        
        // 1. 查询余额
        $this->queryBalance();
        
        // 2. 创建订单
        $orderResult = $this->createOrder();
        
        // 3. 如果订单创建成功，查询订单状态
        if (isset($orderResult['data']['order_no'])) {
            $orderNo = $orderResult['data']['order_no'];
            echo "订单创建成功，订单号: {$orderNo}\n";
            echo "开始查询订单状态...\n\n";
            
            // 等待2秒后查询
            sleep(2);
            $this->queryOrder($orderNo);
        } else {
            echo "订单创建失败，跳过订单查询\n\n";
        }
        
        echo "演示完成！\n";
    }
}

// 运行演示
if (php_sapi_name() === 'cli') {
    $demo = new FourPayDockerDemo();
    $demo->runDemo();
} else {
    echo "请在命令行中运行此脚本\n";
}
