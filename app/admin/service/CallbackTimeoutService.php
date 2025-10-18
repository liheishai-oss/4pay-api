<?php

namespace app\admin\service;

use app\model\Order;
use support\Log;
use support\Db;

/**
 * 回调超时检查服务
 * 检查支付成功但10分钟内没有回调成功的订单，将其状态改为回调失败
 */
class CallbackTimeoutService
{
    /**
     * 检查回调超时的订单
     * @param int $timeoutMinutes 超时时间（分钟）
     * @param int $limit 处理数量限制
     * @return array
     */
    public function checkCallbackTimeout(int $timeoutMinutes = 10, int $limit = 100): array
    {
        try {
            $timeoutTime = date('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));
            
            // 查找支付成功但超过指定时间没有回调成功的订单
            $orders = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_NONE) // 未通知
                ->where('notify_url', '!=', '') // 有通知地址
                ->where('paid_time', '<=', $timeoutTime) // 支付时间超过超时时间
                ->whereNotNull('paid_time') // 有支付时间
                ->limit($limit)
                ->get();

            $results = [];
            $successCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            foreach ($orders as $order) {
                try {
                    // 检查是否已经在队列中
                    if ($this->isOrderInQueue($order->id)) {
                        $results[] = [
                            'order_no' => $order->order_no,
                            'status' => 'skip',
                            'message' => '仍在回调队列中，暂不标记为失败',
                            'paid_time' => $order->paid_time,
                            'time_since_paid' => $this->getTimeSincePaid($order->paid_time)
                        ];
                        $skipCount++;
                        continue;
                    }

                    // 检查是否已经有成功的回调记录
                    if ($this->hasSuccessfulCallback($order->id)) {
                        $results[] = [
                            'order_no' => $order->order_no,
                            'status' => 'skip',
                            'message' => '已有成功回调记录，无需处理',
                            'paid_time' => $order->paid_time,
                            'time_since_paid' => $this->getTimeSincePaid($order->paid_time)
                        ];
                        $skipCount++;
                        continue;
                    }

                    // 标记为回调失败
                    $order->notify_status = Order::NOTIFY_STATUS_FAILED;
                    $order->save();

                    $results[] = [
                        'order_no' => $order->order_no,
                        'status' => 'success',
                        'message' => '已标记为回调失败',
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => $this->getTimeSincePaid($order->paid_time)
                    ];
                    $successCount++;

                    Log::info('回调超时处理', [
                        'order_no' => $order->order_no,
                        'order_id' => $order->id,
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => $this->getTimeSincePaid($order->paid_time),
                        'notify_url' => $order->notify_url,
                        'timeout_minutes' => $timeoutMinutes
                    ]);

                } catch (\Exception $e) {
                    $results[] = [
                        'order_no' => $order->order_no,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => $this->getTimeSincePaid($order->paid_time)
                    ];
                    $errorCount++;
                }
            }

            Log::info('回调超时检查完成', [
                'total' => $orders->count(),
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'timeout_minutes' => $timeoutMinutes,
                'filter_conditions' => [
                    'status' => Order::STATUS_SUCCESS,
                    'notify_status' => Order::NOTIFY_STATUS_NONE,
                    'has_notify_url' => true,
                    'paid_time_before' => $timeoutTime,
                    'description' => "支付成功但{$timeoutMinutes}分钟内没有回调成功的订单"
                ]
            ]);

            return [
                'total' => $orders->count(),
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'results' => $results,
                'timeout_minutes' => $timeoutMinutes,
                'filter_conditions' => [
                    'status' => Order::STATUS_SUCCESS,
                    'notify_status' => Order::NOTIFY_STATUS_NONE,
                    'has_notify_url' => true,
                    'paid_time_before' => $timeoutTime,
                    'description' => "支付成功但{$timeoutMinutes}分钟内没有回调成功的订单"
                ]
            ];

        } catch (\Exception $e) {
            Log::error('回调超时检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timeout_minutes' => $timeoutMinutes
            ]);
            throw $e;
        }
    }

    /**
     * 获取回调超时统计信息
     * @param int $timeoutMinutes 超时时间（分钟）
     * @param int $hours 统计时间范围（小时）
     * @return array
     */
    public function getCallbackTimeoutStats(int $timeoutMinutes = 10, int $hours = 24): array
    {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $timeoutTime = date('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));
            
            // 总支付成功订单数
            $totalOrders = Order::where('status', Order::STATUS_SUCCESS)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 回调成功数
            $callbackSuccess = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_SUCCESS)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 回调失败数
            $callbackFailed = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_FAILED)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 回调超时数（支付成功但超过指定时间没有回调成功）
            $callbackTimeout = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_NONE)
                ->where('notify_url', '!=', '')
                ->where('paid_time', '>=', $startTime)
                ->where('paid_time', '<=', $timeoutTime)
                ->whereNotNull('paid_time')
                ->count();

            // 正在回调中的订单数（最近支付成功，还未超时）
            $callbackPending = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_NONE)
                ->where('notify_url', '!=', '')
                ->where('paid_time', '>', $timeoutTime)
                ->whereNotNull('paid_time')
                ->count();

            // 无回调地址数
            $noCallbackUrl = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_url', '')
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            return [
                'total_orders' => $totalOrders,
                'callback_success' => $callbackSuccess,
                'callback_failed' => $callbackFailed,
                'callback_timeout' => $callbackTimeout,
                'callback_pending' => $callbackPending,
                'no_callback_url' => $noCallbackUrl,
                'callback_success_rate' => $totalOrders > 0 ? round(($callbackSuccess / $totalOrders) * 100, 2) : 0,
                'callback_failure_rate' => $totalOrders > 0 ? round(($callbackFailed / $totalOrders) * 100, 2) : 0,
                'timeout_minutes' => $timeoutMinutes,
                'time_range' => $hours . '小时',
                'start_time' => $startTime,
                'timeout_time' => $timeoutTime,
                'description' => "回调超时统计：支付成功但{$timeoutMinutes}分钟内没有回调成功的订单"
            ];

        } catch (\Exception $e) {
            Log::error('获取回调超时统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timeout_minutes' => $timeoutMinutes,
                'hours' => $hours
            ]);
            throw $e;
        }
    }

    /**
     * 检查订单是否在回调队列中
     * @param int $orderId
     * @return bool
     */
    private function isOrderInQueue(int $orderId): bool
    {
        try {
            // 检查待通知队列
            $pendingQueue = \support\Redis::lRange('merchant_notify_pending_queue', 0, -1);
            foreach ($pendingQueue as $item) {
                $data = json_decode($item, true);
                if (isset($data['order_id']) && $data['order_id'] == $orderId) {
                    return true;
                }
            }

            // 检查重试队列
            $retryQueue = \support\Redis::zRange('merchant_notify_retry_queue', 0, -1);
            foreach ($retryQueue as $item) {
                $data = json_decode($item, true);
                if (isset($data['order_id']) && $data['order_id'] == $orderId) {
                    return true;
                }
            }

            // 检查延迟队列
            $delayedQueue = \support\Redis::zRange('merchant_notify_delayed_queue', 0, -1);
            foreach ($delayedQueue as $item) {
                $data = json_decode($item, true);
                if (isset($data['order_id']) && $data['order_id'] == $orderId) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('检查订单队列状态失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查是否已经有成功的回调记录
     * @param int $orderId
     * @return bool
     */
    private function hasSuccessfulCallback(int $orderId): bool
    {
        try {
            $successfulCallback = \app\model\NotifyLog::where('order_id', $orderId)
                ->where('http_code', 200)
                ->where('response_body', 'like', '%success%')
                ->first();

            return $successfulCallback !== null;

        } catch (\Exception $e) {
            Log::error('检查成功回调记录失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 计算支付时间距离现在的时间
     * @param string $paidTime
     * @return string
     */
    private function getTimeSincePaid(string $paidTime): string
    {
        $timeDiff = time() - strtotime($paidTime);
        
        if ($timeDiff < 60) {
            return $timeDiff . '秒';
        } elseif ($timeDiff < 3600) {
            return floor($timeDiff / 60) . '分钟';
        } else {
            return floor($timeDiff / 3600) . '小时';
        }
    }
}
