<?php

namespace app\command;

use app\service\notification\MerchantNotificationService;
use support\Log;
use support\Redis;

/**
 * 商户通知队列处理器
 * 处理重试队列和批量通知
 */
class MerchantNotifyQueueCommand
{
    private $notificationService;
    private $isRunning = false;

    public function __construct()
    {
        $this->notificationService = new MerchantNotificationService();
    }

    /**
     * 启动队列处理器
     * @return void
     */
    public function start(): void
    {
        $this->isRunning = true;
        Log::info('商户通知队列处理器启动');
        
        while ($this->isRunning) {
            try {
                // 处理重试队列
                $this->notificationService->processRetryQueue();
                
                // 处理待通知队列
                $this->processPendingNotifications();
                
                // 处理延迟通知队列
                $this->processDelayedNotifications();
                
                // 清理过期数据
                $this->cleanupExpiredData();
                
                // 休眠1秒
                sleep(1);
                
            } catch (\Exception $e) {
                Log::error('商户通知队列处理器异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep(5); // 异常时休眠5秒
            }
        }
        
        Log::info('商户通知队列处理器停止');
    }

    /**
     * 停止队列处理器
     * @return void
     */
    public function stop(): void
    {
        $this->isRunning = false;
    }

    /**
     * 处理待通知队列
     * @return void
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
            // 批量通知
            $this->notificationService->batchNotifyMerchantsAsync($orders);
            
            // 从队列中移除已处理的订单
            Redis::lTrim('merchant_notify_pending_queue', count($orders), -1);
        }
    }

    /**
     * 处理延迟通知队列
     * @return void
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
     * 清理过期数据
     * @return void
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
     * 添加订单到待通知队列
     * @param int $orderId
     * @param array $callbackData
     * @return void
     */
    public static function addToPendingQueue(int $orderId, array $callbackData = []): void
    {
        $queueData = [
            'order_id' => $orderId,
            'callback_data' => $callbackData,
            'created_at' => time()
        ];
        
        Redis::lPush('merchant_notify_pending_queue', json_encode($queueData));
    }

    /**
     * 获取队列状态
     * @return array
     */
    public static function getQueueStatus(): array
    {
        return [
            'pending_count' => Redis::lLen('merchant_notify_pending_queue'),
            'retry_count' => Redis::zCard('merchant_notify_retry_queue'),
            'delayed_count' => Redis::zCard('merchant_notify_delayed_queue'),
            'pending_orders' => Redis::lRange('merchant_notify_pending_queue', 0, 9), // 前10个
            'retry_orders' => Redis::zRange('merchant_notify_retry_queue', 0, 9), // 前10个
            'delayed_orders' => Redis::zRange('merchant_notify_delayed_queue', 0, 9) // 前10个
        ];
    }
}
