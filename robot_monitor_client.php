<?php

/**
 * 机器人消息监控WebSocket客户端测试工具
 * 
 * 使用方法:
 * php robot_monitor_client.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

class RobotMonitorClient
{
    private $connection;
    private $isConnected = false;

    public function __construct()
    {
        echo "🤖 机器人消息监控客户端启动\n";
        echo "======================\n\n";
    }

    public function connect()
    {
        $this->connection = new AsyncTcpConnection('ws://127.0.0.1:8789');
        
        $this->connection->onConnect = function($connection) {
            echo "✅ 已连接到机器人监控服务\n\n";
            $this->isConnected = true;
            $this->showMenu();
        };
        
        $this->connection->onMessage = function($connection, $data) {
            $this->handleMessage($data);
        };
        
        $this->connection->onClose = function($connection) {
            echo "❌ 连接已断开\n";
            $this->isConnected = false;
        };
        
        $this->connection->onError = function($connection, $code, $msg) {
            echo "❌ 连接错误: {$code} - {$msg}\n";
        };
        
        $this->connection->connect();
    }

    private function handleMessage($data)
    {
        $message = json_decode($data, true);
        
        if (!$message) {
            echo "📨 收到原始消息: {$data}\n\n";
            return;
        }
        
        $type = $message['type'] ?? 'unknown';
        $timestamp = $message['timestamp'] ?? date('Y-m-d H:i:s');
        
        echo "📨 收到消息 [{$type}] [{$timestamp}]:\n";
        
        switch ($type) {
            case 'welcome':
                echo "   🎉 {$message['message']}\n";
                echo "   🔗 连接ID: {$message['connection_id']}\n";
                break;
                
            case 'status':
                echo "   📊 {$message['message']}\n";
                echo "   📋 支持的命令: " . implode(', ', $message['supported_commands']) . "\n";
                break;
                
            case 'group_message_result':
                $success = $message['success'] ? '✅' : '❌';
                echo "   {$success} 群消息处理结果:\n";
                echo "   📝 消息: {$message['message']}\n";
                if (isset($message['command'])) {
                    echo "   🎯 命令: {$message['command']}\n";
                }
                if (isset($message['error'])) {
                    echo "   ❌ 错误: {$message['error']}\n";
                }
                break;
                
            case 'pong':
                echo "   🏓 Pong响应\n";
                break;
                
            case 'status_response':
                echo "   📊 服务状态: {$message['status']}\n";
                echo "   ⏱️  运行时间: {$message['uptime']}秒\n";
                echo "   🔗 连接数: {$message['connections']}\n";
                break;
                
            case 'error':
                echo "   ❌ 错误: {$message['message']}\n";
                break;
                
            default:
                echo "   📄 数据: " . json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
        
        echo "\n";
    }

    private function showMenu()
    {
        echo "📋 可用操作:\n";
        echo "1. 发送群消息\n";
        echo "2. 发送ping\n";
        echo "3. 获取状态\n";
        echo "4. 发送帮助命令\n";
        echo "5. 发送预付命令\n";
        echo "6. 发送下发命令\n";
        echo "7. 发送查余额命令\n";
        echo "8. 发送查成功率命令\n";
        echo "9. 退出\n";
        echo "请输入选项 (1-9): ";
        
        $this->handleInput();
    }

    private function handleInput()
    {
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        switch ($input) {
            case '1':
                $this->sendGroupMessage();
                break;
            case '2':
                $this->sendPing();
                break;
            case '3':
                $this->getStatus();
                break;
            case '4':
                $this->sendHelpCommand();
                break;
            case '5':
                $this->sendPrepaymentCommand();
                break;
            case '6':
                $this->sendPayoutCommand();
                break;
            case '7':
                $this->sendBalanceQueryCommand();
                break;
            case '8':
                $this->sendSuccessRateCommand();
                break;
            case '9':
                $this->disconnect();
                return;
            default:
                echo "❌ 无效选项，请重新选择\n\n";
                $this->showMenu();
                return;
        }
        
        // 继续显示菜单
        $this->showMenu();
    }

    private function sendGroupMessage()
    {
        echo "请输入群组ID: ";
        $handle = fopen("php://stdin", "r");
        $groupId = trim(fgets($handle));
        fclose($handle);
        
        echo "请输入用户ID: ";
        $handle = fopen("php://stdin", "r");
        $userId = trim(fgets($handle));
        fclose($handle);
        
        echo "请输入消息内容: ";
        $handle = fopen("php://stdin", "r");
        $message = trim(fgets($handle));
        fclose($handle);
        
        $data = [
            'type' => 'group_message',
            'message' => $message,
            'group_id' => $groupId,
            'user_id' => $userId
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送群消息\n\n";
    }

    private function sendPing()
    {
        $data = ['type' => 'ping'];
        $this->connection->send(json_encode($data));
        echo "📤 已发送ping\n\n";
    }

    private function getStatus()
    {
        $data = ['type' => 'get_status'];
        $this->connection->send(json_encode($data));
        echo "📤 已请求状态\n\n";
    }

    private function sendHelpCommand()
    {
        $data = [
            'type' => 'group_message',
            'message' => '/帮助',
            'group_id' => 'test_group_001',
            'user_id' => 'test_user_001'
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送帮助命令\n\n";
    }

    private function sendPrepaymentCommand()
    {
        echo "请输入预付金额: ";
        $handle = fopen("php://stdin", "r");
        $amount = trim(fgets($handle));
        fclose($handle);
        
        $data = [
            'type' => 'group_message',
            'message' => "/预付+{$amount}",
            'group_id' => 'test_group_001',
            'user_id' => 'test_user_001'
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送预付命令: /预付+{$amount}\n\n";
    }

    private function sendPayoutCommand()
    {
        echo "请输入下发金额: ";
        $handle = fopen("php://stdin", "r");
        $amount = trim(fgets($handle));
        fclose($handle);
        
        $data = [
            'type' => 'group_message',
            'message' => "/下发-{$amount}",
            'group_id' => 'test_group_001',
            'user_id' => 'test_user_001'
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送下发命令: /下发-{$amount}\n\n";
    }

    private function sendBalanceQueryCommand()
    {
        $data = [
            'type' => 'group_message',
            'message' => '/查余额',
            'group_id' => 'test_group_001',
            'user_id' => 'test_user_001'
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送查余额命令\n\n";
    }

    private function sendSuccessRateCommand()
    {
        echo "请输入供应商ID (可选，直接回车使用默认值1): ";
        $handle = fopen("php://stdin", "r");
        $supplierId = trim(fgets($handle));
        fclose($handle);
        
        if (empty($supplierId)) {
            $supplierId = '1';
        }
        
        $data = [
            'type' => 'group_message',
            'message' => "/查成率{$supplierId}",
            'group_id' => 'test_group_001',
            'user_id' => 'test_user_001'
        ];
        
        $this->connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
        echo "📤 已发送查成功率命令: /查成率{$supplierId}\n\n";
    }

    private function disconnect()
    {
        echo "👋 正在断开连接...\n";
        $this->connection->close();
        echo "✅ 已断开连接\n";
        exit(0);
    }

    public function run()
    {
        $this->connect();
        
        // 保持连接
        Worker::runAll();
    }
}

// 运行客户端
$client = new RobotMonitorClient();
$client->run();















