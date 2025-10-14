<?php

namespace app\command;

use app\model\Order;
use app\model\SystemConfig;
use support\Log;
use support\Db;

/**
 * 订单超时检查命令
 * 用于检测订单是否超时未处理，如果超时则关闭订单
 */
class OrderTimeoutCheckCommand
{
    /**
     * 开始执行任务
     */
    public function start(): void
    {
        Log::info('订单超时检查任务开始执行');
        
        try {
            // 获取订单有效期配置（分钟）
            $orderValidityMinutes = $this->getOrderValidityMinutes();
            Log::info('订单有效期配置', ['minutes' => $orderValidityMinutes]);
            
            // 计算超时时间点
            $timeoutTime = date('Y-m-d H:i:s', time() - ($orderValidityMinutes * 60));
            Log::info('计算超时时间点', ['timeout_time' => $timeoutTime]);
            
            // 查找超时的待支付和支付中订单
            $timeoutOrders = $this->getTimeoutOrders($timeoutTime);
            Log::info('找到超时订单数量', ['count' => count($timeoutOrders)]);
            
            if (empty($timeoutOrders)) {
                Log::info('没有找到超时订单');
                return;
            }
            
            // 批量关闭超时订单
            $this->closeTimeoutOrders($timeoutOrders);
            
            Log::info('订单超时检查任务执行完成');
            
        } catch (\Exception $e) {
            Log::error('订单超时检查任务执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 获取订单有效期配置
     * @return int 有效期（分钟）
     */
    private function getOrderValidityMinutes(): int
    {
        try {
            $config = SystemConfig::where('config_key', 'payment.order_validity_minutes')->first();
            if ($config) {
                return (int)$config->config_value;
            }
        } catch (\Exception $e) {
            Log::warning('获取订单有效期配置失败，使用默认值', [
                'error' => $e->getMessage()
            ]);
        }
        
        // 默认30分钟
        return 30;
    }
    
    /**
     * 获取超时订单
     * @param string $timeoutTime 超时时间点
     * @return array 超时订单列表
     */
    private function getTimeoutOrders(string $timeoutTime): array
    {
        return Order::whereIn('status', [1, 2]) // 1-待支付，2-支付中
            ->where('created_at', '<', $timeoutTime)
            ->whereNull('paid_time') // 未支付
            ->select([
                'id', 'order_no', 'merchant_order_no', 'merchant_id', 
                'amount', 'status', 'created_at', 'expire_time'
            ])
            ->get()
            ->toArray();
    }
    
    /**
     * 批量关闭超时订单
     * @param array $timeoutOrders 超时订单列表
     */
    private function closeTimeoutOrders(array $timeoutOrders): void
    {
        if (empty($timeoutOrders)) {
            return;
        }
        
        $orderIds = array_column($timeoutOrders, 'id');
        $orderNos = array_column($timeoutOrders, 'order_no');
        
        try {
            // 开启事务
            Db::beginTransaction();
            
            // 批量更新订单状态为已关闭（状态6）
            $updatedCount = Order::whereIn('id', $orderIds)
                ->whereIn('status', [1, 2]) // 只更新待支付和支付中的订单
                ->update([
                    'status' => 6, // 6-已关闭
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            // 记录操作日志
            $this->logOrderCloseOperation($timeoutOrders, $updatedCount);
            
            // 提交事务
            Db::commit();
            
            Log::info('批量关闭超时订单成功', [
                'total_orders' => count($timeoutOrders),
                'updated_count' => $updatedCount,
                'order_nos' => $orderNos
            ]);
            
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollBack();
            
            Log::error('批量关闭超时订单失败', [
                'error' => $e->getMessage(),
                'order_ids' => $orderIds
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 记录订单关闭操作日志
     * @param array $timeoutOrders 超时订单列表
     * @param int $updatedCount 实际更新的订单数量
     */
    private function logOrderCloseOperation(array $timeoutOrders, int $updatedCount): void
    {
        $logData = [
            'operation_type' => 'order_timeout_close',
            'total_orders' => count($timeoutOrders),
            'updated_count' => $updatedCount,
            'orders' => array_map(function($order) {
                return [
                    'id' => $order['id'],
                    'order_no' => $order['order_no'],
                    'merchant_order_no' => $order['merchant_order_no'],
                    'merchant_id' => $order['merchant_id'],
                    'amount' => $order['amount'],
                    'created_at' => $order['created_at'],
                    'expire_time' => $order['expire_time']
                ];
            }, $timeoutOrders),
            'executed_at' => date('Y-m-d H:i:s')
        ];
        
        Log::info('订单超时关闭操作日志', $logData);
    }
    
    /**
     * 获取任务统计信息
     * @return array
     */
    public function getStats(): array
    {
        try {
            $orderValidityMinutes = $this->getOrderValidityMinutes();
            $timeoutTime = date('Y-m-d H:i:s', time() - ($orderValidityMinutes * 60));
            
            // 统计待支付订单
            $pendingCount = Order::where('status', 1)
                ->where('created_at', '<', $timeoutTime)
                ->whereNull('paid_time')
                ->count();
            
            // 统计支付中订单
            $processingCount = Order::where('status', 2)
                ->where('created_at', '<', $timeoutTime)
                ->whereNull('paid_time')
                ->count();
            
            // 统计今日已关闭的超时订单
            $todayClosedCount = Order::where('status', 6)
                ->whereDate('updated_at', date('Y-m-d'))
                ->count();
            
            return [
                'order_validity_minutes' => $orderValidityMinutes,
                'timeout_time' => $timeoutTime,
                'pending_timeout_count' => $pendingCount,
                'processing_timeout_count' => $processingCount,
                'total_timeout_count' => $pendingCount + $processingCount,
                'today_closed_count' => $todayClosedCount,
                'last_check_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            Log::error('获取订单超时检查统计信息失败', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'last_check_time' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * 停止任务
     */
    public function stop(): void
    {
        Log::info('订单超时检查任务停止');
    }
}



