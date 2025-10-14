<?php

/**
 * 商户通知队列处理器启动脚本
 * 用于处理高并发商户通知
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\command\MerchantNotifyQueueCommand;
use support\Log;

// 设置进程标题
if (function_exists('cli_set_process_title')) {
    cli_set_process_title('merchant-notify-queue');
}

// 信号处理
$command = new MerchantNotifyQueueCommand();

// 注册信号处理器
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use ($command) {
        Log::info('收到SIGTERM信号，准备停止商户通知队列处理器');
        $command->stop();
    });
    
    pcntl_signal(SIGINT, function() use ($command) {
        Log::info('收到SIGINT信号，准备停止商户通知队列处理器');
        $command->stop();
    });
}

// 启动队列处理器
Log::info('启动商户通知队列处理器');
$command->start();



