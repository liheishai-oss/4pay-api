<?php

namespace app\process;

use support\Log;
use app\service\notification\MerchantNotificationService;
use support\Redis;

/**
 * 商户通知队列处理进程
 * 处理新通知、重试队列和延迟通知
 */
class MerchantNotifyQueueProcess
{
    private $notificationService;
    
    public function __construct()
    {
        $this->notificationService = new MerchantNotificationService();
    }
    
    /**
     * 进程启动时调用
     */
    public function onWorkerStart()
    {
        Log::info('商户通知队列处理进程启动');
        
        // 实时处理新通知队列 - 每100毫秒检查一次
        new \Workerman\Crontab\Crontab('*/100 * * * * *', function(){
            try {
                $this->processPendingNotifications();
            } catch (\Exception $e) {
                Log::error('处理新通知队列异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
        
        // 处理重试队列 - 每10秒检查一次
        new \Workerman\Crontab\Crontab('*/10 * * * * *', function(){
            try {
                $this->processRetryQueueWithStatus();
            } catch (\Exception $e) {
                Log::error('处理重试队列异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
        
        // 处理延迟通知队列 - 每10秒检查一次
        new \Workerman\Crontab\Crontab('*/10 * * * * *', function(){
            try {
                $this->processDelayedNotificationsWithStatus();
            } catch (\Exception $e) {
                Log::error('处理延迟通知队列异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
        
        // 清理过期数据 - 每5分钟清理一次
        new \Workerman\Crontab\Crontab('*/5 * * * *', function(){
            try {
                $this->cleanupExpiredData();
            } catch (\Exception $e) {
                Log::error('清理过期数据异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }
    
    /**
     * 处理待通知队列
     */
    private function processPendingNotifications(): void
    {
        // 从Redis队列中获取待通知的订单
        $pendingOrders = Redis::lRange('merchant_notify_pending_queue', 0, 49); // 每次处理50个
        
        if (empty($pendingOrders)) {
            return;
        }

        $orders = [];
        foreach ($pendingOrders as $orderData) {
            $data = json_decode($orderData, true);
            $order = \app\model\Order::find($data['order_id']);
            
            if ($order && $order->notify_status != \app\model\Order::NOTIFY_STATUS_SUCCESS) {
                $orders[] = $order;
            }
        }

        if (!empty($orders)) {
            // 更新处理进度到Redis
            $this->updateProcessingStatus('pending', count($orders), 'processing');
            
            // 批量通知
            $this->notificationService->batchNotifyMerchantsAsync($orders);
            
            // 从队列中移除已处理的订单
            Redis::lTrim('merchant_notify_pending_queue', count($orders), -1);
            
            // 更新处理完成状态
            $this->updateProcessingStatus('pending', count($orders), 'completed');
        }
    }
    
    /**
     * 处理延迟通知队列
     */
    private function processDelayedNotifications(): void
    {
        $now = time();
        $delayedItems = Redis::zRangeByScore('merchant_notify_delayed_queue', 0, $now, ['limit' => [0, 50]]);
        
        if (empty($delayedItems)) {
            return;
        }

        $orders = [];
        foreach ($delayedItems as $item) {
            $data = json_decode($item, true);
            $order = \app\model\Order::find($data['order_id']);
            
            if ($order && $order->notify_status != \app\model\Order::NOTIFY_STATUS_SUCCESS) {
                $orders[] = $order;
            }
            
            // 从队列中移除
            Redis::zRem('merchant_notify_delayed_queue', $item);
        }

        if (!empty($orders)) {
            $this->notificationService->batchNotifyMerchantsAsync($orders);
        }
    }
    
    /**
     * 处理重试队列（带状态同步）
     */
    private function processRetryQueueWithStatus(): void
    {
        $now = time();
        $retryItems = Redis::zRangeByScore('merchant_notify_retry_queue', 0, $now, ['limit' => [0, 50]]);
        
        if (empty($retryItems)) {
            return;
        }

        $orders = [];
        foreach ($retryItems as $item) {
            $data = json_decode($item, true);
            $order = \app\model\Order::find($data['order_id']);
            
            if ($order && $order->notify_status != \app\model\Order::NOTIFY_STATUS_SUCCESS) {
                $orders[] = $order;
            }
            
            // 从队列中移除
            Redis::zRem('merchant_notify_retry_queue', $item);
        }

        if (!empty($orders)) {
            // 更新处理进度
            $this->updateProcessingStatus('retry', count($orders), 'processing');
            
            // 批量通知
            $this->notificationService->batchNotifyMerchantsAsync($orders);
            
            // 更新处理完成状态
            $this->updateProcessingStatus('retry', count($orders), 'completed');
        }
    }
    
    /**
     * 处理延迟通知队列（带状态同步）
     */
    private function processDelayedNotificationsWithStatus(): void
    {
        $now = time();
        $delayedItems = Redis::zRangeByScore('merchant_notify_delayed_queue', 0, $now, ['limit' => [0, 50]]);
        
        if (empty($delayedItems)) {
            return;
        }

        $orders = [];
        foreach ($delayedItems as $item) {
            $data = json_decode($item, true);
            $order = \app\model\Order::find($data['order_id']);
            
            if ($order && $order->notify_status != \app\model\Order::NOTIFY_STATUS_SUCCESS) {
                $orders[] = $order;
            }
            
            // 从队列中移除
            Redis::zRem('merchant_notify_delayed_queue', $item);
        }

        if (!empty($orders)) {
            // 更新处理进度
            $this->updateProcessingStatus('delayed', count($orders), 'processing');
            
            // 批量通知
            $this->notificationService->batchNotifyMerchantsAsync($orders);
            
            // 更新处理完成状态
            $this->updateProcessingStatus('delayed', count($orders), 'completed');
        }
    }
    
    /**
     * 清理过期数据
     */
    private function cleanupExpiredData(): void
    {
        $now = time();
        
        // 清理过期的重试队列数据（超过1小时）
        Redis::zRemRangeByScore('merchant_notify_retry_queue', 0, $now - 3600);
        
        // 清理过期的延迟队列数据（超过2小时）
        Redis::zRemRangeByScore('merchant_notify_delayed_queue', 0, $now - 7200);
        
        // 清理过期的待通知队列数据（超过30分钟）
        $expiredCount = 0;
        $pendingOrders = Redis::lRange('merchant_notify_pending_queue', 0, -1);
        
        foreach ($pendingOrders as $index => $orderData) {
            $data = json_decode($orderData, true);
            if (isset($data['created_at']) && $data['created_at'] < $now - 1800) {
                Redis::lRem('merchant_notify_pending_queue', $orderData, 1);
                $expiredCount++;
            }
        }
        
        if ($expiredCount > 0) {
            Log::info('清理过期通知数据', ['expired_count' => $expiredCount]);
        }
    }
    
    /**
     * 更新处理状态到Redis
     * @param string $queueType 队列类型 (pending, retry, delayed)
     * @param int $count 处理数量
     * @param string $status 状态 (processing, completed, failed)
     */
    private function updateProcessingStatus(string $queueType, int $count, string $status): void
    {
        $statusKey = "merchant_queue_processing_status:{$queueType}";
        $statusData = [
            'queue_type' => $queueType,
            'count' => $count,
            'status' => $status,
            'timestamp' => time(),
            'process_time' => date('Y-m-d H:i:s')
        ];
        
        Redis::setex($statusKey, 300, json_encode($statusData)); // 5分钟过期
        
        // 更新总体处理统计
        $statsKey = "merchant_queue_stats";
        $stats = Redis::get($statsKey);
        $statsData = $stats ? json_decode($stats, true) : [
            'total_processed' => 0,
            'pending_processed' => 0,
            'retry_processed' => 0,
            'delayed_processed' => 0,
            'last_update' => time()
        ];
        
        if ($status === 'completed') {
            $statsData['total_processed'] += $count;
            $statsData["{$queueType}_processed"] += $count;
            $statsData['last_update'] = time();
            
            Redis::setex($statsKey, 3600, json_encode($statsData)); // 1小时过期
        }
    }
    
    /**
     * 获取处理状态
     * @return array
     */
    public static function getProcessingStatus(): array
    {
        $status = [];
        $queueTypes = ['pending', 'retry', 'delayed'];
        
        foreach ($queueTypes as $type) {
            $statusKey = "merchant_queue_processing_status:{$type}";
            $statusData = Redis::get($statusKey);
            $status[$type] = $statusData ? json_decode($statusData, true) : null;
        }
        
        // 获取总体统计
        $statsKey = "merchant_queue_stats";
        $stats = Redis::get($statsKey);
        $status['stats'] = $stats ? json_decode($stats, true) : null;
        
        return $status;
    }
    
    /**
     * 进程停止时调用
     */
    public function onWorkerStop()
    {
        Log::info('商户通知队列处理进程停止');
    }
}
