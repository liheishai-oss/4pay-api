<?php
/**
 * 4Pay API 对接演示
 * 
 * 功能包含：
 * 1. 订单创建
 * 2. 订单查询  
 * 3. 余额查询
 * 
 * 使用方法：
 * php 4pay_demo.php
 */

class FourPayDemo
{
    // API配置
    private $apiBaseUrl = 'http://127.0.0.1:8787/api/v1';
    private $merchantKey = 'MCH_68F0E79CA6E42_20251016';
    private $merchantSecret = 'MCH_SECRET_68F0E79CA6E42_20251016'; // 实际商户密钥
    
    /**
     * 生成签名
     */
    private function generateSign(array $data, string $secret): string
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
        $signString .= 'key=' . $secret;
        
        // 生成MD5签名
        return strtoupper(md5($signString));
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
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => fopen('php://temp', 'w+')
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
    public function createOrder(): array
    {
        echo "=== 创建订单 ===\n";
        
        $orderData = [
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
        $orderData['sign'] = $this->generateSign($orderData, $this->merchantSecret);
        
        echo "请求数据:\n";
        echo json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/order/create', $orderData);
            
            echo "响应状态码: " . $response['http_code'] . "\n";
            echo "响应数据:\n";
            echo json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
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
        
        $queryData = [
            'merchant_key' => $this->merchantKey,
            'order_no' => $orderNo,
            'debug' => 0
        ];
        
        // 生成签名
        $queryData['sign'] = $this->generateSign($queryData, $this->merchantSecret);
        
        echo "请求数据:\n";
        echo json_encode($queryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/order/query', $queryData);
            
            echo "响应状态码: " . $response['http_code'] . "\n";
            echo "响应数据:\n";
            echo json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            return $response['data'];
            
        } catch (Exception $e) {
            echo "请求失败: " . $e->getMessage() . "\n\n";
            return [];
        }
    }
    
    /**
     * 查询余额
     */
    public function queryBalance(): array
    {
        echo "=== 查询余额 ===\n";
        
        $balanceData = [
            'merchant_key' => $this->merchantKey,
            'debug' => 0
        ];
        
        // 生成签名
        $balanceData['sign'] = $this->generateSign($balanceData, $this->merchantSecret);
        
        echo "请求数据:\n";
        echo json_encode($balanceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        try {
            $response = $this->sendRequest($this->apiBaseUrl . '/merchant/balance', $balanceData);
            
            echo "响应状态码: " . $response['http_code'] . "\n";
            echo "响应数据:\n";
            echo json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
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
        echo "4Pay API 对接演示\n";
        echo "==================\n\n";
        
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
    $demo = new FourPayDemo();
    $demo->runDemo();
} else {
    echo "请在命令行中运行此脚本\n";
}
