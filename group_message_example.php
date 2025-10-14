<?php

require_once __DIR__ . '/vendor/autoload.php';

use app\service\robot\GroupMessageListener;

// 初始化群消息监听器
$listener = new GroupMessageListener();

// 模拟群消息
$groupMessages = [
    '/帮助',         // 显示帮助信息
    '/预付+100',     // 增加100元预付
    '/下发-50',      // 扣除50元预付
    '/查余额',       // 查询当前群组余额
    '/查成率1',      // 查询供应商ID为1的通道成功率
    '/无效命令',     // 测试错误处理
];

echo "🤖 群消息机器人监听示例\n";
echo "======================\n\n";

foreach ($groupMessages as $message) {
    echo "📨 收到群消息: {$message}\n";
    echo "----------------------------------------\n";
    
    try {
        // 模拟群消息上下文
        $context = [
            'group_id' => '123456789',
            'sender_id' => 'user123',
            'sender_name' => '管理员',
            'timestamp' => time()
        ];
        
        // 处理群消息
        $result = $listener->handleGroupMessage($message, $context);
        
        if ($result['success']) {
            echo "✅ 处理成功\n";
            echo "命令: {$result['command']}\n";
            echo "回复消息:\n{$result['reply_message']}\n";
        } else {
            echo "❌ 处理失败\n";
            echo "错误: {$result['error']}\n";
            echo "回复消息:\n{$result['reply_message']}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 系统错误: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 获取支持的命令列表
echo "支持的命令列表:\n";
echo "================\n";
$supportedCommands = $listener->getSupportedCommands();
foreach ($supportedCommands as $command) {
    echo "- {$command}\n";
}

echo "\n💡 使用说明:\n";
echo "============\n";
echo "1. 在群中发送 '/帮助' 来查看所有可用命令\n";
echo "2. 在群中发送 '/预付+100' 来增加100元预付\n";
echo "3. 在群中发送 '/下发-50' 来扣除50元预付\n";
echo "4. 在群中发送 '/查余额' 来查询当前群组余额\n";
echo "5. 在群中发送 '/查成率1' 来查询供应商ID为1的通道成功率\n";
echo "6. 机器人会自动处理命令并推送结果到群中\n";
