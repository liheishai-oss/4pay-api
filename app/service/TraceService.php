<?php

namespace app\service;

use app\model\OrderLifecycleTrace;
use app\model\OrderQueryTrace;
use app\model\Order;
use support\Log;
use Illuminate\Support\Str;

class TraceService
{
    /**
     * 记录订单生命周期步骤
     * @param string $traceId 追踪ID
     * @param int $orderId 订单ID
     * @param int $merchantId 商户ID
     * @param string $stepName 步骤名称
     * @param string $status 步骤状态
     * @param array $data 步骤数据
     * @param int|null $parentStepId 父步骤ID
     * @param int $durationMs 步骤耗时(毫秒)
     * @return void
     */
    public function logLifecycleStep(
        string $traceId,
        int $orderId,
        int $merchantId,
        string $stepName,
        string $status,
        array $data = [],
        ?int $parentStepId = null,
        float $durationMs = 0,
        ?string $orderNo = null,
        ?string $merchantOrderNo = null
    ): void {
        try {
            OrderLifecycleTrace::create([
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'merchant_order_no' => $merchantOrderNo,
                'merchant_id' => $merchantId,
                'step_name' => $stepName,
                'step_status' => $status,
                'step_data' => $data,
                'parent_step_id' => $parentStepId,
                'duration_ms' => $durationMs,
                'created_at' => $this->getMicrosecondTimestamp()
            ]);

            // 同时记录到现有日志系统，保持兼容性
            Log::info("Lifecycle Step: {$stepName}", [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'merchant_id' => $merchantId,
                'status' => $status,
                'duration_ms' => $durationMs,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log lifecycle step', [
                'trace_id' => $traceId,
                'step_name' => $stepName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 记录订单查询步骤
     * @param string $traceId 追踪ID
     * @param int|null $orderId 订单ID
     * @param int|null $merchantId 商户ID
     * @param string $queryType 查询类型
     * @param string $stepName 步骤名称
     * @param string $status 步骤状态
     * @param array $data 步骤数据
     * @param int $durationMs 步骤耗时(毫秒)
     * @return void
     */
    public function logQueryStep(
        string $traceId,
        ?int $orderId,
        ?int $merchantId,
        string $queryType,
        string $stepName,
        string $status,
        array $data = [],
        float $durationMs = 0,
        ?string $orderNo = null,
        ?string $merchantOrderNo = null
    ): void {
        try {
            OrderQueryTrace::create([
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'merchant_order_no' => $merchantOrderNo,
                'merchant_id' => $merchantId,
                'query_type' => $queryType,
                'step_name' => $stepName,
                'step_status' => $status,
                'step_data' => $data,
                'duration_ms' => $durationMs,
                'created_at' => $this->getMicrosecondTimestamp()
            ]);

            // 同时记录到现有日志系统，保持兼容性
            Log::info("Query Step: {$stepName}", [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'merchant_id' => $merchantId,
                'query_type' => $queryType,
                'status' => $status,
                'duration_ms' => $durationMs,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log query step', [
                'trace_id' => $traceId,
                'step_name' => $stepName,
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * 获取订单生命周期完整链路
     * @param string $traceId 追踪ID
     * @return array
     */
    public function getLifecycleTrace(string $traceId): array
    {
        try {
            $steps = OrderLifecycleTrace::byTraceId($traceId)
                ->orderByCreated('asc')
                ->get()
                ->toArray();

            if (empty($steps)) {
                throw new \Exception("Lifecycle trace not found: {$traceId}");
            }

            $firstStep = $steps[0];
            $traceData = [
                'trace_id' => $traceId,
                'order_id' => $firstStep['order_id'],
                'merchant_id' => $firstStep['merchant_id'],
                'start_time' => $firstStep['created_at'],
                'end_time' => end($steps)['created_at'],
                'total_steps' => count($steps),
                'success_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderLifecycleTrace::STATUS_SUCCESS)),
                'failed_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderLifecycleTrace::STATUS_FAILED)),
                'pending_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderLifecycleTrace::STATUS_PENDING)),
                'total_duration' => array_sum(array_column($steps, 'duration_ms')),
                'steps' => $steps
            ];

            return $traceData;
        } catch (\Exception $e) {
            Log::error('Failed to get lifecycle trace', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取订单查询完整链路
     * @param string $traceId 追踪ID
     * @return array
     */
    public function getQueryTrace(string $traceId): array
    {
        try {
            $steps = OrderQueryTrace::byTraceId($traceId)
                ->orderByCreated('asc')
                ->get()
                ->toArray();

            if (empty($steps)) {
                throw new \Exception("Query trace not found: {$traceId}");
            }

            $firstStep = $steps[0];
            $traceData = [
                'trace_id' => $traceId,
                'order_id' => $firstStep['order_id'],
                'merchant_id' => $firstStep['merchant_id'],
                'query_type' => $firstStep['query_type'],
                'start_time' => $firstStep['created_at'],
                'end_time' => end($steps)['created_at'],
                'total_steps' => count($steps),
                'success_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderQueryTrace::STATUS_SUCCESS)),
                'failed_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderQueryTrace::STATUS_FAILED)),
                'pending_steps' => count(array_filter($steps, fn($step) => $step['step_status'] === OrderQueryTrace::STATUS_PENDING)),
                'total_duration' => array_sum(array_column($steps, 'duration_ms')),
                'steps' => $steps
            ];

            return $traceData;
        } catch (\Exception $e) {
            Log::error('Failed to get query trace', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 搜索链路（支持订单号和trace_id）
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function searchTraces(string $keyword, int $page = 1, int $limit = 20): array
    {
        try {
            // 搜索生命周期追踪
            $lifecycleQuery = OrderLifecycleTrace::where('trace_id', 'like', "%{$keyword}%")
                ->orWhere('order_id', 'like', "%{$keyword}%")
                ->orWhere('order_no', 'like', "%{$keyword}%")
                ->orWhere('merchant_order_no', 'like', "%{$keyword}%");

            $lifecycleTotal = $lifecycleQuery->count();
            $lifecycleSteps = $lifecycleQuery->orderByCreated('desc')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->toArray();

            // 搜索查询追踪
            $queryQuery = OrderQueryTrace::where('trace_id', 'like', "%{$keyword}%")
                ->orWhere('order_id', 'like', "%{$keyword}%")
                ->orWhere('order_no', 'like', "%{$keyword}%")
                ->orWhere('merchant_order_no', 'like', "%{$keyword}%");

            $queryTotal = $queryQuery->count();
            $querySteps = $queryQuery->orderByCreated('desc')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->toArray();

            // 合并结果并按trace_id分组
            $allSteps = array_merge($lifecycleSteps, $querySteps);
            $traces = [];
            
            foreach ($allSteps as $step) {
                $traceId = $step['trace_id'];
                if (!isset($traces[$traceId])) {
                    $traces[$traceId] = [
                        'trace_id' => $traceId,
                        'order_id' => $step['order_id'],
                        'merchant_id' => $step['merchant_id'],
                        'latest_step' => $step,
                        'step_count' => 0,
                        'created_at' => $step['created_at'],
                        'trace_type' => isset($step['query_type']) ? 'query' : 'lifecycle'
                    ];
                }
                $traces[$traceId]['step_count']++;
            }

            $total = $lifecycleTotal + $queryTotal;

            return [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit),
                'traces' => array_values($traces)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search traces', [
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取链路统计信息
     * @param string $traceId 追踪ID
     * @param string $type 追踪类型 (lifecycle|query)
     * @return array
     */
    public function getTraceStatistics(string $traceId, string $type = 'lifecycle'): array
    {
        try {
            if ($type === 'lifecycle') {
                $steps = OrderLifecycleTrace::byTraceId($traceId)->get();
            } else {
                $steps = OrderQueryTrace::byTraceId($traceId)->get();
            }
            
            if ($steps->isEmpty()) {
                throw new \Exception("Trace not found: {$traceId}");
            }

            $statistics = [
                'trace_id' => $traceId,
                'trace_type' => $type,
                'total_steps' => $steps->count(),
                'success_steps' => $steps->where('step_status', 'success')->count(),
                'failed_steps' => $steps->where('step_status', 'failed')->count(),
                'pending_steps' => $steps->where('step_status', 'pending')->count(),
                'success_rate' => 0,
                'total_duration' => $steps->sum('duration_ms'),
                'average_duration' => 0,
                'step_breakdown' => []
            ];

            // 计算成功率
            if ($statistics['total_steps'] > 0) {
                $statistics['success_rate'] = round(($statistics['success_steps'] / $statistics['total_steps']) * 100, 2);
            }

            // 计算平均耗时
            $statistics['average_duration'] = $statistics['total_duration'] > 0 ? 
                round($statistics['total_duration'] / $statistics['total_steps'], 2) : 0;

            // 步骤分解统计
            $stepGroups = $steps->groupBy('step_name');
            foreach ($stepGroups as $stepName => $stepList) {
                $statistics['step_breakdown'][] = [
                    'step_name' => $stepName,
                    'count' => $stepList->count(),
                    'success_count' => $stepList->where('step_status', 'success')->count(),
                    'failed_count' => $stepList->where('step_status', 'failed')->count(),
                    'success_rate' => $stepList->count() > 0 ? 
                        round(($stepList->where('step_status', 'success')->count() / $stepList->count()) * 100, 2) : 0
                ];
            }

            return $statistics;
        } catch (\Exception $e) {
            Log::error('Failed to get trace statistics', [
                'trace_id' => $traceId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 清理过期的追踪数据
     * @param int $days 保留天数
     * @return array 清理结果
     */
    public function cleanExpiredTraces(int $days = 30): array
    {
        try {
            $expiredDate = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
            
            $lifecycleDeleted = OrderLifecycleTrace::where('created_at', '<', $expiredDate)->delete();
            $queryDeleted = OrderQueryTrace::where('created_at', '<', $expiredDate)->delete();
            
            $result = [
                'lifecycle_deleted' => $lifecycleDeleted,
                'query_deleted' => $queryDeleted,
                'total_deleted' => $lifecycleDeleted + $queryDeleted,
                'expired_date' => $expiredDate
            ];
            
            Log::info('Expired traces cleaned', $result);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to clean expired traces', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取微秒级时间戳
     * @return string
     */
    private function getMicrosecondTimestamp(): string
    {
        $microtime = microtime(true);
        $seconds = floor($microtime);
        $microseconds = ($microtime - $seconds) * 1000000;
        
        return date('Y-m-d H:i:s.', $seconds) . sprintf('%06d', $microseconds);
    }
}
