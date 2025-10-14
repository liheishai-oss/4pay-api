<?php
/**
 * 订单超时检查任务启动脚本
 * 用于定期检查订单是否超时并自动关闭
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\command\OrderTimeoutCheckCommand;
use support\Log;

// 设置错误处理
set_error_handler(function($severity, $message, $file, $line) {
    Log::error('订单超时检查任务错误', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line
    ]);
});

// 设置异常处理
set_exception_handler(function($exception) {
    Log::error('订单超时检查任务异常', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});

// 设置信号处理
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        Log::info('收到SIGTERM信号，准备停止订单超时检查任务');
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() {
        Log::info('收到SIGINT信号，准备停止订单超时检查任务');
        exit(0);
    });
}

echo "=== 订单超时检查任务启动 ===\n";
echo "启动时间: " . date('Y-m-d H:i:s') . "\n";
echo "检查间隔: 每5分钟执行一次\n\n";

$command = new OrderTimeoutCheckCommand();
$checkInterval = 300; // 5分钟 = 300秒

// 首次执行
echo "执行首次检查...\n";
$command->start();

// 显示统计信息
$stats = $command->getStats();
echo "统计信息:\n";
foreach ($stats as $key => $value) {
    echo "  {$key}: {$value}\n";
}
echo "\n";

// 循环执行
while (true) {
    sleep($checkInterval);
    
    // 处理信号
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 执行订单超时检查...\n";
    
    try {
        $command->start();
        
        // 显示统计信息
        $stats = $command->getStats();
        echo "统计信息:\n";
        foreach ($stats as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "执行失败: " . $e->getMessage() . "\n";
        Log::error('订单超时检查任务执行失败', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}



