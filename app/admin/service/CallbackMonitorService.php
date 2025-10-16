<?php

namespace app\admin\service;

use app\model\Order;
use app\model\NotifyLog;
use app\service\notification\MerchantNotificationService;
use support\Log;
use support\Db;

/**
 * 回调监控服务
 * 用于检测和修复遗漏的商户通知
 */
class CallbackMonitorService
{
    /**
     * 获取未通知订单列表
     * 查询支付成功但5秒内没有通知的订单
     */
    public function getUnnotifiedOrders(int $page = 1, int $pageSize = 20, int $status = 3, int $hours = 24): array
    {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $fiveSecondsAgo = date('Y-m-d H:i:s', time() - 5);
            
            // 查询支付成功但5秒内没有通知的订单
            $query = Order::where('status', $status)
                ->where('notify_status', 0) // 未通知
                ->where('notify_url', '!=', '') // 有通知地址
                ->where('paid_time', '>=', $startTime) // 支付时间在指定范围内
                ->where('paid_time', '<=', $fiveSecondsAgo) // 支付时间超过5秒
                ->orderBy('paid_time', 'desc');

            $total = $query->count();
            $orders = $query->skip(($page - 1) * $pageSize)
                ->take($pageSize)
                ->get();

            return [
                'orders' => $orders,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => ceil($total / $pageSize),
                'filter_conditions' => [
                    'status' => $status,
                    'notify_status' => 0,
                    'has_notify_url' => true,
                    'paid_time_range' => $startTime . ' - ' . $fiveSecondsAgo,
                    'description' => '支付成功但5秒内没有通知的订单'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取未通知订单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 手动触发订单通知
     */
    public function triggerNotification($orderId = null, $orderNo = null): array
    {
        try {
            // 查找订单
            $order = null;
            if ($orderId) {
                $order = Order::find($orderId);
            } elseif ($orderNo) {
                $order = Order::where('order_no', $orderNo)->first();
            }

            if (!$order) {
                throw new \Exception('订单不存在');
            }

            // 检查订单状态
            if ($order->status != Order::STATUS_SUCCESS) {
                throw new \Exception('订单状态不是支付成功，无法触发通知');
            }

            // 检查是否已有通知地址
            if (empty($order->notify_url)) {
                throw new \Exception('订单没有通知地址');
            }

            // 检查是否已经在队列中
            if ($this->isOrderInQueue($order->id)) {
                return [
                    'success' => false,
                    'message' => '订单已在通知队列中，无需重复添加',
                    'order_no' => $order->order_no,
                    'order_id' => $order->id
                ];
            }

            // 检查是否已经通知成功
            if ($order->notify_status == Order::NOTIFY_STATUS_SUCCESS) {
                return [
                    'success' => false,
                    'message' => '订单已通知成功，无需重复通知',
                    'order_no' => $order->order_no,
                    'order_id' => $order->id
                ];
            }

            // 触发通知
            $notificationService = new MerchantNotificationService();
            $notificationService->notifyMerchantAsync($order);

            Log::info('手动触发订单通知', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'merchant_order_no' => $order->merchant_order_no,
                'notify_url' => $order->notify_url
            ]);

            return [
                'success' => true,
                'message' => '通知已触发',
                'order_no' => $order->order_no,
                'order_id' => $order->id
            ];

        } catch (\Exception $e) {
            Log::error('手动触发通知失败', [
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 批量触发通知
     */
    public function batchTriggerNotification(array $orderIds = [], array $orderNos = []): array
    {
        try {
            $results = [];
            $successCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            // 处理订单ID列表
            foreach ($orderIds as $orderId) {
                try {
                    $result = $this->triggerNotification($orderId);
                    $results[] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $skipCount++;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'message' => $e->getMessage(),
                        'order_id' => $orderId
                    ];
                    $errorCount++;
                }
            }

            // 处理订单号列表
            foreach ($orderNos as $orderNo) {
                try {
                    $result = $this->triggerNotification(null, $orderNo);
                    $results[] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $skipCount++;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'message' => $e->getMessage(),
                        'order_no' => $orderNo
                    ];
                    $errorCount++;
                }
            }

            return [
                'total' => count($orderIds) + count($orderNos),
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('批量触发通知失败', [
                'order_ids' => $orderIds,
                'order_nos' => $orderNos,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 获取通知统计信息
     * 基于支付时间统计
     */
    public function getNotificationStats(int $hours = 24): array
    {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $fiveSecondsAgo = date('Y-m-d H:i:s', time() - 5);
            
            // 总支付成功订单数
            $totalOrders = Order::where('status', Order::STATUS_SUCCESS)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 已通知成功数
            $notifiedSuccess = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_SUCCESS)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 支付成功但5秒内没有通知的订单数
            $unnotified = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', 0)
                ->where('notify_url', '!=', '')
                ->where('paid_time', '>=', $startTime)
                ->where('paid_time', '<=', $fiveSecondsAgo)
                ->whereNotNull('paid_time')
                ->count();

            // 通知失败数
            $notifyFailed = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_status', Order::NOTIFY_STATUS_FAILED)
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 无通知地址数
            $noNotifyUrl = Order::where('status', Order::STATUS_SUCCESS)
                ->where('notify_url', '')
                ->where('paid_time', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->count();

            // 最近5秒内支付成功的订单数（可能还在处理中）
            $recentPaid = Order::where('status', Order::STATUS_SUCCESS)
                ->where('paid_time', '>', $fiveSecondsAgo)
                ->whereNotNull('paid_time')
                ->count();

            return [
                'total_orders' => $totalOrders,
                'notified_success' => $notifiedSuccess,
                'unnotified' => $unnotified,
                'notify_failed' => $notifyFailed,
                'no_notify_url' => $noNotifyUrl,
                'recent_paid' => $recentPaid,
                'notification_rate' => $totalOrders > 0 ? round(($notifiedSuccess / $totalOrders) * 100, 2) : 0,
                'time_range' => $hours . '小时',
                'start_time' => $startTime,
                'filter_description' => '基于支付时间统计，监控支付成功但5秒内没有通知的订单'
            ];

        } catch (\Exception $e) {
            Log::error('获取通知统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 修复未通知订单
     * 专门处理支付成功但5秒内没有通知的订单
     */
    public function fixUnnotifiedOrders(int $hours = 24, int $status = 3, int $limit = 100): array
    {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $fiveSecondsAgo = date('Y-m-d H:i:s', time() - 5);
            
            // 查找支付成功但5秒内没有通知的订单
            $orders = Order::where('status', $status)
                ->where('notify_status', 0) // 未通知
                ->where('notify_url', '!=', '') // 有通知地址
                ->where('paid_time', '>=', $startTime) // 支付时间在指定范围内
                ->where('paid_time', '<=', $fiveSecondsAgo) // 支付时间超过5秒
                ->whereNotNull('paid_time') // 有支付时间
                ->limit($limit)
                ->get();
//            print_r($orders);

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
                            'message' => '已在队列中',
                            'paid_time' => $order->paid_time,
                            'time_since_paid' => time() - strtotime($order->paid_time) . '秒'
                        ];
                        $skipCount++;
                        continue;
                    }

                    // 触发通知
                    $notificationService = new MerchantNotificationService();
                    $notificationService->notifyMerchantAsync($order);

                    $results[] = [
                        'order_no' => $order->order_no,
                        'status' => 'success',
                        'message' => '已触发通知',
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => time() - strtotime($order->paid_time) . '秒'
                    ];
                    $successCount++;

                    Log::info('修复未通知订单', [
                        'order_no' => $order->order_no,
                        'order_id' => $order->id,
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => time() - strtotime($order->paid_time) . '秒',
                        'notify_url' => $order->notify_url
                    ]);

                } catch (\Exception $e) {
                    $results[] = [
                        'order_no' => $order->order_no,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'paid_time' => $order->paid_time,
                        'time_since_paid' => time() - strtotime($order->paid_time) . '秒'
                    ];
                    $errorCount++;
                }
            }

            Log::info('修复未通知订单完成', [
                'total' => $orders->count(),
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'filter_conditions' => [
                    'status' => $status,
                    'notify_status' => 0,
                    'has_notify_url' => true,
                    'paid_time_range' => $startTime . ' - ' . $fiveSecondsAgo,
                    'description' => '支付成功但5秒内没有通知的订单'
                ]
            ]);

            return [
                'total' => $orders->count(),
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'results' => $results,
                'filter_conditions' => [
                    'status' => $status,
                    'notify_status' => 0,
                    'has_notify_url' => true,
                    'paid_time_range' => $startTime . ' - ' . $fiveSecondsAgo,
                    'description' => '支付成功但5秒内没有通知的订单'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('修复未通知订单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 检查订单是否在通知队列中
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
}
