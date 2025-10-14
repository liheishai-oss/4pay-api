<?php

namespace app\admin\service\finance;

use app\model\SupplierBalanceLog;
use app\model\Supplier;
use app\model\Order;
use support\Db;
use support\Log;

/**
 * 供应商余额变动记录服务
 */
class SupplierBalanceLogService
{
    /**
     * 获取余额变动记录列表
     */
    public function getList(array $params): array
    {
        try {
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 20;
            $search = $params['search'] ?? [];

            $query = SupplierBalanceLog::with(['supplier', 'order'])
                ->orderBy('created_at', 'desc');

            // 应用搜索条件
            if (isset($search['supplier_id']) && $search['supplier_id']) {
                $query->where('supplier_id', $search['supplier_id']);
            }

            if (isset($search['operation_type']) && $search['operation_type']) {
                $query->where('operation_type', $search['operation_type']);
            }

            if (isset($search['operator_type']) && $search['operator_type']) {
                $query->where('operator_type', $search['operator_type']);
            }

            if (isset($search['operator_name']) && $search['operator_name']) {
                $query->where('operator_name', 'like', '%' . $search['operator_name'] . '%');
            }

            if (isset($search['start_date']) && $search['start_date']) {
                $query->where('created_at', '>=', $search['start_date']);
            }

            if (isset($search['end_date']) && $search['end_date']) {
                $query->where('created_at', '<=', $search['end_date']);
            }

            if (isset($search['order_no']) && $search['order_no']) {
                $query->where('order_no', 'like', '%' . $search['order_no'] . '%');
            }

            // 排序
            if (isset($params['sort_field']) && isset($params['sort_order'])) {
                $query->orderBy($params['sort_field'], $params['sort_order']);
            }

            // 分页
            $total = $query->count();
            $data = $query->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'supplier_id' => $item->supplier_id,
                        'supplier_name' => $item->supplier ? $item->supplier->supplier_name : '',
                        'operation_type' => $item->operation_type,
                        'operation_type_text' => $item->operation_type_text,
                        'amount' => $item->amount,
                        'balance_before' => $item->balance_before,
                        'balance_after' => $item->balance_after,
                        'operator_type' => $item->operator_type,
                        'operator_type_text' => $item->operator_type_text,
                        'operator_id' => $item->operator_id,
                        'operator_name' => $item->operator_name,
                        'order_id' => $item->order_id,
                        'order_no' => $item->order_no,
                        'remark' => $item->remark,
                        'telegram_message' => $item->telegram_message,
                        'ip_address' => $item->ip_address,
                        'user_agent' => $item->user_agent,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'supplier' => $item->supplier,
                        'order' => $item->order
                    ];
                });

            return [
                'data' => $data,
                'total' => $total,
                'current_page' => $page,
                'page_size' => $pageSize,
                'total_pages' => ceil($total / $pageSize)
            ];

        } catch (\Exception $e) {
            Log::error('获取余额变动记录列表失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取余额变动记录详情
     */
    public function getDetail(int $id): array
    {
        try {
            $log = SupplierBalanceLog::with(['supplier', 'order'])->find($id);
            
            if (!$log) {
                throw new \Exception('记录不存在');
            }

            return [
                'id' => $log->id,
                'supplier_id' => $log->supplier_id,
                'supplier_name' => $log->supplier ? $log->supplier->supplier_name : '',
                'operation_type' => $log->operation_type,
                'operation_type_text' => $log->operation_type_text,
                'amount' => $log->amount,
                'balance_before' => $log->balance_before,
                'balance_after' => $log->balance_after,
                'operator_type' => $log->operator_type,
                'operator_type_text' => $log->operator_type_text,
                'operator_id' => $log->operator_id,
                'operator_name' => $log->operator_name,
                'order_id' => $log->order_id,
                'order_no' => $log->order_no,
                'remark' => $log->remark,
                'telegram_message' => $log->telegram_message,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
                'supplier' => $log->supplier,
                'order' => $log->order
            ];

        } catch (\Exception $e) {
            Log::error('获取余额变动记录详情失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取统计信息
     */
    public function getStatistics(array $params): array
    {
        try {
            $startDate = $params['start_date'] ?? date('Y-m-d 00:00:00');
            $endDate = $params['end_date'] ?? date('Y-m-d 23:59:59');
            $supplierId = $params['supplier_id'] ?? null;

            $query = SupplierBalanceLog::whereBetween('created_at', [$startDate, $endDate]);
            
            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            // 按操作类型统计
            $operationStats = $query->selectRaw('
                operation_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('operation_type')
            ->get();

            $result = [];
            foreach ($operationStats as $stat) {
                $result[$stat->operation_type] = [
                    'count' => $stat->count,
                    'total_amount' => $stat->total_amount,
                    'avg_amount' => $stat->avg_amount,
                    'total_amount_yuan' => $stat->total_amount / 100,
                    'avg_amount_yuan' => $stat->avg_amount / 100
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取余额变动记录统计失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

