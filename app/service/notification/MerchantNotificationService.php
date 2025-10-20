<?php

namespace app\service\notification;

use app\model\Order;
use app\model\NotifyLog;
use support\Log;
use support\Redis;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use app\service\TraceService;
use app\common\helpers\TraceIdHelper;

/**
 * 商户通知服务 - 高并发版本
 * 支持异步通知、重试机制、并发控制
 */
class MerchantNotificationService
{
    private $httpClient;
    private $maxConcurrent = 50; // 最大并发数
    private $timeout = 10; // 请求超时时间
    private $retryTimes = 3; // 重试次数
    private $retryDelay = [1, 3, 5]; // 重试延迟（秒）
    
    // 商户隔离配置
    private $merchantTimeoutMap = []; // 商户超时记录
    private $merchantFailureCount = []; // 商户失败次数
    private $maxFailureCount = 5; // 最大失败次数
    private $circuitBreakerTimeout = 300; // 熔断器超时时间（秒）
    private $slowMerchantThreshold = 3; // 慢商户阈值（秒）
    
    // 机器人告警配置
    private $telegramAlertService;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'connect_timeout' => 5,
            'verify' => false, // 生产环境建议开启SSL验证
            'http_errors' => false, // 不抛出HTTP错误异常
        ]);
        
        // 初始化机器人告警服务
        $this->telegramAlertService = new \app\api\service\v1\order\TelegramAlertService();
    }

    /**
     * 异步通知商户（高并发版本）
     * @param Order $order 订单对象
     * @param array $callbackData 回调数据
     * @return void
     */
    public function notifyMerchantAsync(Order $order, array $callbackData = []): void
    {
        // 只对支付成功状态发送通知，其他状态不通知
        if ($order->status != Order::STATUS_SUCCESS) {
            Log::info('订单状态不是支付成功，跳过通知', [
                'order_no' => $order->order_no,
                'merchant_order_no' => $order->merchant_order_no,
                'current_status' => $order->status,
                'status_text' => $this->getStatusText($order->status)
            ]);
            return;
        }
        
        // 检查是否已经通知成功
        if ($order->notify_status == Order::NOTIFY_STATUS_SUCCESS) {
            Log::info('订单已通知成功，跳过重复通知', [
                'order_no' => $order->order_no,
                'merchant_order_no' => $order->merchant_order_no
            ]);
            return;
        }

        // 检查商户熔断状态
        if ($this->isMerchantCircuitBreakerOpen($order->notify_url)) {
            Log::warning('商户熔断器开启，跳过通知', [
                'order_no' => $order->order_no,
                'notify_url' => $order->notify_url
            ]);
            $this->scheduleDelayedNotification($order, $callbackData, 60); // 延迟1分钟重试
            return;
        }

        // 使用Redis分布式锁防止重复通知
        $lockKey = "merchant_notify_lock:{$order->order_no}";
        $lockAcquired = Redis::set($lockKey, 1, 'EX', 30, 'NX');
        
        if (!$lockAcquired) {
            Log::info('订单通知正在处理中，跳过重复通知', [
                'order_no' => $order->order_no
            ]);
            return;
        }

        try {
            // 构建通知数据
            $notifyData = $this->buildNotifyData($order, $callbackData);
            
            // 记录通知日志
            $notifyLog = $this->createNotifyLog($order, $notifyData);
            
            // 记录到订单链路追踪
            $this->logMerchantNotificationToTrace($order, $notifyData, 'start');
            
            // 异步发送通知
            $this->sendNotificationAsync($order, $notifyData, $notifyLog);
            
        } finally {
            // 释放锁
            Redis::del($lockKey);
        }
    }

    /**
     * 批量异步通知商户（高并发版本）
     * @param array $orders 订单数组
     * @return void
     */
    public function batchNotifyMerchantsAsync(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        $promises = [];
        $notifyLogs = [];
        
        foreach ($orders as $order) {
            // 检查是否已经通知成功
            if ($order->notify_status == Order::NOTIFY_STATUS_SUCCESS) {
                continue;
            }

            // 构建通知数据
            $notifyData = $this->buildNotifyData($order);
            $notifyLog = $this->createNotifyLog($order, $notifyData);
            $notifyLogs[] = $notifyLog;
            
            // 创建异步请求
            $promises[$order->order_no] = $this->createAsyncRequest($order, $notifyData);
        }

        if (empty($promises)) {
            return;
        }

        // 并发执行所有请求
        $responses = Promise\settle($promises)->wait();
        
        // 处理响应结果
        $this->handleBatchResponses($responses, $notifyLogs);
    }

    /**
     * 构建通知数据
     * @param Order $order
     * @param array $callbackData
     * @return array
     */
    private function buildNotifyData(Order $order, array $callbackData = []): array
    {
        return [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->merchant_order_no,
            'amount' => number_format($order->amount, 2, '.', ''), // 确保金额格式为元，保留2位小数
            'status' => $order->status,
            'status_text' => $this->getStatusText($order->status),
            'paid_time' => $order->paid_time,
            'created_at' => $order->created_at,
            'extra_data' => $order->extra_data ?: '{}', // 扩展数据，JSON格式
            'sign' => $this->generateSign($order),
            'timestamp' => time(),
            'callback_data' => $callbackData
        ];
    }

    /**
     * 创建通知日志
     * @param Order $order
     * @param array $notifyData
     * @return NotifyLog
     */
    private function createNotifyLog(Order $order, array $notifyData): NotifyLog
    {
        return NotifyLog::create([
            'order_id' => $order->id,
            'notify_url' => $order->notify_url,
            'request_data' => $notifyData,
            'response_data' => null,
            'http_code' => 0,
            'status' => NotifyLog::STATUS_FAILED,
            'retry_count' => 0
        ]);
    }

    /**
     * 异步发送通知
     * @param Order $order
     * @param array $notifyData
     * @param NotifyLog $notifyLog
     * @return void
     */
    private function sendNotificationAsync(Order $order, array $notifyData, NotifyLog $notifyLog): void
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->httpClient->post($order->notify_url, [
                'json' => $notifyData,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'PaymentSystem/1.0',
                    'X-Order-No' => $order->order_no
                ]
            ]);

            $responseTime = microtime(true) - $startTime;
            $httpCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            // 判断是否成功
            $isSuccess = $httpCode == 200 && $this->isSuccessResponse($responseBody);
            
            // 更新通知日志
            $this->updateNotifyLog($notifyLog, $httpCode, $responseBody, $isSuccess);
            
            // 更新订单通知状态
            if ($isSuccess) {
                $this->updateOrderNotifyStatus($order, Order::NOTIFY_STATUS_SUCCESS);
                $this->recordMerchantSuccess($order->notify_url, $responseTime);
                
                // 记录回调成功到链路追踪
                $this->logMerchantNotificationToTrace($order, $notifyData, 'callback_success', [
                    'http_code' => $httpCode,
                    'response_time' => round($responseTime, 3),
                    'response_body' => $responseBody
                ]);
                
                Log::info('商户通知成功', [
                    'order_no' => $order->order_no,
                    'notify_url' => $order->notify_url,
                    'http_code' => $httpCode,
                    'response_time' => round($responseTime, 3)
                ]);
            } else {
                $this->updateOrderNotifyStatus($order, Order::NOTIFY_STATUS_FAILED);
                $this->recordMerchantFailure($order->notify_url, $responseTime);
                
                // 记录回调失败到链路追踪
                $this->logMerchantNotificationToTrace($order, $notifyData, 'callback_failed', [
                    'http_code' => $httpCode,
                    'response_time' => round($responseTime, 3),
                    'response_body' => $responseBody,
                    'failure_reason' => 'HTTP状态码非200或响应内容不符合预期'
                ]);
                
                $this->scheduleRetry($order, $notifyData, $notifyLog);
            }
            
        } catch (RequestException $e) {
            $responseTime = microtime(true) - $startTime;
            $this->recordMerchantFailure($order->notify_url, $responseTime);
            
            // 记录回调异常到链路追踪
            $this->logMerchantNotificationToTrace($order, $notifyData, 'callback_failed', [
                'http_code' => $e->getCode(),
                'response_time' => round($responseTime, 3),
                'error_message' => $e->getMessage(),
                'failure_reason' => 'RequestException: ' . $e->getMessage()
            ]);
            
            $this->handleRequestException($e, $order, $notifyData, $notifyLog);
        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;
            $this->recordMerchantFailure($order->notify_url, $responseTime);
            
            // 记录回调异常到链路追踪
            $this->logMerchantNotificationToTrace($order, $notifyData, 'callback_failed', [
                'http_code' => 0,
                'response_time' => round($responseTime, 3),
                'error_message' => $e->getMessage(),
                'failure_reason' => 'Exception: ' . $e->getMessage()
            ]);
            
            // 发送供货商回调商户异常告警到机器人群
            $this->sendSupplierCallbackExceptionAlert($order, $notifyLog, 0, $e->getMessage());
            
            Log::error('商户通知异常', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response_time' => round($responseTime, 3)
            ]);
            $this->updateNotifyLog($notifyLog, 0, $e->getMessage(), false);
            $this->scheduleRetry($order, $notifyData, $notifyLog);
        }
    }

    /**
     * 创建异步请求
     * @param Order $order
     * @param array $notifyData
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function createAsyncRequest(Order $order, array $notifyData)
    {
        return $this->httpClient->postAsync($order->notify_url, [
            'json' => $notifyData,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'PaymentSystem/1.0',
                'X-Order-No' => $order->order_no
            ]
        ]);
    }

    /**
     * 处理批量响应
     * @param array $responses
     * @param array $notifyLogs
     * @return void
     */
    private function handleBatchResponses(array $responses, array $notifyLogs): void
    {
        foreach ($responses as $orderNo => $response) {
            $notifyLog = $notifyLogs[$orderNo] ?? null;
            if (!$notifyLog) {
                continue;
            }

            if ($response['state'] === 'fulfilled') {
                $httpResponse = $response['value'];
                $httpCode = $httpResponse->getStatusCode();
                $responseBody = $httpResponse->getBody()->getContents();
                
                // 判断是否成功
                $isSuccess = $httpCode == 200 && $this->isSuccessResponse($responseBody);
                
                $this->updateNotifyLog($notifyLog, $httpCode, $responseBody, $isSuccess);
                
                if ($isSuccess) {
                    $this->updateOrderNotifyStatusByLog($notifyLog, Order::NOTIFY_STATUS_SUCCESS);
                } else {
                    // 记录商户失败（触发熔断器）
                    $this->recordMerchantFailure($notifyLog->notify_url);
                    $this->updateOrderNotifyStatusByLog($notifyLog, Order::NOTIFY_STATUS_FAILED);
                    $this->scheduleRetryByLog($notifyLog);
                }
            } else {
                $exception = $response['reason'];
                $this->handleRequestException($exception, null, null, $notifyLog);
            }
        }
    }

    /**
     * 处理请求异常
     * @param \Exception $e
     * @param Order|null $order
     * @param array|null $notifyData
     * @param NotifyLog $notifyLog
     * @return void
     */
    private function handleRequestException(\Exception $e, ?Order $order, ?array $notifyData, NotifyLog $notifyLog): void
    {
        $httpCode = 0;
        $errorMessage = $e->getMessage();
        
        if ($e instanceof RequestException && $e->hasResponse()) {
            $httpCode = $e->getResponse()->getStatusCode();
            $errorMessage = $e->getResponse()->getBody()->getContents();
        }
        
        $this->updateNotifyLog($notifyLog, $httpCode, $errorMessage, false);
        
        // 记录商户失败（触发熔断器）
        if ($order) {
            $this->recordMerchantFailure($order->notify_url);
            $this->updateOrderNotifyStatus($order, Order::NOTIFY_STATUS_FAILED);
            $this->scheduleRetry($order, $notifyData, $notifyLog);
        } else {
            $this->recordMerchantFailure($notifyLog->notify_url);
            $this->updateOrderNotifyStatusByLog($notifyLog, Order::NOTIFY_STATUS_FAILED);
            $this->scheduleRetryByLog($notifyLog);
        }
        
        // 发送供货商回调商户异常告警到机器人群
        $this->sendSupplierCallbackExceptionAlert($order, $notifyLog, $httpCode, $errorMessage);
        
        Log::warning('商户通知失败', [
            'order_no' => $order ? $order->order_no : $notifyLog->order_id,
            'notify_url' => $notifyLog->notify_url,
            'http_code' => $httpCode,
            'error' => $errorMessage
        ]);
    }

    /**
     * 更新通知日志
     * @param NotifyLog $notifyLog
     * @param int $httpCode
     * @param string $responseBody
     * @param bool $isSuccess
     * @return void
     */
    private function updateNotifyLog(NotifyLog $notifyLog, int $httpCode, string $responseBody, bool $isSuccess): void
    {
        // 直接更新字段，避免updated_at字段问题
        $notifyLog->response_data = $responseBody;
        $notifyLog->http_code = $httpCode;
        $notifyLog->status = $isSuccess ? NotifyLog::STATUS_SUCCESS : NotifyLog::STATUS_FAILED;
        $notifyLog->retry_count = $notifyLog->retry_count + 1;
        $notifyLog->save();
    }

    /**
     * 更新订单通知状态
     * @param Order $order
     * @param int $status
     * @return void
     */
    private function updateOrderNotifyStatus(Order $order, int $status): void
    {
        $updateData = [
            'notify_status' => $status,
            'notify_count' => $order->notify_count + 1
        ];
        
        // 如果是首次通知成功，记录回调时间
        if ($status == Order::NOTIFY_STATUS_SUCCESS && $order->notify_status != Order::NOTIFY_STATUS_SUCCESS) {
            // 暂时注释掉，等数据库字段添加后再启用
            // $updateData['callback_time'] = date('Y-m-d H:i:s');
        }
        
        $order->update($updateData);
    }

    /**
     * 根据日志更新订单通知状态
     * @param NotifyLog $notifyLog
     * @param int $status
     * @return void
     */
    private function updateOrderNotifyStatusByLog(NotifyLog $notifyLog, int $status): void
    {
        $updateData = [
            'notify_status' => $status,
            'notify_count' => \DB::raw('notify_count + 1')
        ];
        
        // 如果是首次通知成功，记录回调时间
        if ($status == Order::NOTIFY_STATUS_SUCCESS) {
            // 暂时注释掉，等数据库字段添加后再启用
            // $updateData['callback_time'] = date('Y-m-d H:i:s');
        }
        
        Order::where('id', $notifyLog->order_id)->update($updateData);
    }

    /**
     * 安排重试
     * @param Order $order
     * @param array $notifyData
     * @param NotifyLog $notifyLog
     * @return void
     */
    private function scheduleRetry(Order $order, array $notifyData, NotifyLog $notifyLog): void
    {
        if ($notifyLog->retry_count >= $this->retryTimes) {
            Log::warning('商户通知重试次数已达上限', [
                'order_no' => $order->order_no,
                'retry_count' => $notifyLog->retry_count
            ]);
            return;
        }

        $delay = $this->retryDelay[$notifyLog->retry_count] ?? 5;
        
        // 使用Redis延迟队列
        Redis::zAdd('merchant_notify_retry_queue', time() + $delay, json_encode([
            'order_id' => $order->id,
            'notify_data' => $notifyData,
            'retry_count' => $notifyLog->retry_count
        ]));
    }

    /**
     * 根据日志安排重试
     * @param NotifyLog $notifyLog
     * @return void
     */
    private function scheduleRetryByLog(NotifyLog $notifyLog): void
    {
        if ($notifyLog->retry_count >= $this->retryTimes) {
            return;
        }

        $delay = $this->retryDelay[$notifyLog->retry_count] ?? 5;
        
        Redis::zAdd('merchant_notify_retry_queue', time() + $delay, json_encode([
            'order_id' => $notifyLog->order_id,
            'retry_count' => $notifyLog->retry_count
        ]));
    }

    /**
     * 处理重试队列
     * @return void
     */
    public function processRetryQueue(): void
    {
        $now = time();
        $retryItems = Redis::zRangeByScore('merchant_notify_retry_queue', 0, $now, ['limit' => [0, 100]]);
        
        if (empty($retryItems)) {
            return;
        }

        $retryData = [];
        foreach ($retryItems as $item) {
            $data = json_decode($item, true);
            $order = Order::find($data['order_id']);
            
            if ($order && $order->notify_status != Order::NOTIFY_STATUS_SUCCESS) {
                $retryData[] = [
                    'order' => $order,
                    'retry_count' => $data['retry_count'] ?? 0,
                    'notify_log_id' => $data['notify_log_id'] ?? null
                ];
            }
            
            // 从队列中移除
            Redis::zRem('merchant_notify_retry_queue', $item);
        }

        if (!empty($retryData)) {
            $this->batchNotifyMerchantsAsyncWithRetry($retryData);
        }
    }

    /**
     * 批量异步通知商户（重试版本）
     * @param array $retryData 重试数据数组
     * @return void
     */
    private function batchNotifyMerchantsAsyncWithRetry(array $retryData): void
    {
        if (empty($retryData)) {
            return;
        }

        $promises = [];
        $notifyLogs = [];
        
        foreach ($retryData as $item) {
            $order = $item['order'];
            $retryCount = $item['retry_count'];
            $notifyLogId = $item['notify_log_id'];
            
            // 检查是否已经通知成功
            if ($order->notify_status == Order::NOTIFY_STATUS_SUCCESS) {
                continue;
            }

            // 构建通知数据
            $notifyData = $this->buildNotifyData($order);
            
            // 创建重试通知日志
            $notifyLog = $this->createRetryNotifyLog($order, $notifyData, $retryCount, $notifyLogId);
            $notifyLogs[] = $notifyLog;
            
            // 创建异步请求
            $promises[$order->order_no] = $this->createAsyncRequest($order, $notifyData);
        }

        if (empty($promises)) {
            return;
        }

        // 并发执行所有请求
        $responses = Promise\settle($promises)->wait();
        
        // 处理响应结果
        $this->handleBatchResponses($responses, $notifyLogs);
    }

    /**
     * 创建或更新重试通知日志
     * @param Order $order
     * @param array $notifyData
     * @param int $retryCount
     * @param int|null $originalLogId
     * @return NotifyLog
     */
    private function createRetryNotifyLog(Order $order, array $notifyData, int $retryCount, ?int $originalLogId = null): NotifyLog
    {
        // 优先使用原始日志ID
        if ($originalLogId) {
            $existingLog = NotifyLog::find($originalLogId);
            if ($existingLog) {
                // 更新现有日志
                $existingLog->update([
                    'retry_count' => $retryCount + 1,
                    'request_data' => $notifyData,
                    'response_data' => null,
                    'http_code' => 0,
                    'status' => NotifyLog::STATUS_FAILED
                ]);
                return $existingLog;
            }
        }
        
        // 如果没有原始日志ID，查找该订单的最新日志记录
        $latestLog = NotifyLog::where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($latestLog) {
            // 更新最新的日志记录
            $latestLog->update([
                'retry_count' => $retryCount + 1,
                'request_data' => $notifyData,
                'response_data' => null,
                'http_code' => 0,
                'status' => NotifyLog::STATUS_FAILED
            ]);
            return $latestLog;
        }
        
        // 如果没有任何日志记录，创建新的（这种情况应该很少见）
        return NotifyLog::create([
            'order_id' => $order->id,
            'notify_url' => $order->notify_url,
            'request_data' => $notifyData,
            'response_data' => null,
            'http_code' => 0,
            'status' => NotifyLog::STATUS_FAILED,
            'retry_count' => $retryCount + 1
        ]);
    }

    /**
     * 生成签名
     * @param Order $order
     * @return string
     */
    private function generateSign(Order $order): string
    {
        $merchant = $order->merchant;
        // 构建签名数据，不包含third_party_order_no字段
        $signData = [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->merchant_order_no,
            'amount' => number_format($order->amount, 2, '.', ''),
            'status' => $order->status,
            'status_text' => $this->getStatusText($order->status),
            'paid_time' => $order->paid_time,
            'created_at' => $order->created_at,
            'extra_data' => $order->extra_data ?: '{}',
            'timestamp' => time()
        ];
        
        // 使用SignatureHelper生成签名
        return \app\common\helpers\SignatureHelper::generate($signData, $merchant->merchant_key);
    }

    /**
     * 记录商户通知到订单链路追踪
     * @param Order $order 订单对象
     * @param array $notifyData 通知数据
     * @param string $status 状态
     * @param array $extraData 额外数据
     */
    private function logMerchantNotificationToTrace(Order $order, array $notifyData, string $status, array $extraData = []): void
    {
        try {
            // 使用订单的原始trace_id，如果没有则生成新的
            $traceId = $order->trace_id ?: TraceIdHelper::get();
            
            // 创建TraceService实例
            $traceService = new TraceService();
            
            // 构建步骤数据
            $stepData = array_merge([
                'notify_url' => $order->notify_url,
                'notify_data' => $notifyData,
                'order_status' => $order->status,
                'order_no' => $order->order_no,
                'merchant_order_no' => $order->merchant_order_no,
                'callback_type' => 'merchant_notification'
            ], $extraData);
            
            // 根据状态确定步骤名称
            $stepName = match($status) {
                'start' => 'callback_sent',
                'callback_success' => 'callback_success',
                'callback_failed' => 'callback_failed',
                default => 'callback_sent'
            };
            
            // 记录商户通知步骤
            $traceService->logLifecycleStep(
                $traceId,
                $order->id,
                $order->merchant_id,
                $stepName,
                $status,
                $stepData,
                null,
                0,
                $order->order_no,
                $order->merchant_order_no
            );

            Log::info('MerchantNotificationService 已记录到订单链路追踪', [
                'trace_id' => $traceId,
                'order_no' => $order->order_no,
                'status' => $status,
                'notify_url' => $order->notify_url
            ]);

        } catch (\Exception $e) {
            Log::error('MerchantNotificationService 记录链路追踪失败', [
                'error' => $e->getMessage(),
                'order_no' => $order->order_no
            ]);
        }
    }

    /**
     * 获取状态文本
     * @param int $status
     * @return string
     */
    private function getStatusText(int $status): string
    {
        $statusMap = [
            Order::STATUS_PENDING => '待支付',
            Order::STATUS_PAYING => '支付中',
            Order::STATUS_SUCCESS => '支付成功',
            Order::STATUS_FAILED => '支付失败',
            Order::STATUS_REFUNDED => '已退款',
            Order::STATUS_CLOSED => '已关闭'
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 判断响应是否成功
     * @param string $responseBody
     * @return bool
     */
    private function isSuccessResponse(string $responseBody): bool
    {
        // 检查商户返回的响应
        $response = json_decode($responseBody, true);
        if (is_array($response)) {
            // 常见的成功响应格式
            return isset($response['code']) && $response['code'] == 200 ||
                   isset($response['status']) && $response['status'] == 'success' ||
                   isset($response['result']) && $response['result'] == 'success' ||
                   $responseBody === 'success' ||
                   $responseBody === 'SUCCESS';
        }
        
        return $responseBody === 'success' || $responseBody === 'SUCCESS';
    }

    /**
     * 检查商户熔断器是否开启
     * @param string $notifyUrl
     * @return bool
     */
    private function isMerchantCircuitBreakerOpen(string $notifyUrl): bool
    {
        $merchantKey = $this->getMerchantKey($notifyUrl);
        $circuitBreakerKey = "merchant_circuit_breaker:{$merchantKey}";
        
        $circuitBreakerData = Redis::get($circuitBreakerKey);
        if (!$circuitBreakerData) {
            return false;
        }
        
        $data = json_decode($circuitBreakerData, true);
        $now = time();
        
        // 检查是否在熔断期内
        if ($now - $data['open_time'] < $this->circuitBreakerTimeout) {
            return true;
        }
        
        // 熔断期结束，重置熔断器
        Redis::del($circuitBreakerKey);
        $this->resetMerchantFailureCount($merchantKey);
        
        return false;
    }

    /**
     * 记录商户失败
     * @param string $notifyUrl
     * @param float $responseTime
     * @return void
     */
    private function recordMerchantFailure(string $notifyUrl, float $responseTime = 0): void
    {
        $merchantKey = $this->getMerchantKey($notifyUrl);
        $failureKey = "merchant_failure_count:{$merchantKey}";
        
        // 增加失败次数
        $failureCount = Redis::incr($failureKey);
        Redis::expire($failureKey, 3600); // 1小时过期
        
        // 记录响应时间
        if ($responseTime > 0) {
            $timeoutKey = "merchant_timeout:{$merchantKey}";
            Redis::lPush($timeoutKey, $responseTime);
            Redis::lTrim($timeoutKey, 0, 99); // 只保留最近100次
            Redis::expire($timeoutKey, 3600);
        }
        
        // 检查是否需要开启熔断器
        if ($failureCount >= $this->maxFailureCount) {
            $this->openMerchantCircuitBreaker($merchantKey);
        }
        
        Log::warning('商户通知失败记录', [
            'merchant_key' => $merchantKey,
            'notify_url' => $notifyUrl,
            'failure_count' => $failureCount,
            'response_time' => $responseTime
        ]);
    }

    /**
     * 记录商户成功
     * @param string $notifyUrl
     * @param float $responseTime
     * @return void
     */
    private function recordMerchantSuccess(string $notifyUrl, float $responseTime = 0): void
    {
        $merchantKey = $this->getMerchantKey($notifyUrl);
        
        // 重置失败次数
        $this->resetMerchantFailureCount($merchantKey);
        
        // 记录响应时间
        if ($responseTime > 0) {
            $timeoutKey = "merchant_timeout:{$merchantKey}";
            Redis::lPush($timeoutKey, $responseTime);
            Redis::lTrim($timeoutKey, 0, 99);
            Redis::expire($timeoutKey, 3600);
            
            // 检查是否为慢商户并发送告警
            $this->checkAndSendSlowMerchantAlert($notifyUrl, $responseTime);
        }
    }

    /**
     * 开启商户熔断器
     * @param string $merchantKey
     * @return void
     */
    private function openMerchantCircuitBreaker(string $merchantKey): void
    {
        $circuitBreakerKey = "merchant_circuit_breaker:{$merchantKey}";
        $circuitBreakerData = [
            'open_time' => time(),
            'failure_count' => $this->getMerchantFailureCount($merchantKey)
        ];
        
        Redis::setex($circuitBreakerKey, $this->circuitBreakerTimeout, json_encode($circuitBreakerData));
        
        Log::error('商户熔断器开启', [
            'merchant_key' => $merchantKey,
            'failure_count' => $circuitBreakerData['failure_count'],
            'timeout' => $this->circuitBreakerTimeout
        ]);
        
        // 发送机器人告警
        $this->sendMerchantCircuitBreakerAlert($merchantKey, $circuitBreakerData['failure_count']);
    }

    /**
     * 重置商户失败次数
     * @param string $merchantKey
     * @return void
     */
    private function resetMerchantFailureCount(string $merchantKey): void
    {
        $failureKey = "merchant_failure_count:{$merchantKey}";
        Redis::del($failureKey);
    }

    /**
     * 获取商户失败次数
     * @param string $merchantKey
     * @return int
     */
    private function getMerchantFailureCount(string $merchantKey): int
    {
        $failureKey = "merchant_failure_count:{$merchantKey}";
        return (int)Redis::get($failureKey);
    }

    /**
     * 获取商户标识
     * @param string $notifyUrl
     * @return string
     */
    private function getMerchantKey(string $notifyUrl): string
    {
        return md5($notifyUrl);
    }

    /**
     * 安排延迟通知
     * @param Order $order
     * @param array $callbackData
     * @param int $delaySeconds
     * @return void
     */
    private function scheduleDelayedNotification(Order $order, array $callbackData, int $delaySeconds): void
    {
        $delayedData = [
            'order_id' => $order->id,
            'callback_data' => $callbackData,
            'scheduled_time' => time() + $delaySeconds,
            'reason' => 'circuit_breaker'
        ];
        
        Redis::zAdd('merchant_notify_delayed_queue', time() + $delaySeconds, json_encode($delayedData));
        
        Log::info('安排延迟通知', [
            'order_no' => $order->order_no,
            'delay_seconds' => $delaySeconds,
            'reason' => 'circuit_breaker'
        ]);
    }

    /**
     * 获取商户平均响应时间
     * @param string $notifyUrl
     * @return float
     */
    private function getMerchantAverageResponseTime(string $notifyUrl): float
    {
        $merchantKey = $this->getMerchantKey($notifyUrl);
        $timeoutKey = "merchant_timeout:{$merchantKey}";
        
        $responseTimes = Redis::lRange($timeoutKey, 0, -1);
        if (empty($responseTimes)) {
            return 0;
        }
        
        $total = array_sum($responseTimes);
        return $total / count($responseTimes);
    }

    /**
     * 检查是否为慢商户
     * @param string $notifyUrl
     * @return bool
     */
    private function isSlowMerchant(string $notifyUrl): bool
    {
        $avgResponseTime = $this->getMerchantAverageResponseTime($notifyUrl);
        return $avgResponseTime > $this->slowMerchantThreshold;
    }

    /**
     * 获取商户状态信息
     * @param string $notifyUrl
     * @return array
     */
    public function getMerchantStatus(string $notifyUrl): array
    {
        $merchantKey = $this->getMerchantKey($notifyUrl);
        $failureCount = $this->getMerchantFailureCount($merchantKey);
        $avgResponseTime = $this->getMerchantAverageResponseTime($notifyUrl);
        $isCircuitBreakerOpen = $this->isMerchantCircuitBreakerOpen($notifyUrl);
        $isSlow = $this->isSlowMerchant($notifyUrl);
        
        return [
            'merchant_key' => $merchantKey,
            'notify_url' => $notifyUrl,
            'failure_count' => $failureCount,
            'avg_response_time' => round($avgResponseTime, 3),
            'is_circuit_breaker_open' => $isCircuitBreakerOpen,
            'is_slow_merchant' => $isSlow,
            'status' => $isCircuitBreakerOpen ? 'circuit_breaker' : ($isSlow ? 'slow' : 'normal')
        ];
    }

    /**
     * 发送商户熔断器告警
     * @param string $merchantKey
     * @param int $failureCount
     * @return void
     */
    private function sendMerchantCircuitBreakerAlert(string $merchantKey, int $failureCount): void
    {
        try {
            $message = $this->buildMerchantCircuitBreakerMessage($merchantKey, $failureCount);
            $this->telegramAlertService->sendSlowResponseAlert($message, [
                'alert_type' => 'merchant_circuit_breaker',
                'merchant_key' => $merchantKey,
                'failure_count' => $failureCount
            ]);
            
            Log::info('商户熔断器告警已发送', [
                'merchant_key' => $merchantKey,
                'failure_count' => $failureCount
            ]);
        } catch (\Exception $e) {
            Log::error('商户熔断器告警发送失败', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 发送慢商户告警
     * @param string $merchantKey
     * @param float $avgResponseTime
     * @return void
     */
    private function sendSlowMerchantAlert(string $merchantKey, float $avgResponseTime): void
    {
        try {
            $message = $this->buildSlowMerchantMessage($merchantKey, $avgResponseTime);
            $this->telegramAlertService->sendSlowResponseAlert($message, [
                'alert_type' => 'slow_merchant',
                'merchant_key' => $merchantKey,
                'avg_response_time' => $avgResponseTime
            ]);
            
            Log::info('慢商户告警已发送', [
                'merchant_key' => $merchantKey,
                'avg_response_time' => $avgResponseTime
            ]);
        } catch (\Exception $e) {
            Log::error('慢商户告警发送失败', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 构建商户熔断器告警消息
     * @param string $merchantKey
     * @param int $failureCount
     * @return string
     */
    private function buildMerchantCircuitBreakerMessage(string $merchantKey, int $failureCount): string
    {
        $timestamp = date('Y-m-d H:i:s');
        
        // 尝试获取商户名称
        $merchantName = $this->getMerchantNameByKey($merchantKey);
        $merchantDisplay = $merchantName ? "{$merchantName} ({$merchantKey})" : $merchantKey;
        
        return "🚨 *商户通知熔断器告警*

*时间*: {$timestamp}
*商户名称*: `{$merchantDisplay}`
*失败次数*: {$failureCount}
*熔断时长*: 5分钟
*状态*: 熔断器已开启

*影响*: 该商户的通知将被暂停5分钟，避免影响其他商户的正常通知。

*建议*: 请检查商户服务器状态和网络连接。";
    }

    /**
     * 根据商户标识获取商户名称
     * @param string $merchantKey
     * @return string|null
     */
    private function getMerchantNameByKey(string $merchantKey): ?string
    {
        try {
            // 由于merchantKey是基于notify_url的MD5，我们需要通过其他方式查找
            // 这里我们通过查找最近使用该merchantKey的订单来获取商户信息
            $order = \app\model\Order::where('notify_url', '!=', '')
                ->whereRaw('MD5(notify_url) = ?', [$merchantKey])
                ->with('merchant')
                ->first();
                
            if ($order && $order->merchant) {
                return $order->merchant->merchant_name;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('获取商户名称失败', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 构建慢商户告警消息
     * @param string $merchantKey
     * @param float $avgResponseTime
     * @return string
     */
    private function buildSlowMerchantMessage(string $merchantKey, float $avgResponseTime): string
    {
        $timestamp = date('Y-m-d H:i:s');
        
        // 尝试获取商户名称
        $merchantName = $this->getMerchantNameByKey($merchantKey);
        $merchantDisplay = $merchantName ? "{$merchantName} ({$merchantKey})" : $merchantKey;
        
        return "⚠️ *慢商户告警*

*时间*: {$timestamp}
*商户名称*: `{$merchantDisplay}`
*平均响应时间*: {$avgResponseTime}秒
*阈值*: {$this->slowMerchantThreshold}秒
*状态*: 响应过慢

*影响*: 该商户响应时间超过阈值，可能影响通知效率。

*建议*: 请检查商户服务器性能和网络状况。";
    }

    /**
     * 检查并发送慢商户告警
     * @param string $notifyUrl
     * @param float $responseTime
     * @return void
     */
    private function checkAndSendSlowMerchantAlert(string $notifyUrl, float $responseTime): void
    {
        // 只有响应时间超过阈值时才发送告警
        if ($responseTime <= $this->slowMerchantThreshold) {
            return;
        }

        $merchantKey = $this->getMerchantKey($notifyUrl);
        $avgResponseTime = $this->getMerchantAverageResponseTime($notifyUrl);
        
        // 检查是否已经发送过慢商户告警（避免重复告警）
        $alertKey = "slow_merchant_alert:{$merchantKey}";
        $lastAlertTime = Redis::get($alertKey);
        
        // 如果距离上次告警超过1小时，则发送新告警
        if (!$lastAlertTime || (time() - $lastAlertTime) > 3600) {
            $this->sendSlowMerchantAlert($merchantKey, $avgResponseTime);
            Redis::setex($alertKey, 3600, time()); // 1小时内不再重复告警
        }
    }

    /**
     * 发送供货商回调商户异常告警到机器人群
     * @param Order|null $order
     * @param NotifyLog $notifyLog
     * @param int $httpCode
     * @param string $errorMessage
     * @return void
     */
    private function sendSupplierCallbackExceptionAlert(?Order $order, NotifyLog $notifyLog, int $httpCode, string $errorMessage): void
    {
        try {
            $orderNo = $order ? $order->order_no : $notifyLog->order_id;
            $merchantKey = $this->getMerchantKey($notifyLog->notify_url);
            
            // 检查是否已经发送过异常告警（避免重复告警）
            $alertKey = "supplier_callback_exception_alert:{$merchantKey}:{$orderNo}";
            $lastAlertTime = Redis::get($alertKey);
            
            // 如果距离上次告警超过30分钟，则发送新告警
            if (!$lastAlertTime || (time() - $lastAlertTime) > 1800) {
                $message = $this->buildSupplierCallbackExceptionMessage($orderNo, $notifyLog->notify_url, $httpCode, $errorMessage);
                $this->telegramAlertService->sendSlowResponseAlert($message, [
                    'alert_type' => 'supplier_callback_exception',
                    'order_no' => $orderNo,
                    'merchant_key' => $merchantKey,
                    'http_code' => $httpCode,
                    'error_message' => $errorMessage
                ]);
                
                Redis::setex($alertKey, 1800, time()); // 30分钟内不再重复告警
                
                Log::info('供货商回调商户异常告警已发送', [
                    'order_no' => $orderNo,
                    'merchant_key' => $merchantKey,
                    'http_code' => $httpCode,
                    'error_message' => $errorMessage
                ]);
            }
        } catch (\Exception $e) {
            Log::error('供货商回调商户异常告警发送失败', [
                'order_no' => $order ? $order->order_no : $notifyLog->order_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 构建供货商回调商户异常告警消息
     * @param string $orderNo
     * @param string $notifyUrl
     * @param int $httpCode
     * @param string $errorMessage
     * @return string
     */
    private function buildSupplierCallbackExceptionMessage(string $orderNo, string $notifyUrl, int $httpCode, string $errorMessage): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $merchantKey = $this->getMerchantKey($notifyUrl);
        
        // 尝试获取商户名称
        $merchantName = $this->getMerchantNameByKey($merchantKey);
        $merchantDisplay = $merchantName ? "{$merchantName} ({$merchantKey})" : $merchantKey;
        
        return "🚨 *供货商回调商户异常告警*

*时间*: {$timestamp}
*订单号*: `{$orderNo}`
*商户名称*: `{$merchantDisplay}`
*回调地址*: `{$notifyUrl}`
*HTTP状态码*: {$httpCode}
*错误信息*: {$errorMessage}

*影响*: 供货商回调商户失败，商户可能无法及时收到支付结果通知。

*建议*: 请检查商户回调地址是否正常，网络连接是否稳定。";
    }
}
