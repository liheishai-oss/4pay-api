<?php

require_once __DIR__ . '/vendor/autoload.php';

use app\service\robot\RobotService;
use app\service\robot\CommandParser;

// 初始化机器人服务
$robotService = new RobotService();
$commandParser = new CommandParser();

// 示例命令
$commands = [
    '预付+100',      // 增加100元预付
    '下发-50',       // 扣除50元预付
    '查余额1',       // 查询供应商ID为1的余额
    '查成率1',       // 查询供应商ID为1的通道成功率
];

echo "🤖 机器人命令执行示例\n";
echo "==================\n\n";

foreach ($commands as $command) {
    echo "执行命令: {$command}\n";
    echo "----------------------------------------\n";
    
    try {
        // 解析命令
        $parsed = $commandParser->parse($command);
        echo "解析结果: " . json_encode($parsed, JSON_UNESCAPED_UNICODE) . "\n";
        
        // 执行命令
        $result = $robotService->handleCommand($parsed['command'], $parsed['params']);
        echo "执行结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        
    } catch (Exception $e) {
        echo "执行失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 获取支持的命令列表
echo "支持的命令列表:\n";
echo "================\n";
$supportedCommands = $robotService->getSupportedCommands();
foreach ($supportedCommands as $command) {
    echo "- {$command}\n";
}

echo "\n命令帮助:\n";
echo "==========\n";
$help = $commandParser->getHelp();
foreach ($help as $item) {
    echo "命令: {$item['command']}\n";
    echo "描述: {$item['description']}\n";
    echo "示例: {$item['example']}\n\n";
}















