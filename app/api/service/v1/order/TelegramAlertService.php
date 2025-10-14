<?php

namespace app\api\service\v1\order;

use support\Log;
use support\Redis;

class TelegramAlertService
{
    private $botToken;
    private $chatIds;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->chatIds = config('telegram.alert_chat_ids', []);
    }

    /**
     * 发送全部通道失败告警
     * @param array $merchantInfo
     * @param array $productInfo
     * @param array $orderData
     * @param array $failedChannels
     * @param string|null $traceId
     * @return bool
     */
    public function sendAllChannelsFailedAlert(array $merchantInfo, array $productInfo, array $orderData, array $failedChannels, ?string $traceId = null): bool
    {
        $message = $this->buildAllChannelsFailedMessage($merchantInfo, $productInfo, $orderData, $failedChannels, $traceId);
        return $this->sendAlert('all_channels_failed', $message);
    }

    /**
     * 发送产品未配置告警
     * @param array $merchantInfo
     * @param int $productId
     * @param array $orderData
     * @param string|null $traceId
     * @return bool
     */
    public function sendProductNotConfiguredAlert(array $merchantInfo, int $productId, array $orderData, ?string $traceId = null): bool
    {
        $message = $this->buildProductNotConfiguredMessage($merchantInfo, $productId, $orderData, $traceId);
        return $this->sendAlert('product_not_configured', $message);
    }

    /**
     * 发送轮询池不可用告警
     * @param array $merchantInfo
     * @param array $productInfo
     * @param array $orderData
     * @param string|null $traceId
     * @return bool
     */
    public function sendNoAvailablePoolAlert(array $merchantInfo, array $productInfo, array $orderData, ?string $traceId = null): bool
    {
        $message = $this->buildNoAvailablePoolMessage($merchantInfo, $productInfo, $orderData, $traceId);
        return $this->sendAlert('no_available_polling_pool', $message);
    }

    /**
     * 发送慢响应告警
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function sendSlowResponseAlert(string $message, array $context = []): bool
    {
        return $this->sendAlert('slow_response', $message);
    }

    /**
     * 发送非正常响应告警
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function sendAbnormalResponseAlert(string $message, array $context = []): bool
    {
        return $this->sendAlert('abnormal_response', $message);
    }

    /**
     * 发送供应商请求失败告警
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendSupplierRequestFailedAlert(string $supplierName, string $channelName, string $error, array $context = []): bool
    {
        $message = $this->buildSupplierRequestFailedMessage($supplierName, $channelName, $error, $context);
        return $this->sendAlert('supplier_request_failed', $message);
    }

    /**
     * 发送供应商超时告警
     * @param string $supplierName
     * @param string $channelName
     * @param float $responseTime
     * @param array $context
     * @return bool
     */
    public function sendSupplierTimeoutAlert(string $supplierName, string $channelName, float $responseTime, array $context = []): bool
    {
        $message = $this->buildSupplierTimeoutMessage($supplierName, $channelName, $responseTime, $context);
        return $this->sendAlert('supplier_timeout', $message);
    }

    /**
     * 发送供应商连接错误告警
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendSupplierConnectionErrorAlert(string $supplierName, string $channelName, string $error, array $context = []): bool
    {
        $message = $this->buildSupplierConnectionErrorMessage($supplierName, $channelName, $error, $context);
        return $this->sendAlert('supplier_connection_error', $message);
    }

    /**
     * 发送供应商认证失败告警
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendSupplierAuthFailedAlert(string $supplierName, string $channelName, string $error, array $context = []): bool
    {
        $message = $this->buildSupplierAuthFailedMessage($supplierName, $channelName, $error, $context);
        return $this->sendAlert('supplier_auth_failed', $message);
    }

    /**
     * 发送供应商配置错误告警
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendSupplierConfigErrorAlert(string $supplierName, string $channelName, string $error, array $context = []): bool
    {
        $message = $this->buildSupplierConfigErrorMessage($supplierName, $channelName, $error, $context);
        return $this->sendAlert('supplier_config_error', $message);
    }

    /**
     * 发送数据库错误告警
     * @param string $operation
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendDatabaseErrorAlert(string $operation, string $error, array $context = []): bool
    {
        $message = $this->buildDatabaseErrorMessage($operation, $error, $context);
        return $this->sendAlert('database_error', $message);
    }

    /**
     * 发送缓存错误告警
     * @param string $operation
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendCacheErrorAlert(string $operation, string $error, array $context = []): bool
    {
        $message = $this->buildCacheErrorMessage($operation, $error, $context);
        return $this->sendAlert('cache_error', $message);
    }

    /**
     * 发送支付网关宕机告警
     * @param string $gatewayName
     * @param string $error
     * @param array $context
     * @return bool
     */
    public function sendPaymentGatewayDownAlert(string $gatewayName, string $error, array $context = []): bool
    {
        $message = $this->buildPaymentGatewayDownMessage($gatewayName, $error, $context);
        return $this->sendAlert('payment_gateway_down', $message);
    }

    /**
     * 构建全部通道失败消息
     * @param array $merchantInfo
     * @param array $productInfo
     * @param array $orderData
     * @param array $failedChannels
     * @return string
     */
    private function buildAllChannelsFailedMessage(array $merchantInfo, array $productInfo, array $orderData, array $failedChannels, ?string $traceId = null): string
    {
        $failedChannelsText = '';
        foreach ($failedChannels as $channel) {
            $failedChannelsText .= "• {$channel['name']} (ID: {$channel['id']}): " . str_replace(['*', '_', '`', '[', ']'], '', $channel['error']) . "\n";
        }

        $traceIdText = $traceId ? "\n追踪ID: {$traceId}" : '';

        return "🚨 支付通道全部失败\n\n" .
               "商户: {$merchantInfo['merchant_name']} (ID: {$merchantInfo['id']})\n" .
               "商品: {$productInfo['product_name']} (ID: {$productInfo['id']})\n" .
               "订单号: {$orderData['merchant_order_no']}\n" .
               "金额: ¥{$orderData['order_amount']}{$traceIdText}\n" .
               "失败通道数: " . count($failedChannels) . "\n" .
               "失败时间: " . date('Y-m-d H:i:s') . "\n\n" .
               "失败详情:\n{$failedChannelsText}";
    }

    /**
     * 构建产品未配置消息
     * @param array $merchantInfo
     * @param int $productId
     * @param array $orderData
     * @return string
     */
    private function buildProductNotConfiguredMessage(array $merchantInfo, int $productId, array $orderData, ?string $traceId = null): string
    {
        $traceIdText = $traceId ? "\n追踪ID: {$traceId}" : '';
        
        return "⚠️ 商品未配置\n\n" .
               "商户: {$merchantInfo['merchant_name']} (ID: {$merchantInfo['id']})\n" .
               "商品ID: {$productId}\n" .
               "订单号: {$orderData['merchant_order_no']}\n" .
               "金额: ¥{$orderData['order_amount']}{$traceIdText}\n" .
               "错误: 商品不存在或已被删除\n" .
               "失败时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建轮询池不可用消息
     * @param array $merchantInfo
     * @param array $productInfo
     * @param array $orderData
     * @return string
     */
    private function buildNoAvailablePoolMessage(array $merchantInfo, array $productInfo, array $orderData, ?string $traceId = null): string
    {
        $traceIdText = $traceId ? "\n追踪ID: {$traceId}" : '';
        
        return "⚠️ 商品轮询池不可用\n\n" .
               "商户: {$merchantInfo['merchant_name']} (ID: {$merchantInfo['id']})\n" .
               "商品: {$productInfo['product_name']} (ID: {$productInfo['id']})\n" .
               "订单号: {$orderData['merchant_order_no']}\n" .
               "金额: ¥{$orderData['order_amount']}{$traceIdText}\n" .
               "错误: 商品轮询池为空或所有通道都被禁用\n" .
               "失败时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 发送告警消息
     * @param string $alertType
     * @param string $message
     * @return bool
     */
    private function sendAlert(string $alertType, string $message): bool
    {
        if (!$this->botToken || empty($this->chatIds)) {
            Log::warning('Telegram告警配置不完整', [
                'bot_token' => $this->botToken ? '已配置' : '未配置',
                'chat_ids' => count($this->chatIds)
            ]);
            return false;
        }

        // 系统级错误立即推送，不受频率限制
        $criticalAlerts = config('telegram.critical_alerts', []);
        if (!isset($criticalAlerts[$alertType]) || !$criticalAlerts[$alertType]) {
            // 非系统级错误才检查频率限制
            if (!$this->checkRateLimit($alertType)) {
                Log::info('Telegram告警被频率限制', ['alert_type' => $alertType]);
                return false;
            }
        } else {
            Log::info('系统级错误告警，跳过频率限制', ['alert_type' => $alertType]);
        }

        $success = true;
        // 去重 chat_id，避免重复发送
        $uniqueChatIds = array_unique(array_values($this->chatIds));
        
        Log::info('Telegram告警发送开始', [
            'alert_type' => $alertType,
            'total_chat_ids' => count($this->chatIds),
            'unique_chat_ids' => count($uniqueChatIds),
            'chat_ids' => $uniqueChatIds
        ]);
        
        foreach ($uniqueChatIds as $chatId) {
            if (!$this->sendToTelegram($chatId, $message)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 发送到Telegram
     * @param string $chatId
     * @param string $message
     * @return bool
     */
    private function sendToTelegram(string $chatId, string $message): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => $message
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Log::info('Telegram告警发送成功', ['chat_id' => $chatId]);
                return true;
            } else {
                Log::error('Telegram告警发送失败', [
                    'chat_id' => $chatId,
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram告警发送异常', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查频率限制
     * @param string $alertType
     * @return bool
     */
    private function checkRateLimit(string $alertType): bool
    {
        try {
            $key = "telegram_alert_rate_limit:{$alertType}:" . date('Y-m-d H:i');
            
                $limits = [
                    'all_channels_failed' => 10,
                    'product_not_configured' => 5,
                    'no_available_polling_pool' => 5,
                    'slow_response' => 20,
                    'abnormal_response' => 15
                ];

            $limit = $limits[$alertType] ?? 5;
            
            // 使用 Redis 原子操作检查并增加计数（仅在Redis正常时）
            try {
                $currentCount = Redis::incr($key);
                
                // 如果是第一次设置，设置过期时间为2分钟
                if ($currentCount === 1) {
                    Redis::expire($key, 120);
                }
            } catch (\Throwable $redisException) {
                Log::warning('Redis操作失败，跳过频率限制检查', [
                    'alert_type' => $alertType,
                    'key' => $key,
                    'error' => $redisException->getMessage()
                ]);
                // Redis异常时允许发送告警
                return true;
            }
            
            if ($currentCount > $limit) {
                Log::info('Telegram告警被频率限制', [
                    'alert_type' => $alertType,
                    'current_count' => $currentCount,
                    'limit' => $limit
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram频率限制检查失败', [
                'alert_type' => $alertType,
                'error' => $e->getMessage()
            ]);
            // 如果 Redis 失败，允许发送（避免因为缓存问题导致告警失效）
            return true;
        }
    }

    /**
     * 构建供应商请求失败消息
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildSupplierRequestFailedMessage(string $supplierName, string $channelName, string $error, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "🚨 供应商请求失败\n\n" .
               "供应商: {$supplierName}\n" .
               "通道: {$channelName}\n" .
               $orderInfo .
               $amountInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建供应商超时消息
     * @param string $supplierName
     * @param string $channelName
     * @param float $responseTime
     * @param array $context
     * @return string
     */
    private function buildSupplierTimeoutMessage(string $supplierName, string $channelName, float $responseTime, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "⏰ 供应商请求超时\n\n" .
               "供应商: {$supplierName}\n" .
               "通道: {$channelName}\n" .
               $orderInfo .
               $amountInfo .
               "响应时间: {$responseTime}秒\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建供应商连接错误消息
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildSupplierConnectionErrorMessage(string $supplierName, string $channelName, string $error, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "🔌 供应商连接错误\n\n" .
               "供应商: {$supplierName}\n" .
               "通道: {$channelName}\n" .
               $orderInfo .
               $amountInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建供应商认证失败消息
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildSupplierAuthFailedMessage(string $supplierName, string $channelName, string $error, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "🔐 供应商认证失败\n\n" .
               "供应商: {$supplierName}\n" .
               "通道: {$channelName}\n" .
               $orderInfo .
               $amountInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建供应商配置错误消息
     * @param string $supplierName
     * @param string $channelName
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildSupplierConfigErrorMessage(string $supplierName, string $channelName, string $error, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "⚙️ 供应商配置错误\n\n" .
               "供应商: {$supplierName}\n" .
               "通道: {$channelName}\n" .
               $orderInfo .
               $amountInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建数据库错误消息
     * @param string $operation
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildDatabaseErrorMessage(string $operation, string $error, array $context = []): string
    {
        $tableInfo = isset($context['table']) ? "表: {$context['table']}\n" : '';
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        
        return "🗄️ 数据库错误\n\n" .
               "操作: {$operation}\n" .
               $tableInfo .
               $orderInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建缓存错误消息
     * @param string $operation
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildCacheErrorMessage(string $operation, string $error, array $context = []): string
    {
        $keyInfo = isset($context['cache_key']) ? "缓存键: `{$context['cache_key']}`\n" : '';
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        
        return "💾 缓存错误\n\n" .
               "操作: {$operation}\n" .
               $keyInfo .
               $orderInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 构建支付网关宕机消息
     * @param string $gatewayName
     * @param string $error
     * @param array $context
     * @return string
     */
    private function buildPaymentGatewayDownMessage(string $gatewayName, string $error, array $context = []): string
    {
        $orderInfo = isset($context['merchant_order_no']) ? "订单号: `{$context['merchant_order_no']}`\n" : '';
        $amountInfo = isset($context['order_amount']) ? "金额: `¥{$context['order_amount']}`\n" : '';
        
        return "🚫 支付网关宕机\n\n" .
               "网关: {$gatewayName}\n" .
               $orderInfo .
               $amountInfo .
               "错误: " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n" .
               "时间: " . date('Y-m-d H:i:s');
    }
}
