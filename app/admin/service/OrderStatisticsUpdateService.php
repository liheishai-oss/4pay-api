<?php

namespace app\admin\service;

use app\model\Order;
use app\model\OrderStatistics;
use app\model\Merchant;
use Illuminate\Support\Facades\DB;
use support\Log;
use Carbon\Carbon;

/**
 * 订单统计数据更新服务
 * 负责更新统计汇总表，支持实时和定时更新
 */
class OrderStatisticsUpdateService
{
    /**
     * 更新指定日期的统计数据
     * @param string $date 日期 (Y-m-d)
     * @param int|null $merchantId 商户ID，null表示更新所有商户
     * @return bool
     */
    public function updateStatisticsForDate(string $date, ?int $merchantId = null): bool
    {
        try {
            $startTime = $date . ' 00:00:00';
            $endTime = $date . ' 23:59:59';
            
            // 获取需要更新的商户列表
            $merchantIds = $merchantId ? [$merchantId] : $this->getActiveMerchantIds();
            
            foreach ($merchantIds as $mid) {
                $this->updateMerchantStatistics($mid, $date, $startTime, $endTime);
            }
            
            Log::info('订单统计数据更新完成', [
                'date' => $date,
                'merchant_id' => $merchantId,
                'merchants_count' => count($merchantIds)
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('订单统计数据更新失败', [
                'date' => $date,
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 更新指定商户的统计数据
     * @param int $merchantId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     */
    private function updateMerchantStatistics(int $merchantId, string $date, string $startTime, string $endTime): void
    {
        // 使用单次查询获取所有统计数据
        $sql = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(CASE WHEN status = 1 THEN 1 END) as pending_orders,
                COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) as pending_amount,
                COUNT(CASE WHEN status = 2 THEN 1 END) as paid_orders,
                COALESCE(SUM(CASE WHEN status = 2 THEN amount ELSE 0 END), 0) as paid_amount,
                COUNT(CASE WHEN status = 4 THEN 1 END) as failed_orders,
                COALESCE(SUM(CASE WHEN status = 4 THEN amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN status = 5 THEN 1 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN status = 5 THEN amount ELSE 0 END), 0) as cancelled_amount
            FROM fourth_party_payment_order
            WHERE merchant_id = ? 
            AND created_at >= ? 
            AND created_at <= ?
        ";
        
        $result = DB::selectOne($sql, [$merchantId, $startTime, $endTime]);
        
        // 更新或插入统计记录
        OrderStatistics::updateOrCreate(
            [
                'merchant_id' => $merchantId,
                'stat_date' => $date
            ],
            [
                'total_orders' => (int)$result->total_orders,
                'total_amount' => (int)$result->total_amount,
                'pending_orders' => (int)$result->pending_orders,
                'pending_amount' => (int)$result->pending_amount,
                'paid_orders' => (int)$result->paid_orders,
                'paid_amount' => (int)$result->paid_amount,
                'failed_orders' => (int)$result->failed_orders,
                'failed_amount' => (int)$result->failed_amount,
                'cancelled_orders' => (int)$result->cancelled_orders,
                'cancelled_amount' => (int)$result->cancelled_amount
            ]
        );
    }
    
    /**
     * 获取活跃商户ID列表
     * @return array
     */
    private function getActiveMerchantIds(): array
    {
        return Merchant::where('status', 1)
            ->where('is_deleted', 0)
            ->pluck('id')
            ->toArray();
    }
    
    /**
     * 更新最近N天的统计数据
     * @param int $days 天数
     * @param int|null $merchantId 商户ID
     * @return bool
     */
    public function updateRecentStatistics(int $days = 7, ?int $merchantId = null): bool
    {
        $success = true;
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $result = $this->updateStatisticsForDate($date, $merchantId);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * 实时更新统计数据（订单状态变更时调用）
     * @param int $orderId
     * @param int $oldStatus
     * @param int $newStatus
     * @return bool
     */
    public function updateStatisticsOnOrderChange(int $orderId, int $oldStatus, int $newStatus): bool
    {
        try {
            $order = Order::find($orderId);
            if (!$order) {
                return false;
            }
            
            $date = Carbon::parse($order->created_at)->format('Y-m-d');
            
            // 清除相关缓存
            $this->clearRelatedCache($order->merchant_id, $date);
            
            // 更新统计数据
            return $this->updateStatisticsForDate($date, $order->merchant_id);
        } catch (\Exception $e) {
            Log::error('订单状态变更时统计更新失败', [
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 清除相关缓存
     * @param int $merchantId
     * @param string $date
     */
    private function clearRelatedCache(int $merchantId, string $date): void
    {
        $cacheKeys = [
            'order_statistics:main',
            'order_statistics:main:merchant_' . $merchantId,
            'order_statistics:main:start_' . str_replace('-', '', $date),
            'order_statistics:main:end_' . str_replace('-', '', $date),
        ];
        
        foreach ($cacheKeys as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }
    
    /**
     * 批量更新统计数据
     * @param array $merchantIds
     * @param string $startDate
     * @param string $endDate
     * @return bool
     */
    public function batchUpdateStatistics(array $merchantIds, string $startDate, string $endDate): bool
    {
        $success = true;
        $currentDate = $startDate;
        
        while ($currentDate <= $endDate) {
            foreach ($merchantIds as $merchantId) {
                $result = $this->updateStatisticsForDate($currentDate, $merchantId);
                if (!$result) {
                    $success = false;
                }
            }
            $currentDate = Carbon::parse($currentDate)->addDay()->format('Y-m-d');
        }
        
        return $success;
    }
    
    /**
     * 获取统计更新进度
     * @param string $startDate
     * @param string $endDate
     * @param int|null $merchantId
     * @return array
     */
    public function getUpdateProgress(string $startDate, string $endDate, ?int $merchantId = null): array
    {
        $merchantIds = $merchantId ? [$merchantId] : $this->getActiveMerchantIds();
        $totalDays = Carbon::parse($endDate)->diffInDays(Carbon::parse($startDate)) + 1;
        $totalTasks = count($merchantIds) * $totalDays;
        
        $completedTasks = 0;
        $currentDate = $startDate;
        
        while ($currentDate <= $endDate) {
            foreach ($merchantIds as $merchantId) {
                $exists = OrderStatistics::where('merchant_id', $merchantId)
                    ->where('stat_date', $currentDate)
                    ->exists();
                if ($exists) {
                    $completedTasks++;
                }
            }
            $currentDate = Carbon::parse($currentDate)->addDay()->format('Y-m-d');
        }
        
        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'progress_percentage' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
            'remaining_tasks' => $totalTasks - $completedTasks
        ];
    }
    
    /**
     * 清理过期统计数据
     * @param int $daysToKeep 保留天数
     * @return int 删除的记录数
     */
    public function cleanupExpiredStatistics(int $daysToKeep = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep)->format('Y-m-d');
        
        $deletedCount = OrderStatistics::where('stat_date', '<', $cutoffDate)->delete();
        
        Log::info('过期统计数据清理完成', [
            'cutoff_date' => $cutoffDate,
            'deleted_count' => $deletedCount
        ]);
        
        return $deletedCount;
    }
}
