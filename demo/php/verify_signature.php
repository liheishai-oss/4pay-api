<?php
/**
 * 验证签名算法的脚本
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$merchantKey = 'MCH_68F0E79CA6E42_20251016';
$merchantSecret = '353320d6149aba99bed365581d81547eda64487b819f605d8f6f8e236693139b';

// 测试数据
$data = [
    'merchant_key' => $merchantKey,
    'debug' => 0
];

echo "=== 验证签名算法 ===\n";
echo "原始数据:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// 使用系统的SignatureHelper
$signatureHelper = new \app\common\helpers\SignatureHelper();
$systemSignature = $signatureHelper->generate($data, $merchantSecret);

echo "系统生成的签名: " . $systemSignature . "\n\n";

// 手动生成签名
unset($data['sign']);
unset($data['client_ip']);
unset($data['entities_id']);

ksort($data);

$signString = '';
foreach ($data as $key => $value) {
    if ($value !== '' && $value !== null) {
        $signString .= (string)$value;
    }
}

$manualSignature = md5(hash_hmac('sha256', $signString, $merchantSecret));

echo "手动生成的签名: " . $manualSignature . "\n\n";

echo "签名是否匹配: " . ($systemSignature === $manualSignature ? '是' : '否') . "\n\n";

// 测试验证
$data['sign'] = $systemSignature;
$isValid = $signatureHelper->verify($data, $merchantSecret);

echo "签名验证结果: " . ($isValid ? '有效' : '无效') . "\n";
