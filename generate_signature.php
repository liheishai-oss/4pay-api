<?php

/**
 * 生成API请求签名
 */

function generateSignature(array $params, string $secretKey): string
{
    // 排除sign字段，按key排序
    ksort($params);
    $stringToSign = '';
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'client_ip' || $k === 'entities_id') {
            continue;
        }
        $stringToSign .= (string)($v ?? '');
    }
    
    // 使用HMAC-SHA256 + MD5
    return md5(hash_hmac('sha256', $stringToSign, $secretKey));
}

// 测试数据
$params = [
    'merchant_key' => 'MCH_68F0E79CA6E42_20251016',
    'merchant_order_no' => 'TEST_ORDER_' . time(),
    'order_amount' => '1.00',
    'product_code' => '8416',
    'notify_url' => 'https://api.baiyi-pay.com/notify',
    'return_url' => 'https://example.com/return',
    'terminal_ip' => '127.0.0.1',
    'debug' => '1',
    'timestamp' => time(),
];

// 假设的商户密钥（实际需要从数据库获取）
$secretKey = 'test_secret_key_123456';

// 生成签名
$signature = generateSignature($params, $secretKey);
$params['sign'] = $signature;

echo "生成的签名: " . $signature . "\n";
echo "完整请求参数:\n";
echo json_encode($params, JSON_PRETTY_PRINT) . "\n";
