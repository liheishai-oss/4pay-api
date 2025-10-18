<?php

require_once __DIR__ . '/vendor/autoload.php';

use app\service\merchant\StoreService;
use app\model\Merchant;
use support\Db;

// 加载环境变量
loadEnvFile();

// 初始化数据库连接
$config = require __DIR__ . '/config/database.php';
Db::setConfig($config);

// 创建测试商户
$storeService = new StoreService();

/**
 * 加载 .env 文件
 */
function loadEnvFile()
{
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // 跳过注释行
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 移除引号
                if (($value[0] ?? '') === '"' && ($value[-1] ?? '') === '"') {
                    $value = substr($value, 1, -1);
                } elseif (($value[0] ?? '') === "'" && ($value[-1] ?? '') === "'") {
                    $value = substr($value, 1, -1);
                }
                
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

try {
    $merchantData = [
        'login_account' => 'test_merchant_001',
        'merchant_name' => '测试商户001',
        'status' => 1,
        'whitelist_ips' => ['127.0.0.1', '0.0.0.0/0']
    ];
    
    $merchant = $storeService->createMerchant($merchantData);
    
    echo "测试商户创建成功！\n";
    echo "商户ID: " . $merchant->id . "\n";
    echo "商户Key: " . $merchant->merchant_key . "\n";
    echo "商户密钥: " . $merchant->merchant_secret . "\n";
    echo "登录账号: " . $merchant->login_account . "\n";
    echo "商户名称: " . $merchant->merchant_name . "\n";
    
} catch (Exception $e) {
    echo "创建测试商户失败: " . $e->getMessage() . "\n";
}
