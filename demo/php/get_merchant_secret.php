<?php
/**
 * 获取商户密钥的脚本
 */

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    $merchant = \app\model\Merchant::where('merchant_key', 'MCH_68F0E79CA6E42_20251016')->first();
    
    if ($merchant) {
        echo "商户Key: " . $merchant->merchant_key . "\n";
        echo "商户密钥: " . $merchant->merchant_secret . "\n";
        echo "商户名称: " . $merchant->merchant_name . "\n";
    } else {
        echo "未找到商户信息\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
