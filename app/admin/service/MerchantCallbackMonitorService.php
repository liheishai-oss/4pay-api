<?php

namespace app\admin\service;

use app\model\Order;
use app\model\NotifyLog;
use app\model\Merchant;
use support\Redis;
use support\Log;
use app\service\notification\MerchantNotificationService;

/**
 * 商户回调监控服务
 */
class MerchantCallbackMonitorService
{
    private $notificationService;

    public function __construct()
    {
        $this->notificationService = new MerchantNotificationService();
    }

    /**
     * 根据回调URL获取商户名称
     * @param string $notifyUrl
     * @return string|null
     */
    private function getMerchantNameByUrl(string $notifyUrl): ?string
    {
        try {
            // 通过notify_url查找对应的商户
            $order = \app\model\Order::where('notify_url', $notifyUrl)
                ->with('merchant')
                ->first();
                
            if ($order && $order->merchant) {
                return $order->merchant->merchant_name;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('获取商户名称失败', [
                'notify_url' => $notifyUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取队列状态
     * @return array
     */
    public function getQueueStatus(): array
    {
        try {
            // 获取三个队列中待处理的任务数量
            $notifyQueue = Redis::lLen('merchant_notify_pending_queue');      // 待通知队列
            $retryQueue = Redis::zCard('merchant_notify_retry_queue');        // 重试队列
            $delayedQueue = Redis::zCard('merchant_notify_delayed_queue');    // 延迟队列
            
            return [
                // 基础队列数据
                'notifyQueue' => $notifyQueue,
                'retryQueue' => $retryQueue,
                'delayedQueue' => $delayedQueue,
                'lastUpdate' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取队列状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'notifyQueue' => 0,
                'retryQueue' => 0,
                'delayedQueue' => 0,
                'lastUpdate' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 获取实时监控数据
     * @param string $timeRange 时间范围
     * @return array
     */
    public function getRealTimeData(string $timeRange = '1m'): array
    {
        try {
            // 根据时间范围计算开始时间
            $startTime = $this->calculateStartTime($timeRange);
            
            // 通知总数
            $totalNotifications = NotifyLog::where('created_at', '>=', $startTime)->count();
            
            // 成功通知数
            $successNotifications = NotifyLog::where('created_at', '>=', $startTime)
                ->where('status', NotifyLog::STATUS_SUCCESS)
                ->count();
            
            // 失败通知数
            $failedNotifications = NotifyLog::where('created_at', '>=', $startTime)
                ->where('status', NotifyLog::STATUS_FAILED)
                ->count();
            
            // 成功率
            $successRate = $totalNotifications > 0 ? round(($successNotifications / $totalNotifications) * 100, 2) : 0;
            
            // 获取Redis中的实时数据
            $redisData = $this->getRedisRealTimeData();
            
            // 获取最近的通知日志
            $recentLogs = NotifyLog::with('order')
                ->where('created_at', '>=', $startTime)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'order_no' => $log->order ? $log->order->order_no : '',
                        'merchant_order_no' => $log->order ? $log->order->merchant_order_no : '',
                        'notify_url' => $log->notify_url,
                        'status' => $log->status,
                        'status_text' => $log->status_text,
                        'http_code' => $log->http_code,
                        'http_code_text' => $log->http_code_text,
                        'retry_count' => $log->retry_count,
                        'created_at' => $log->created_at,
                        'merchant_key' => md5($log->notify_url)
                    ];
                });

            // 获取超时检查相关数据
            $timeoutCheckData = $this->getTimeoutCheckData($timeRange);

            return [
                'summary' => [
                    'total_notifications' => $totalNotifications,
                    'success_notifications' => $successNotifications,
                    'failed_notifications' => $failedNotifications,
                    'success_rate' => $successRate,
                    'time_range' => $this->getTimeRangeText($timeRange)
                ],
                'redis_data' => $redisData,
                'recent_logs' => $recentLogs,
                'timeout_check' => $timeoutCheckData,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取实时监控数据异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 计算开始时间
     * @param string $timeRange
     * @return string
     */
    private function calculateStartTime(string $timeRange): string
    {
        $now = time();
        
        switch ($timeRange) {
            case '5s':
                return date('Y-m-d H:i:s', $now - 5);
            case '1m':
                return date('Y-m-d H:i:s', $now - 60);
            case '5m':
                return date('Y-m-d H:i:s', $now - 300);
            case '10m':
                return date('Y-m-d H:i:s', $now - 600);
            case '1h':
                return date('Y-m-d H:i:s', $now - 3600);
            case '1d':
                return date('Y-m-d H:i:s', $now - 86400);
            default:
                return date('Y-m-d H:i:s', $now - 60); // 默认1分钟
        }
    }

    /**
     * 获取时间范围文本
     * @param string $timeRange
     * @return string
     */
    private function getTimeRangeText(string $timeRange): string
    {
        $timeRangeMap = [
            '5s' => '最近5秒',
            '1m' => '最近1分钟',
            '5m' => '最近5分钟',
            '10m' => '最近10分钟',
            '1h' => '最近1小时',
            '1d' => '最近1天'
        ];
        
        return $timeRangeMap[$timeRange] ?? '最近1分钟';
    }

    /**
     * 获取超时检查相关数据
     * @param string $timeRange
     * @return array
     */
    private function getTimeoutCheckData(string $timeRange = '1m'): array
    {
        try {
            // 根据时间范围计算开始时间
            $startTime = $this->calculateStartTime($timeRange);
            
            // 统计被超时检查恢复的订单数量
            $recoveredOrders = Order::where('status', 3) // 支付成功
                ->where('updated_at', '>=', $startTime)
                ->whereNotNull('paid_time')
                ->whereRaw('updated_at > paid_time') // 更新时间晚于支付时间，说明是后来更新的
                ->count();
            
            // 统计被超时检查关闭的订单数量
            $closedOrders = Order::where('status', 6) // 已关闭
                ->where('updated_at', '>=', $startTime)
                ->whereNotNull('closed_time')
                ->count();
            
            // 统计当前待检查的订单数量
            $pendingCheckOrders = Order::whereIn('status', [1, 2]) // 待支付、支付中
                ->whereNull('paid_time')
                ->count();
            
            // 统计被错误关闭但实际已支付的订单
            $incorrectlyClosedOrders = Order::where('status', 6) // 已关闭
                ->whereNotNull('third_party_order_no')
                ->whereNull('paid_time')
                ->count();
            
            return [
                'recovered_orders' => $recoveredOrders,
                'closed_orders' => $closedOrders,
                'pending_check_orders' => $pendingCheckOrders,
                'incorrectly_closed_orders' => $incorrectlyClosedOrders,
                'time_range' => $this->getTimeRangeText($timeRange)
            ];
        } catch (\Exception $e) {
            Log::error('获取超时检查数据异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'recovered_orders' => 0,
                'closed_orders' => 0,
                'pending_check_orders' => 0,
                'incorrectly_closed_orders' => 0,
                'time_range' => $this->getTimeRangeText($timeRange)
            ];
        }
    }

    /**
     * 获取Redis中的实时数据
     * @return array
     */
    private function getRedisRealTimeData(): array
    {
        try {
            // 获取重试队列长度
            $retryQueueLength = Redis::zCard('merchant_notify_retry_queue');
            
            // 获取延迟队列长度
            $delayedQueueLength = Redis::zCard('merchant_notify_delayed_queue');
            
            // 获取熔断器数量
            $circuitBreakerKeys = Redis::keys('merchant_circuit_breaker:*');
            $circuitBreakerCount = count($circuitBreakerKeys);
            
            // 获取失败计数
            $failureKeys = Redis::keys('merchant_failure_count:*');
            $failureCount = count($failureKeys);
            
            // 获取超时记录
            $timeoutKeys = Redis::keys('merchant_timeout:*');
            $timeoutCount = count($timeoutKeys);
            
            return [
                'retry_queue_length' => $retryQueueLength,
                'delayed_queue_length' => $delayedQueueLength,
                'circuit_breaker_count' => $circuitBreakerCount,
                'failure_count' => $failureCount,
                'timeout_count' => $timeoutCount
            ];
        } catch (\Exception $e) {
            Log::warning('获取Redis实时数据失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'retry_queue_length' => 0,
                'delayed_queue_length' => 0,
                'circuit_breaker_count' => 0,
                'failure_count' => 0,
                'timeout_count' => 0
            ];
        }
    }

    /**
     * 获取商户状态统计
     * @param string $timeRange
     * @return array
     */
    public function getMerchantStats(string $timeRange = '1m'): array
    {
        try {
            // 获取所有商户的通知URL
            $merchantUrls = Order::whereNotNull('notify_url')
                ->where('notify_url', '!=', '')
                ->distinct()
                ->pluck('notify_url')
                ->toArray();
            
            $merchantStats = [];
            $totalMerchants = 0;
            $normalMerchants = 0;
            $slowMerchants = 0;
            $circuitBreakerMerchants = 0;
            
            foreach ($merchantUrls as $url) {
                $merchantKey = md5($url);
                $status = $this->notificationService->getMerchantStatus($url);
                
                // 获取商户名称
                $merchantName = $this->getMerchantNameByUrl($url);
                
                $merchantStats[] = [
                    'merchant_key' => $merchantKey,
                    'merchant_name' => $merchantName,
                    'notify_url' => $url,
                    'status' => $status['status'],
                    'failure_count' => $status['failure_count'],
                    'avg_response_time' => $status['avg_response_time'],
                    'is_circuit_breaker_open' => $status['is_circuit_breaker_open'],
                    'is_slow_merchant' => $status['is_slow_merchant']
                ];
                
                $totalMerchants++;
                switch ($status['status']) {
                    case 'normal':
                        $normalMerchants++;
                        break;
                    case 'slow':
                        $slowMerchants++;
                        break;
                    case 'circuit_breaker':
                        $circuitBreakerMerchants++;
                        break;
                }
            }
            
            return [
                'summary' => [
                    'total_merchants' => $totalMerchants,
                    'normal_merchants' => $normalMerchants,
                    'slow_merchants' => $slowMerchants,
                    'circuit_breaker_merchants' => $circuitBreakerMerchants
                ],
                'merchants' => $merchantStats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取商户状态统计异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 获取通知日志列表
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array
     */
    public function getNotifyLogs(int $page, int $limit, array $filters = []): array
    {
        try {
            $query = NotifyLog::with('order');

            // 订单号筛选
            if (!empty($filters['order_no'])) {
                $query->whereHas('order', function($q) use ($filters) {
                    $q->where('order_no', 'like', "%{$filters['order_no']}%")
                      ->orWhere('merchant_order_no', 'like', "%{$filters['order_no']}%");
                });
            }

            // 状态筛选
            if ($filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }

            // 商户标识筛选
            if (!empty($filters['merchant_key'])) {
                $query->whereRaw('MD5(notify_url) = ?', [$filters['merchant_key']]);
            }

            // 时间范围筛选
            if (!empty($filters['start_time'])) {
                $query->where('created_at', '>=', $filters['start_time']);
            }
            if (!empty($filters['end_time'])) {
                $query->where('created_at', '<=', $filters['end_time']);
            }

            $total = $query->count();
            $logs = $query->orderBy('created_at', 'desc')
                         ->offset(($page - 1) * $limit)
                         ->limit($limit)
                         ->get()
                         ->map(function($log) {
                             return [
                                 'id' => $log->id,
                                 'order_id' => $log->order_id,
                                 'order_no' => $log->order ? $log->order->order_no : '',
                                 'merchant_order_no' => $log->order ? $log->order->merchant_order_no : '',
                                 'notify_url' => $log->notify_url,
                                 'merchant_key' => md5($log->notify_url),
                                 'request_data' => $log->request_data,
                                 'response_data' => $log->response_data,
                                 'http_code' => $log->http_code,
                                 'http_code_text' => $log->http_code_text,
                                 'status' => $log->status,
                                 'status_text' => $log->status_text,
                                 'retry_count' => $log->retry_count,
                                 'created_at' => $log->created_at
                             ];
                         });

            return [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'list' => $logs
            ];
        } catch (\Exception $e) {
            Log::error('获取通知日志异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 获取商户详情
     * @param string $merchantKey
     * @return array
     */
    public function getMerchantDetail(string $merchantKey): array
    {
        try {
            // 根据merchant_key找到对应的notify_url
            $notifyUrl = $this->getNotifyUrlByMerchantKey($merchantKey);
            if (!$notifyUrl) {
                throw new \Exception('商户不存在');
            }

            // 获取商户状态
            $status = $this->notificationService->getMerchantStatus($notifyUrl);
            
            // 获取最近的通知记录
            $recentLogs = NotifyLog::where('notify_url', $notifyUrl)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'order_no' => $log->order ? $log->order->order_no : '',
                        'status' => $log->status,
                        'status_text' => $log->status_text,
                        'http_code' => $log->http_code,
                        'retry_count' => $log->retry_count,
                        'created_at' => $log->created_at
                    ];
                });

            // 获取统计信息
            $stats = $this->getMerchantNotificationStats($notifyUrl);

            return [
                'merchant_key' => $merchantKey,
                'notify_url' => $notifyUrl,
                'status' => $status,
                'recent_logs' => $recentLogs,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取商户详情异常', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 根据商户标识获取通知URL
     * @param string $merchantKey
     * @return string|null
     */
    private function getNotifyUrlByMerchantKey(string $merchantKey): ?string
    {
        try {
            $order = Order::whereRaw('MD5(notify_url) = ?', [$merchantKey])
                ->whereNotNull('notify_url')
                ->where('notify_url', '!=', '')
                ->first();
            
            return $order ? $order->notify_url : null;
        } catch (\Exception $e) {
            Log::error('根据商户标识获取通知URL异常', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取商户通知统计
     * @param string $notifyUrl
     * @return array
     */
    private function getMerchantNotificationStats(string $notifyUrl): array
    {
        try {
            $oneDayAgo = date('Y-m-d H:i:s', time() - 86400);
            $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
            
            // 最近24小时统计
            $total24h = NotifyLog::where('notify_url', $notifyUrl)
                ->where('created_at', '>=', $oneDayAgo)
                ->count();
            
            $success24h = NotifyLog::where('notify_url', $notifyUrl)
                ->where('created_at', '>=', $oneDayAgo)
                ->where('status', NotifyLog::STATUS_SUCCESS)
                ->count();
            
            // 最近1小时统计
            $total1h = NotifyLog::where('notify_url', $notifyUrl)
                ->where('created_at', '>=', $oneHourAgo)
                ->count();
            
            $success1h = NotifyLog::where('notify_url', $notifyUrl)
                ->where('created_at', '>=', $oneHourAgo)
                ->where('status', NotifyLog::STATUS_SUCCESS)
                ->count();
            
            return [
                'last_24h' => [
                    'total' => $total24h,
                    'success' => $success24h,
                    'success_rate' => $total24h > 0 ? round(($success24h / $total24h) * 100, 2) : 0
                ],
                'last_1h' => [
                    'total' => $total1h,
                    'success' => $success1h,
                    'success_rate' => $total1h > 0 ? round(($success1h / $total1h) * 100, 2) : 0
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取商户通知统计异常', [
                'notify_url' => $notifyUrl,
                'error' => $e->getMessage()
            ]);
            return [
                'last_24h' => ['total' => 0, 'success' => 0, 'success_rate' => 0],
                'last_1h' => ['total' => 0, 'success' => 0, 'success_rate' => 0]
            ];
        }
    }

    /**
     * 重置商户熔断器
     * @param string $merchantKey
     * @return bool
     */
    public function resetMerchantCircuitBreaker(string $merchantKey): bool
    {
        try {
            $notifyUrl = $this->getNotifyUrlByMerchantKey($merchantKey);
            if (!$notifyUrl) {
                return false;
            }

            // 删除熔断器
            $circuitBreakerKey = "merchant_circuit_breaker:{$merchantKey}";
            Redis::del($circuitBreakerKey);
            
            // 重置失败次数
            $failureKey = "merchant_failure_count:{$merchantKey}";
            Redis::del($failureKey);
            
            Log::info('手动重置商户熔断器', [
                'merchant_key' => $merchantKey,
                'notify_url' => $notifyUrl
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('重置商户熔断器异常', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 手动触发商户通知
     * @param int $orderId
     * @return bool
     */
    public function triggerMerchantNotify(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);
            if (!$order || empty($order->notify_url)) {
                return false;
            }

            // 使用通知服务触发通知
            $this->notificationService->notifyMerchantAsync($order);
            
            Log::info('手动触发商户通知', [
                'order_id' => $orderId,
                'order_no' => $order->order_no,
                'notify_url' => $order->notify_url
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('手动触发商户通知异常', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取支付成功但回调失败的订单
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPaidButCallbackFailedOrders(int $page = 1, int $limit = 20): array
    {
        try {
            $query = Order::where('status', Order::STATUS_SUCCESS) // 支付成功
                ->where('notify_status', '!=', Order::NOTIFY_STATUS_SUCCESS); // 回调未成功

            $total = $query->count();
            $orders = $query->orderBy('created_at', 'desc')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get();

            $orderList = [];
            foreach ($orders as $order) {
                // 获取最新的通知日志
                $latestLog = NotifyLog::where('order_id', $order->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $orderList[] = [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'merchant_order_no' => $order->merchant_order_no,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'status_text' => $this->getOrderStatusText($order->status),
                    'notify_status' => $order->notify_status,
                    'notify_status_text' => $this->getNotifyStatusText($order->notify_status),
                    'notify_count' => $order->notify_count,
                    'notify_url' => $order->notify_url,
                    'created_at' => $order->created_at,
                    'paid_time' => $order->paid_time,
                    'latest_notify_log' => $latestLog ? [
                        'id' => $latestLog->id,
                        'http_code' => $latestLog->http_code,
                        'status' => $latestLog->status,
                        'status_text' => $latestLog->status == NotifyLog::STATUS_SUCCESS ? '成功' : '失败',
                        'retry_count' => $latestLog->retry_count,
                        'response_data' => $latestLog->response_data,
                        'created_at' => $latestLog->created_at
                    ] : null
                ];
            }

            return [
                'orders' => $orderList,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];

        } catch (\Exception $e) {
            Log::error('获取支付成功但回调失败的订单异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 获取订单状态文本
     * @param int $status
     * @return string
     */
    private function getOrderStatusText(int $status): string
    {
        $statusMap = [
            Order::STATUS_PENDING => '待支付',
            Order::STATUS_PAYING => '支付中',
            Order::STATUS_SUCCESS => '支付成功',
            Order::STATUS_FAILED => '支付失败',
            Order::STATUS_REFUNDED => '已退款',
            Order::STATUS_CLOSED => '已关闭'
        ];
        return $statusMap[$status] ?? '未知';
    }

    /**
     * 获取通知状态文本
     * @param int $status
     * @return string
     */
    private function getNotifyStatusText(int $status): string
    {
        $statusMap = [
            Order::NOTIFY_STATUS_NONE => '未通知',
            Order::NOTIFY_STATUS_SUCCESS => '通知成功',
            Order::NOTIFY_STATUS_FAILED => '通知失败'
        ];
        return $statusMap[$status] ?? '未知';
    }

    /**
     * 获取系统健康状态
     * @return array
     */
    public function getSystemHealth(): array
    {
        try {
            // 检查Redis连接
            $redisHealthy = $this->checkRedisHealth();
            
            // 检查数据库连接
            $dbHealthy = $this->checkDatabaseHealth();
            
            // 检查队列状态
            $queueHealthy = $this->checkQueueHealth();
            
            // 检查通知服务状态
            $notificationHealthy = $this->checkNotificationHealth();
            
            // 检查超时监控进程状态
            $timeoutMonitorHealthy = $this->checkTimeoutMonitorHealth();
            
            $overallHealth = $redisHealthy && $dbHealthy && $queueHealthy && $notificationHealthy && $timeoutMonitorHealthy;
            
            return [
                'overall_health' => $overallHealth,
                'status' => $overallHealth ? '健康' : '异常',
                'components' => [
                    'redis' => [
                        'healthy' => $redisHealthy,
                        'status' => $redisHealthy ? '已连接' : '连接断开',
                        'name' => 'Redis缓存'
                    ],
                    'database' => [
                        'healthy' => $dbHealthy,
                        'status' => $dbHealthy ? '已连接' : '连接断开',
                        'name' => '数据库'
                    ],
                    'queue' => [
                        'healthy' => $queueHealthy,
                        'status' => $queueHealthy ? '正常' : '异常',
                        'name' => '消息队列'
                    ],
                    'notification' => [
                        'healthy' => $notificationHealthy,
                        'status' => $notificationHealthy ? '正常' : '异常',
                        'name' => '通知服务'
                    ],
                    'timeout_monitor' => [
                        'healthy' => $timeoutMonitorHealthy,
                        'status' => $timeoutMonitorHealthy ? '正常运行' : '异常',
                        'name' => '超时监控进程'
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取系统健康状态异常', [
                'error' => $e->getMessage()
            ]);
            return [
                'overall_health' => false,
                'status' => '错误',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 检查Redis健康状态
     * @return bool
     */
    private function checkRedisHealth(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查数据库健康状态
     * @return bool
     */
    private function checkDatabaseHealth(): bool
    {
        try {
            \support\Db::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查队列健康状态
     * @return bool
     */
    private function checkQueueHealth(): bool
    {
        try {
            // 检查重试队列和延迟队列是否正常
            $retryQueueLength = Redis::zCard('merchant_notify_retry_queue');
            $delayedQueueLength = Redis::zCard('merchant_notify_delayed_queue');
            
            // 如果队列长度过大，认为不健康
            return ($retryQueueLength < 10000) && ($delayedQueueLength < 10000);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查通知服务健康状态
     * @return bool
     */
    private function checkNotificationHealth(): bool
    {
        try {
            // 检查最近1小时的通知成功率
            $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
            $total = NotifyLog::where('created_at', '>=', $oneHourAgo)->count();
            $success = NotifyLog::where('created_at', '>=', $oneHourAgo)
                ->where('status', NotifyLog::STATUS_SUCCESS)
                ->count();
            
            if ($total == 0) {
                return true; // 没有通知记录，认为健康
            }
            
            $successRate = ($success / $total) * 100;
            return $successRate >= 80; // 成功率低于80%认为不健康
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查超时监控进程健康状态
     * @return bool
     */
    private function checkTimeoutMonitorHealth(): bool
    {
        try {
            // 检查进程是否运行
            if (!$this->isTimeoutMonitorProcessRunning()) {
                return false;
            }
            
            // 检查最近是否有检查日志
            if (!$this->hasRecentTimeoutCheckLogs()) {
                return false;
            }
            
            // 检查超时监控统计数据
            $stats = $this->getTimeoutMonitorStats();
            if (isset($stats['error'])) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('检查超时监控进程健康状态失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查超时监控进程是否运行
     * @return bool
     */
    private function isTimeoutMonitorProcessRunning(): bool
    {
        try {
            // 检查进程是否存在
            $processName = 'OrderTimeoutCheckProcess';
            $command = "ps aux | grep '{$processName}' | grep -v grep";
            $output = shell_exec($command);
            
            return !empty(trim($output));
        } catch (\Exception $e) {
            Log::warning('检查超时监控进程状态失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查最近是否有超时检查日志
     * @return bool
     */
    private function hasRecentTimeoutCheckLogs(): bool
    {
        try {
            // 检查最近5分钟是否有超时检查日志
            $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
            
            // 这里需要检查日志文件，由于Log类可能没有直接查询方法，
            // 我们通过检查数据库中的相关记录来判断
            $recentLogs = Order::where('updated_at', '>=', $fiveMinutesAgo)
                ->whereIn('status', [3, 6]) // 支付成功或已关闭
                ->whereRaw('updated_at > created_at') // 有更新记录
                ->count();
            
            // 如果有订单状态更新，说明监控在运行
            return $recentLogs > 0;
        } catch (\Exception $e) {
            Log::warning('检查超时监控日志失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取超时监控统计信息
     * @return array
     */
    public function getTimeoutMonitorStats(): array
    {
        try {
            // 获取订单有效期配置
            $orderValidityMinutes = $this->getOrderValidityMinutes();
            $timeoutTime = date('Y-m-d H:i:s', time() - ($orderValidityMinutes * 60));
            
            // 统计待检查的订单
            $pendingOrders = Order::whereIn('status', [1, 2])
                ->whereNull('paid_time')
                ->count();
            
            // 统计超时订单
            $timeoutOrders = Order::whereIn('status', [1, 2])
                ->where('created_at', '<', $timeoutTime)
                ->whereNull('paid_time')
                ->count();
            
            // 统计今日处理的订单
            $todayProcessed = Order::whereIn('status', [3, 6])
                ->whereDate('updated_at', date('Y-m-d'))
                ->whereRaw('updated_at > created_at')
                ->count();
            
            // 统计最近1小时处理的订单
            $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
            $recentProcessed = Order::whereIn('status', [3, 6])
                ->where('updated_at', '>=', $oneHourAgo)
                ->whereRaw('updated_at > created_at')
                ->count();
            
            return [
                'order_validity_minutes' => $orderValidityMinutes,
                'timeout_time' => $timeoutTime,
                'pending_orders' => $pendingOrders,
                'timeout_orders' => $timeoutOrders,
                'today_processed' => $todayProcessed,
                'recent_processed' => $recentProcessed,
                'last_check_time' => date('Y-m-d H:i:s'),
                'process_status' => $this->isTimeoutMonitorProcessRunning() ? 'running' : 'stopped'
            ];
        } catch (\Exception $e) {
            Log::error('获取超时监控统计信息失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => $e->getMessage(),
                'last_check_time' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 获取订单有效期配置
     * @return int
     */
    private function getOrderValidityMinutes(): int
    {
        try {
            $config = \app\model\SystemConfig::where('config_key', 'payment.order_validity_minutes')->first();
            if ($config) {
                return (int)$config->config_value;
            }
        } catch (\Exception $e) {
            Log::warning('获取订单有效期配置失败，使用默认值', [
                'error' => $e->getMessage()
            ]);
        }
        
        return 30; // 默认30分钟
    }

    /**
     * 获取超时监控进程状态
     * @return array
     */
    public function getTimeoutMonitorStatus(): array
    {
        try {
            $isRunning = $this->isTimeoutMonitorProcessRunning();
            $hasRecentLogs = $this->hasRecentTimeoutCheckLogs();
            $stats = $this->getTimeoutMonitorStats();
            
            $status = 'unknown';
            $message = '';
            
            if ($isRunning && $hasRecentLogs) {
                $status = 'running';
                $message = '超时监控进程正常运行';
            } elseif ($isRunning && !$hasRecentLogs) {
                $status = 'warning';
                $message = '进程运行中但无最近活动';
            } elseif (!$isRunning) {
                $status = 'stopped';
                $message = '超时监控进程未运行';
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'is_running' => $isRunning,
                'has_recent_logs' => $hasRecentLogs,
                'stats' => $stats,
                'last_check_time' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('获取超时监控进程状态失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'error',
                'message' => '获取状态失败: ' . $e->getMessage(),
                'is_running' => false,
                'has_recent_logs' => false,
                'stats' => [],
                'last_check_time' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 重启超时监控进程
     * @return array
     */
    public function restartTimeoutMonitor(): array
    {
        try {
            // 停止现有进程
            $this->stopTimeoutMonitorProcess();
            
            // 等待一秒
            sleep(1);
            
            // 启动新进程
            $result = $this->startTimeoutMonitorProcess();
            
            if ($result['success']) {
                Log::info('超时监控进程重启成功');
                return [
                    'success' => true,
                    'message' => '超时监控进程重启成功',
                    'restart_time' => date('Y-m-d H:i:s')
                ];
            } else {
                Log::error('超时监控进程重启失败', [
                    'error' => $result['message']
                ]);
                return [
                    'success' => false,
                    'message' => '重启失败: ' . $result['message'],
                    'restart_time' => date('Y-m-d H:i:s')
                ];
            }
        } catch (\Exception $e) {
            Log::error('重启超时监控进程异常', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => '重启异常: ' . $e->getMessage(),
                'restart_time' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 停止超时监控进程
     * @return bool
     */
    private function stopTimeoutMonitorProcess(): bool
    {
        try {
            $command = "pkill -f 'OrderTimeoutCheckProcess'";
            $output = shell_exec($command);
            
            Log::info('停止超时监控进程', [
                'command' => $command,
                'output' => $output
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('停止超时监控进程失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 启动超时监控进程
     * @return array
     */
    private function startTimeoutMonitorProcess(): array
    {
        try {
            // 获取项目根目录
            $rootPath = base_path();
            $command = "cd {$rootPath} && nohup php start.php start > /dev/null 2>&1 &";
            
            $output = shell_exec($command);
            
            // 等待2秒后检查进程是否启动
            sleep(2);
            $isRunning = $this->isTimeoutMonitorProcessRunning();
            
            if ($isRunning) {
                Log::info('超时监控进程启动成功');
                return [
                    'success' => true,
                    'message' => '进程启动成功'
                ];
            } else {
                Log::error('超时监控进程启动失败');
                return [
                    'success' => false,
                    'message' => '进程启动失败'
                ];
            }
        } catch (\Exception $e) {
            Log::error('启动超时监控进程异常', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => '启动异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取队列性能指标
     * @return array
     */
    private function getQueueMetrics(): array
    {
        try {
            $now = time();
            $oneHourAgo = $now - 3600;
            
            // 获取处理速度指标
            $processedCount = Redis::get('merchant_queue_processed_count') ?: 0;
            $processingRate = $this->calculateProcessingRate();
            
            // 获取平均处理时间
            $avgProcessingTime = $this->getAverageProcessingTime();
            
            // 获取队列积压情况
            $backlogAnalysis = $this->analyzeBacklog();
            
            // 获取吞吐量数据
            $throughput = $this->getThroughputMetrics();
            
            return [
                'processedCount' => (int)$processedCount,
                'processingRate' => $processingRate,
                'avgProcessingTime' => $avgProcessingTime,
                'backlogAnalysis' => $backlogAnalysis,
                'throughput' => $throughput,
                'timestamp' => $now
            ];
        } catch (\Exception $e) {
            Log::error('获取队列性能指标失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'processedCount' => 0,
                'processingRate' => 0,
                'avgProcessingTime' => 0,
                'backlogAnalysis' => null,
                'throughput' => null,
                'timestamp' => time()
            ];
        }
    }




    /**
     * 获取队列健康状态
     * @param int $notifyQueue
     * @param int $retryQueue
     * @param int $delayedQueue
     * @param int $failedQueue
     * @return array
     */
    private function getQueueHealthStatus(int $notifyQueue, int $retryQueue, int $delayedQueue, int $failedQueue): array
    {
        $healthScore = 100;
        $issues = [];
        $warnings = [];
        
        // 检查队列积压
        if ($notifyQueue > 500) {
            $healthScore -= 20;
            $issues[] = '待通知队列积压严重';
        } elseif ($notifyQueue > 200) {
            $healthScore -= 10;
            $warnings[] = '待通知队列积压';
        }
        
        if ($retryQueue > 200) {
            $healthScore -= 15;
            $issues[] = '重试队列积压严重';
        } elseif ($retryQueue > 100) {
            $healthScore -= 5;
            $warnings[] = '重试队列积压';
        }
        
        if ($failedQueue > 50) {
            $healthScore -= 25;
            $issues[] = '失败队列积压严重';
        } elseif ($failedQueue > 20) {
            $healthScore -= 10;
            $warnings[] = '失败队列积压';
        }
        
        // 确定健康状态
        if ($healthScore >= 90) {
            $status = 'healthy';
            $statusText = '健康';
        } elseif ($healthScore >= 70) {
            $status = 'warning';
            $statusText = '警告';
        } else {
            $status = 'critical';
            $statusText = '严重';
        }
        
        return [
            'status' => $status,
            'statusText' => $statusText,
            'healthScore' => max(0, $healthScore),
            'issues' => $issues,
            'warnings' => $warnings,
            'timestamp' => time()
        ];
    }

    /**
     * 计算处理速度
     * @return float
     */
    private function calculateProcessingRate(): float
    {
        try {
            $now = time();
            $oneHourAgo = $now - 3600;
            
            $processedCount = Redis::get('merchant_queue_processed_count') ?: 0;
            $rate = $processedCount / 3600; // 每秒处理数量
            
            return round($rate, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 获取平均处理时间
     * @return float
     */
    private function getAverageProcessingTime(): float
    {
        try {
            $avgTime = Redis::get('merchant_queue_avg_processing_time');
            return $avgTime ? (float)$avgTime : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 分析积压情况
     * @return array
     */
    private function analyzeBacklog(): array
    {
        try {
            $now = time();
            $backlogData = [];
            
            // 分析待通知队列积压
            $pendingItems = Redis::lRange('merchant_notify_pending_queue', 0, 9);
            $oldestItem = null;
            $newestItem = null;
            
            foreach ($pendingItems as $item) {
                $data = json_decode($item, true);
                if ($data && isset($data['created_at'])) {
                    if (!$oldestItem || $data['created_at'] < $oldestItem) {
                        $oldestItem = $data['created_at'];
                    }
                    if (!$newestItem || $data['created_at'] > $newestItem) {
                        $newestItem = $data['created_at'];
                    }
                }
            }
            
            $backlogData['oldestItemAge'] = $oldestItem ? ($now - $oldestItem) : 0;
            $backlogData['newestItemAge'] = $newestItem ? ($now - $newestItem) : 0;
            $backlogData['avgAge'] = $oldestItem && $newestItem ? (($now - $oldestItem) + ($now - $newestItem)) / 2 : 0;
            
            return $backlogData;
        } catch (\Exception $e) {
            return [
                'oldestItemAge' => 0,
                'newestItemAge' => 0,
                'avgAge' => 0
            ];
        }
    }

    /**
     * 获取吞吐量指标
     * @return array
     */
    private function getThroughputMetrics(): array
    {
        try {
            $now = time();
            $oneHourAgo = $now - 3600;
            
            $throughput = Redis::get('merchant_queue_throughput');
            $throughputData = $throughput ? json_decode($throughput, true) : [
                'requests_per_second' => 0,
                'peak_throughput' => 0,
                'avg_throughput' => 0
            ];
            
            return $throughputData;
        } catch (\Exception $e) {
            return [
                'requests_per_second' => 0,
                'peak_throughput' => 0,
                'avg_throughput' => 0
            ];
        }
    }

}
