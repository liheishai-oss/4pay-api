<?php

namespace app\admin\service;

use app\model\Order;
use app\model\OrderStatistics;
use app\common\config\OrderConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use support\Log;

/**
 * 优化后的订单统计服务
 * 解决统计查询性能问题（从26秒优化到毫秒级别）
 */
class OptimizedOrderStatisticsService
{
    const CACHE_PREFIX = 'order_statistics:';
    const CACHE_TTL = OrderConfig::ADMIN_STATISTICS_CACHE_TTL; // 使用admin模块专用配置
    
    /**
     * 获取订单统计（优化版）
     * @param array $searchParams
     * @return array
     */
    public function getOrderStatistics(array $searchParams = []): array
    {
        // 1. 尝试从缓存获取
        $cacheKey = $this->generateCacheKey($searchParams);
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            Log::info('订单统计命中缓存', ['cache_key' => $cacheKey]);
            return $cachedResult;
        }
        
        // 2. 根据查询条件选择最优策略
        $result = $this->getStatisticsByStrategy($searchParams);
        
        // 3. 缓存结果
        Cache::put($cacheKey, $result, self::CACHE_TTL);
        
        Log::info('订单统计查询完成', [
            'search_params' => $searchParams,
            'cache_key' => $cacheKey,
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ]);
        
        return $result;
    }
    
    /**
     * 根据查询条件选择最优策略
     * @param array $searchParams
     * @return array
     */
    private function getStatisticsByStrategy(array $searchParams): array
    {
        // 策略1: 无时间范围查询 - 使用统计表
        if (empty($searchParams['start_time']) && empty($searchParams['end_time'])) {
            return $this->getStatisticsFromSummaryTable($searchParams);
        }
        
        // 策略2: 有时间范围查询 - 使用优化查询
        return $this->getStatisticsWithTimeRange($searchParams);
    }
    
    /**
     * 从统计汇总表获取数据（最快）
     * @param array $searchParams
     * @return array
     */
    private function getStatisticsFromSummaryTable(array $searchParams): array
    {
        $query = OrderStatistics::query();
        
        // 应用商户筛选
        if (!empty($searchParams['merchant_id'])) {
            $query->where('merchant_id', $searchParams['merchant_id']);
        }
        
        // 获取最新统计数据
        $statistics = $query->orderBy('stat_date', 'desc')->first();
        
        if (!$statistics) {
            return $this->getEmptyStatistics();
        }
        
        return [
            'total_orders' => $statistics->total_orders,
            'total_amount' => round($statistics->total_amount / 100, 2), // 分转元
            'paid_orders' => $statistics->paid_orders,
            'paid_amount' => round($statistics->paid_amount / 100, 2), // 分转元
            'pending_orders' => $statistics->pending_orders,
            'pending_amount' => round($statistics->pending_amount / 100, 2), // 分转元
            'failed_orders' => $statistics->failed_orders,
            'cancelled_orders' => $statistics->cancelled_orders,
            'success_rate' => $this->calculateSuccessRate($statistics->total_orders, $statistics->paid_orders),
            'data_source' => 'summary_table'
        ];
    }
    
    /**
     * 带时间范围的统计查询（优化版）
     * @param array $searchParams
     * @return array
     */
    private function getStatisticsWithTimeRange(array $searchParams): array
    {
        // 使用单次查询获取所有统计数据
        $query = Order::query();
        
        // 应用搜索条件
        $this->applySearchConditions($query, $searchParams);
        
        // 使用原生SQL进行优化查询
        $sql = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(CASE WHEN status = 2 THEN 1 END) as paid_orders,
                COALESCE(SUM(CASE WHEN status = 2 THEN amount ELSE 0 END), 0) as paid_amount,
                COUNT(CASE WHEN status IN (1, 3) THEN 1 END) as pending_orders,
                COALESCE(SUM(CASE WHEN status IN (1, 3) THEN amount ELSE 0 END), 0) as pending_amount,
                COUNT(CASE WHEN status = 4 THEN 1 END) as failed_orders,
                COALESCE(SUM(CASE WHEN status = 4 THEN amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN status = 5 THEN 1 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN status = 5 THEN amount ELSE 0 END), 0) as cancelled_amount
            FROM fourth_party_payment_order
            WHERE " . $this->buildWhereClause($searchParams);
        
        $result = DB::selectOne($sql, $this->getBindings($searchParams));
        
        return [
            'total_orders' => (int)$result->total_orders,
            'total_amount' => round((int)$result->total_amount / 100, 2), // 分转元
            'paid_orders' => (int)$result->paid_orders,
            'paid_amount' => round((int)$result->paid_amount / 100, 2), // 分转元
            'pending_orders' => (int)$result->pending_orders,
            'pending_amount' => round((int)$result->pending_amount / 100, 2), // 分转元
            'failed_orders' => (int)$result->failed_orders,
            'failed_amount' => round((int)$result->failed_amount / 100, 2), // 分转元
            'cancelled_orders' => (int)$result->cancelled_orders,
            'cancelled_amount' => round((int)$result->cancelled_amount / 100, 2), // 分转元
            'success_rate' => $this->calculateSuccessRate($result->total_orders, $result->paid_orders),
            'data_source' => 'optimized_query'
        ];
    }
    
    /**
     * 应用搜索条件
     * @param $query
     * @param array $searchParams
     */
    private function applySearchConditions($query, array $searchParams): void
    {
        if (!empty($searchParams['merchant_id'])) {
            $query->where('merchant_id', $searchParams['merchant_id']);
        }
        if (!empty($searchParams['status'])) {
            $query->where('status', $searchParams['status']);
        }
        if (!empty($searchParams['start_time'])) {
            $query->where('created_at', '>=', $searchParams['start_time']);
        }
        if (!empty($searchParams['end_time'])) {
            $query->where('created_at', '<=', $searchParams['end_time']);
        }
    }
    
    /**
     * 构建WHERE子句
     * @param array $searchParams
     * @return string
     */
    private function buildWhereClause(array $searchParams): string
    {
        $conditions = ['1=1']; // 默认条件
        
        if (!empty($searchParams['merchant_id'])) {
            $conditions[] = 'merchant_id = ?';
        }
        if (!empty($searchParams['status'])) {
            $conditions[] = 'status = ?';
        }
        if (!empty($searchParams['start_time'])) {
            $conditions[] = 'created_at >= ?';
        }
        if (!empty($searchParams['end_time'])) {
            $conditions[] = 'created_at <= ?';
        }
        
        return implode(' AND ', $conditions);
    }
    
    /**
     * 获取绑定参数
     * @param array $searchParams
     * @return array
     */
    private function getBindings(array $searchParams): array
    {
        $bindings = [];
        
        if (!empty($searchParams['merchant_id'])) {
            $bindings[] = $searchParams['merchant_id'];
        }
        if (!empty($searchParams['status'])) {
            $bindings[] = $searchParams['status'];
        }
        if (!empty($searchParams['start_time'])) {
            $bindings[] = $searchParams['start_time'];
        }
        if (!empty($searchParams['end_time'])) {
            $bindings[] = $searchParams['end_time'];
        }
        
        return $bindings;
    }
    
    /**
     * 计算成功率
     * @param int $totalOrders
     * @param int $paidOrders
     * @return float
     */
    private function calculateSuccessRate(int $totalOrders, int $paidOrders): float
    {
        if ($totalOrders <= 0) {
            return 0.0;
        }
        
        return round(($paidOrders / $totalOrders) * 100, 2);
    }
    
    /**
     * 生成缓存键
     * @param array $searchParams
     * @return string
     */
    private function generateCacheKey(array $searchParams): string
    {
        $key = self::CACHE_PREFIX . 'main';
        
        if (!empty($searchParams['merchant_id'])) {
            $key .= ':merchant_' . $searchParams['merchant_id'];
        }
        if (!empty($searchParams['start_time'])) {
            $key .= ':start_' . str_replace(['-', ':', ' '], '', $searchParams['start_time']);
        }
        if (!empty($searchParams['end_time'])) {
            $key .= ':end_' . str_replace(['-', ':', ' '], '', $searchParams['end_time']);
        }
        if (!empty($searchParams['status'])) {
            $key .= ':status_' . $searchParams['status'];
        }
        
        return $key;
    }
    
    /**
     * 获取空统计数据
     * @return array
     */
    private function getEmptyStatistics(): array
    {
        return [
            'total_orders' => 0,
            'total_amount' => 0,
            'paid_orders' => 0,
            'paid_amount' => 0,
            'pending_orders' => 0,
            'pending_amount' => 0,
            'failed_orders' => 0,
            'failed_amount' => 0,
            'cancelled_orders' => 0,
            'cancelled_amount' => 0,
            'success_rate' => 0.0,
            'data_source' => 'empty'
        ];
    }
    
    /**
     * 清除统计缓存
     * @param array $searchParams
     */
    public function clearStatisticsCache(array $searchParams = []): void
    {
        if (empty($searchParams)) {
            // 清除所有统计缓存
            $pattern = self::CACHE_PREFIX . '*';
            $keys = Cache::getRedis()->keys($pattern);
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        } else {
            // 清除特定条件的缓存
            $cacheKey = $this->generateCacheKey($searchParams);
            Cache::forget($cacheKey);
        }
        
        Log::info('订单统计缓存已清除', ['search_params' => $searchParams]);
    }
    
    /**
     * 预热统计缓存
     * @param array $merchantIds
     */
    public function warmUpCache(array $merchantIds = []): void
    {
        $queries = [
            [], // 全量统计
        ];
        
        // 添加商户特定查询
        foreach ($merchantIds as $merchantId) {
            $queries[] = ['merchant_id' => $merchantId];
        }
        
        // 添加时间范围查询
        $queries[] = [
            'start_time' => date('Y-m-d 00:00:00'),
            'end_time' => date('Y-m-d 23:59:59')
        ];
        
        foreach ($queries as $query) {
            try {
                $this->getOrderStatistics($query);
            } catch (\Exception $e) {
                Log::error('统计缓存预热失败', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('订单统计缓存预热完成', ['queries_count' => count($queries)]);
    }
}
