<?php

namespace app\service\supplier;

use app\model\Supplier;
use app\model\SupplierBalanceLog;
use app\model\Order;
use support\Db;
use support\Log;
use support\Redis;

/**
 * 供应商余额管理服务
 */
class SupplierBalanceService
{
    /**
     * 记录余额变动（高并发安全版本）
     * 
     * @param int $supplierId 供应商ID
     * @param int $operationType 操作类型
     * @param int $amount 变动金额（分）
     * @param array $options 其他选项
     * @return SupplierBalanceLog
     * @throws \Exception
     */
    public function logBalanceChange(int $supplierId, int $operationType, int $amount, array $options = []): SupplierBalanceLog
    {
        $lockKey = "supplier_balance_lock:{$supplierId}";
        $lockValue = uniqid();
        $lockTimeout = 30; // 30秒锁超时
        
        try {
            // 获取分布式锁
            if (!$this->acquireLock($lockKey, $lockValue, $lockTimeout)) {
                throw new \Exception("获取余额操作锁失败，请稍后重试");
            }

            // 使用数据库行锁确保并发安全
            $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->first();
            if (!$supplier) {
                throw new \Exception("供应商不存在");
            }

            // 获取当前余额
            $balanceBefore = $supplier->prepayment_remaining ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            // 检查余额是否足够（扣除操作）
            if ($amount < 0 && $balanceBefore < abs($amount)) {
                throw new \Exception("余额不足，当前余额：" . number_format($balanceBefore / 100, 2) . "元");
            }

            // 使用数据库事务确保原子性
            Db::beginTransaction();

            try {
                // 更新供应商余额
                $supplier->prepayment_remaining = $balanceAfter;
                if ($amount > 0) {
                    $supplier->prepayment_total = ($supplier->prepayment_total ?? 0) + $amount;
                }
                $supplier->save();

                // 创建余额变动记录
                $log = new SupplierBalanceLog();
                $log->supplier_id = $supplierId;
                $log->operation_type = $operationType;
                $log->amount = $amount;
                $log->balance_before = $balanceBefore;
                $log->balance_after = $balanceAfter;
                $log->operator_type = $options['operator_type'] ?? SupplierBalanceLog::OPERATOR_TYPE_SYSTEM;
                $log->operator_id = $options['operator_id'] ?? null;
                $log->operator_name = $options['operator_name'] ?? null;
                $log->order_id = $options['order_id'] ?? null;
                $log->order_no = $options['order_no'] ?? null;
                $log->remark = $options['remark'] ?? null;
                $log->telegram_message = $options['telegram_message'] ?? null;
                $log->ip_address = $options['ip_address'] ?? null;
                $log->user_agent = $options['user_agent'] ?? null;
                $log->save();

                Db::commit();

                Log::info('供应商余额变动记录成功', [
                    'supplier_id' => $supplierId,
                    'operation_type' => $operationType,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'log_id' => $log->id,
                    'lock_value' => $lockValue
                ]);

                return $log;

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('供应商余额变动记录失败', [
                'supplier_id' => $supplierId,
                'operation_type' => $operationType,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'lock_value' => $lockValue
            ]);
            throw $e;
        } finally {
            // 释放分布式锁
            $this->releaseLock($lockKey, $lockValue);
        }
    }

    /**
     * 获取分布式锁
     * 
     * @param string $key 锁的键
     * @param string $value 锁的值
     * @param int $timeout 超时时间（秒）
     * @return bool
     */
    private function acquireLock(string $key, string $value, int $timeout): bool
    {
        $maxRetries = 10; // 最大重试次数
        $retryDelay = 100000; // 重试延迟（微秒）
        
        for ($i = 0; $i < $maxRetries; $i++) {
            // 使用SET NX EX命令原子性设置锁
            $result = Redis::set($key, $value, 'EX', $timeout, 'NX');
            
            if ($result) {
                Log::debug('成功获取分布式锁', [
                    'key' => $key,
                    'value' => $value,
                    'timeout' => $timeout,
                    'retry_count' => $i
                ]);
                return true;
            }
            
            // 如果获取锁失败，等待一段时间后重试
            if ($i < $maxRetries - 1) {
                usleep($retryDelay);
            }
        }
        
        Log::warning('获取分布式锁失败', [
            'key' => $key,
            'value' => $value,
            'timeout' => $timeout,
            'max_retries' => $maxRetries
        ]);
        
        return false;
    }

    /**
     * 释放分布式锁
     * 
     * @param string $key 锁的键
     * @param string $value 锁的值
     * @return bool
     */
    private function releaseLock(string $key, string $value): bool
    {
        // 使用Lua脚本确保只有锁的持有者才能释放锁
        $luaScript = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";
        
        $result = Redis::eval($luaScript, 1, $key, $value);
        
        Log::debug('释放分布式锁', [
            'key' => $key,
            'value' => $value,
            'result' => $result
        ]);
        
        return $result > 0;
    }

    /**
     * 预付操作（增加余额）
     * 
     * @param int $supplierId 供应商ID
     * @param int $amount 金额（分）
     * @param array $operatorInfo 操作人信息
     * @param string $remark 备注
     * @param string $telegramMessage Telegram消息
     * @return SupplierBalanceLog
     */
    public function addPrepayment(int $supplierId, int $amount, array $operatorInfo, string $remark = '', string $telegramMessage = ''): SupplierBalanceLog
    {
        return $this->logBalanceChange($supplierId, SupplierBalanceLog::OPERATION_TYPE_PREPAYMENT, $amount, [
            'operator_type' => SupplierBalanceLog::OPERATOR_TYPE_ADMIN,
            'operator_id' => $operatorInfo['operator_id'] ?? null,
            'operator_name' => $operatorInfo['operator_name'] ?? null,
            'remark' => $remark,
            'telegram_message' => $telegramMessage,
            'ip_address' => $operatorInfo['ip_address'] ?? null,
            'user_agent' => $operatorInfo['user_agent'] ?? null
        ]);
    }

    /**
     * 下发操作（扣除余额）
     * 
     * @param int $supplierId 供应商ID
     * @param int $amount 金额（分）
     * @param array $operatorInfo 操作人信息
     * @param string $remark 备注
     * @param string $telegramMessage Telegram消息
     * @return SupplierBalanceLog
     */
    public function withdrawPrepayment(int $supplierId, int $amount, array $operatorInfo, string $remark = '', string $telegramMessage = ''): SupplierBalanceLog
    {
        return $this->logBalanceChange($supplierId, SupplierBalanceLog::OPERATION_TYPE_WITHDRAWAL, -$amount, [
            'operator_type' => SupplierBalanceLog::OPERATOR_TYPE_ADMIN,
            'operator_id' => $operatorInfo['operator_id'] ?? null,
            'operator_name' => $operatorInfo['operator_name'] ?? null,
            'remark' => $remark,
            'telegram_message' => $telegramMessage,
            'ip_address' => $operatorInfo['ip_address'] ?? null,
            'user_agent' => $operatorInfo['user_agent'] ?? null
        ]);
    }

    /**
     * 订单扣款
     * 
     * @param int $supplierId 供应商ID
     * @param int $amount 金额（分）
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号
     * @return SupplierBalanceLog
     */
    public function deductForOrder(int $supplierId, int $amount, int $orderId, string $orderNo): SupplierBalanceLog
    {
        return $this->logBalanceChange($supplierId, SupplierBalanceLog::OPERATION_TYPE_ORDER_DEDUCT, -$amount, [
            'operator_type' => SupplierBalanceLog::OPERATOR_TYPE_ORDER,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'remark' => "订单扣款：{$orderNo}"
        ]);
    }

    /**
     * 订单退款
     * 
     * @param int $supplierId 供应商ID
     * @param int $amount 金额（分）
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号
     * @return SupplierBalanceLog
     */
    public function refundForOrder(int $supplierId, int $amount, int $orderId, string $orderNo): SupplierBalanceLog
    {
        return $this->logBalanceChange($supplierId, SupplierBalanceLog::OPERATION_TYPE_REFUND, $amount, [
            'operator_type' => SupplierBalanceLog::OPERATOR_TYPE_ORDER,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'remark' => "订单退款：{$orderNo}"
        ]);
    }

    /**
     * 系统调整
     * 
     * @param int $supplierId 供应商ID
     * @param int $amount 金额（分）
     * @param string $remark 备注
     * @return SupplierBalanceLog
     */
    public function systemAdjust(int $supplierId, int $amount, string $remark = ''): SupplierBalanceLog
    {
        return $this->logBalanceChange($supplierId, SupplierBalanceLog::OPERATION_TYPE_SYSTEM_ADJUST, $amount, [
            'operator_type' => SupplierBalanceLog::OPERATOR_TYPE_SYSTEM,
            'remark' => $remark
        ]);
    }

    /**
     * 获取余额变动记录
     * 
     * @param int $supplierId 供应商ID
     * @param array $filters 过滤条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getBalanceLogs(int $supplierId, array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        $query = SupplierBalanceLog::where('supplier_id', $supplierId)
            ->with(['supplier', 'order'])
            ->orderBy('created_at', 'desc');

        // 应用过滤条件
        if (isset($filters['operation_type'])) {
            $query->where('operation_type', $filters['operation_type']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['operator_type'])) {
            $query->where('operator_type', $filters['operator_type']);
        }

        // 分页
        $total = $query->count();
        $logs = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return [
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => ceil($total / $pageSize)
        ];
    }

    /**
     * 获取余额统计
     * 
     * @param int $supplierId 供应商ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getBalanceStatistics(int $supplierId, string $startDate, string $endDate): array
    {
        $stats = SupplierBalanceLog::where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                operation_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('operation_type')
            ->get();

        $result = [];
        foreach ($stats as $stat) {
            $result[$stat->operation_type] = [
                'count' => $stat->count,
                'total_amount' => $stat->total_amount,
                'avg_amount' => $stat->avg_amount,
                'total_amount_yuan' => $stat->total_amount / 100,
                'avg_amount_yuan' => $stat->avg_amount / 100
            ];
        }

        return $result;
    }
}
