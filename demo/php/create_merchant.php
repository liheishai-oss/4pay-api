<?php
/**
 * 创建测试商户的脚本
 */

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    // 检查商户是否已存在
    $existingMerchant = \app\model\Merchant::where('merchant_key', 'MCH_test123456')->first();
    
    if ($existingMerchant) {
        echo "商户已存在:\n";
        echo "商户Key: " . $existingMerchant->merchant_key . "\n";
        echo "商户密钥: " . $existingMerchant->merchant_secret . "\n";
        echo "商户名称: " . $existingMerchant->merchant_name . "\n";
    } else {
        // 创建新商户
        $merchant = new \app\model\Merchant();
        $merchant->merchant_key = 'MCH_test123456';
        $merchant->merchant_secret = 'secret123456';
        $merchant->merchant_name = '测试商户';
        $merchant->login_account = 'test_merchant';
        $merchant->login_password = password_hash('123456', PASSWORD_DEFAULT);
        $merchant->withdrawable_amount = 100000; // 1000.00元
        $merchant->frozen_amount = 0;
        $merchant->status = 1;
        $merchant->save();
        
        echo "商户创建成功:\n";
        echo "商户Key: " . $merchant->merchant_key . "\n";
        echo "商户密钥: " . $merchant->merchant_secret . "\n";
        echo "商户名称: " . $merchant->merchant_name . "\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
