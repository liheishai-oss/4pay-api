<?php

namespace app\admin\service;

use app\model\Order;
use app\model\Merchant;
use app\model\Product;
use app\model\PaymentChannel;
use app\enums\OrderStatus;

class OrderManagementService
{
    /**
     * 获取订单列表
     */
    public function getOrderList($page, $pageSize, $searchParams = [])
    {
        $query = Order::query();

        // 记录搜索参数用于调试
        \support\Log::info('订单搜索条件', $searchParams);
        
        // 搜索条件
        if (!empty($searchParams['order_no'])) {
            $query->where('order_no', 'like', '%' . trim($searchParams['order_no']) . '%');
        }
        if (!empty($searchParams['merchant_order_no'])) {
            $query->where('merchant_order_no', 'like', '%' . trim($searchParams['merchant_order_no']) . '%');
        }
        if (!empty($searchParams['merchant_id'])) {
            $query->where('merchant_id', trim($searchParams['merchant_id']));
        }
        if (isset($searchParams['status']) && $searchParams['status'] !== '' && $searchParams['status'] !== null) {
            $query->where('status', $searchParams['status']);
        }
        if (!empty($searchParams['product_name'])) {
            $query->whereHas('product', function($q) use ($searchParams) {
                $q->where('product_name', 'like', '%' . trim($searchParams['product_name']) . '%');
            });
        }
        // 时间范围搜索
        if (!empty($searchParams['start_time']) && !empty($searchParams['end_time'])) {
            $query->whereBetween('created_at', [$searchParams['start_time'], $searchParams['end_time']]);
        } elseif (!empty($searchParams['start_time'])) {
            $query->where('created_at', '>=', $searchParams['start_time']);
        } elseif (!empty($searchParams['end_time'])) {
            $query->where('created_at', '<=', $searchParams['end_time']);
        }
        
        // 添加更多搜索条件
        if (!empty($searchParams['third_party_order_no'])) {
            $query->where('third_party_order_no', 'like', '%' . trim($searchParams['third_party_order_no']) . '%');
        }
        if (!empty($searchParams['channel_id'])) {
            $query->where('channel_id', $searchParams['channel_id']);
        }
        if (!empty($searchParams['payment_method'])) {
            $query->where('payment_method', 'like', '%' . trim($searchParams['payment_method']) . '%');
        }

        // 关联查询
        $query->with(['merchant', 'product', 'channel']);

        // 排序
        $query->orderBy('created_at', 'desc');

        // 分页
        $perPage = $pageSize;
        $result = $query->paginate($perPage, ['*'], 'page', $page);
        
        // 记录查询结果用于调试
        \support\Log::info('订单查询结果', [
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'data_count' => $result->count()
        ]);
        
        return $result;
//        return [
//            'data' => $orders,
//            'current_page' => $page,
//            'per_page' => $pageSize,
//            'total' => $total,
//            'last_page' => ceil($total / $pageSize)
//        ];
    }

    /**
     * 获取订单详情
     */
    public function getOrderDetail($id)
    {
        $order = Order::with(['merchant', 'product', 'channel', 'refunds', 'notifyLogs'])
                     ->find($id);

        if (!$order) {
            throw new \Exception('订单不存在');
        }

        return $order;
    }

    /**
     * 更新订单状态
     */
    public function updateOrderStatus($data)
    {
        $order = Order::find($data['id']);
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 验证状态转换
        $this->validateStatusTransition($order->status, $data['status']);

        $order->status = $data['status'];
        $order->notify_status = $data['notify_status'] ?? $order->notify_status;
        $order->save();

        return $order;
    }

    /**
     * 关闭订单
     */
    public function closeOrder($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if (!in_array($order->status, [OrderStatus::PENDING, OrderStatus::PAYING])) {
            throw new \Exception('只有待支付或支付中的订单才能关闭');
        }

        $order->status = OrderStatus::CLOSED;
        $order->save();

        return $order;
    }

    /**
     * 申请退款
     */
    public function refundOrder($data)
    {
        $order = Order::find($data['order_id']);
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if ($order->status !== OrderStatus::SUCCESS) {
            throw new \Exception('只有已支付的订单才能申请退款');
        }

        // 这里应该调用退款接口
        // 暂时只更新状态
        $order->status = OrderStatus::REFUNDED;
        $order->save();

        return $order;
    }

    /**
     * 导出订单数据
     */
    public function exportOrderData($searchParams = [])
    {
        $query = Order::query();

        // 应用搜索条件（与getOrderList相同）
        if (!empty($searchParams['order_no'])) {
            $query->where('order_no', 'like', '%' . $searchParams['order_no'] . '%');
        }
        if (!empty($searchParams['merchant_order_no'])) {
            $query->where('merchant_order_no', 'like', '%' . $searchParams['merchant_order_no'] . '%');
        }
        if (!empty($searchParams['merchant_id'])) {
            $query->where('merchant_id', $searchParams['merchant_id']);
        }
        if (!empty($searchParams['status'])) {
            $query->where('status', $searchParams['status']);
        }
        if (!empty($searchParams['product_name'])) {
            $query->whereHas('product', function($q) use ($searchParams) {
                $q->where('product_name', 'like', '%' . $searchParams['product_name'] . '%');
            });
        }
        if (!empty($searchParams['start_time'])) {
            $query->where('created_at', '>=', $searchParams['start_time']);
        }
        if (!empty($searchParams['end_time'])) {
            $query->where('created_at', '<=', $searchParams['end_time']);
        }

        $orders = $query->with(['merchant', 'product', 'channel'])
                       ->orderBy('created_at', 'desc')
                       ->get();

        return $orders;
    }

    /**
     * 获取订单统计
     */
    public function getOrderStatistics($searchParams = [])
    {
        $baseQuery = Order::query();

        // 应用搜索条件
        if (!empty($searchParams['start_time'])) {
            $baseQuery->where('created_at', '>=', $searchParams['start_time']);
        }
        if (!empty($searchParams['end_time'])) {
            $baseQuery->where('created_at', '<=', $searchParams['end_time']);
        }
        if (!empty($searchParams['merchant_id'])) {
            $baseQuery->where('merchant_id', $searchParams['merchant_id']);
        }
        if (!empty($searchParams['order_no'])) {
            $baseQuery->where('order_no', 'like', '%' . trim($searchParams['order_no']) . '%');
        }
        if (!empty($searchParams['merchant_order_no'])) {
            $baseQuery->where('merchant_order_no', 'like', '%' . trim($searchParams['merchant_order_no']) . '%');
        }
        if (isset($searchParams['status']) && $searchParams['status'] !== '' && $searchParams['status'] !== null) {
            $baseQuery->where('status', $searchParams['status']);
        }

        // 获取总数据
        $totalOrders = $baseQuery->count();
        $totalAmount = $baseQuery->sum('amount');

        // 获取已付数据（重新构建查询）
        $paidQuery = clone $baseQuery;
        $paidOrders = $paidQuery->where('status', OrderStatus::SUCCESS)->count();
        $paidAmount = $paidQuery->where('status', OrderStatus::SUCCESS)->sum('amount');

        // 获取未付数据（重新构建查询）- 除了已支付成功的都是未付
        $unpaidQuery = clone $baseQuery;
        $unpaidOrders = $unpaidQuery->where('status', '!=', OrderStatus::SUCCESS)->count();
        $unpaidAmount = $unpaidQuery->where('status', '!=', OrderStatus::SUCCESS)->sum('amount');

        $successRate = $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 2) : 0;

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'paid_orders' => $paidOrders,
            'paid_amount' => $paidAmount,
            'unpaid_orders' => $unpaidOrders,
            'unpaid_amount' => $unpaidAmount,
            'success_rate' => $successRate
        ];
    }

    /**
     * 补单（支持单个和批量）
     */
    public function reissueOrders($ids)
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($ids as $orderId) {
            try {
                $order = Order::find($orderId);
                if (!$order) {
                    throw new \Exception('订单不存在');
                }

                // 检查订单状态是否允许补单
                if (!in_array($order->status, [OrderStatus::FAILED, OrderStatus::CLOSED])) {
                    throw new \Exception('订单状态不允许补单');
                }

                // 这里应该调用实际的补单逻辑
                // 例如：重新发起支付请求、更新订单状态等
                $order->status = OrderStatus::PAYING; // 设置为支付中
                $order->save();

                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "订单 {$orderId}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'message' => count($ids) === 1 ? '补单成功' : "批量补单完成，成功: {$successCount}, 失败: {$failedCount}"
        ];
    }

    /**
     * 回调（支持单个和批量）
     */
    public function callbackOrders($ids)
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($ids as $orderId) {
            try {
                $order = Order::find($orderId);
                if (!$order) {
                    throw new \Exception('订单不存在');
                }

                // 检查订单状态是否允许回调
                if ($order->status !== OrderStatus::SUCCESS) {
                    throw new \Exception('只有支付成功的订单才能回调');
                }

                // 这里应该调用实际的回调逻辑
                // 例如：重新发送回调通知给商户
                $order->notify_status = 0; // 重置通知状态
                $order->save();

                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "订单 {$orderId}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'message' => count($ids) === 1 ? '回调成功' : "批量回调完成，成功: {$successCount}, 失败: {$failedCount}"
        ];
    }

    /**
     * 查单
     */
    public function queryOrder($orderNo)
    {
        // 支持通过商户订单号或第三方订单号查询
        $order = Order::where('order_no', $orderNo)
                     ->orWhere('third_party_order_no', $orderNo)
                     ->with(['merchant', 'product', 'channel.supplier'])
                     ->first();

        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 检查订单是否有支付通道
        if (!$order->channel) {
            throw new \Exception('订单未关联支付通道');
        }

        try {
            // 直接创建支付服务实例，避免服务注册管理器的问题
            $serviceClass = "app\\service\\thirdparty_payment\\services\\{$order->channel->interface_code}Service";
            
            if (!class_exists($serviceClass)) {
                throw new \Exception("不支持的支付通道: {$order->channel->interface_code}");
            }
            
            // 获取通道配置
            $channelConfig = $this->getChannelConfig($order->channel);
            
            // 创建支付服务实例
            $paymentService = new $serviceClass($channelConfig);
            
            // 调用支付服务查询订单状态
            $result = $paymentService->queryPayment($order->order_no);

            // 获取查询结果
            $queryResult = $result->getData();

            // 查单成功，返回查询结果（不修改订单状态）
            return [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'amount' => $order->amount,
                'created_at' => $order->created_at,
                'query_result' => $queryResult,
                'query_success' => $result->isSuccess(),
                'query_message' => $result->getMessage(),
                'message' => '查单成功'
            ];
        } catch (\Exception $e) {
            // 如果查单失败，返回当前订单信息
            return [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'amount' => $order->amount,
                'created_at' => $order->created_at,
                'query_result' => null,
                'query_success' => false,
                'query_message' => $e->getMessage(),
                'message' => '查单失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取订单流转日志
     */
    public function getOrderLogs($orderId)
    {
        $logs = \app\model\OrderLog::where('order_id', $orderId)
                                  ->orderBy('created_at', 'desc')
                                  ->get();

        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'order_id' => $log->order_id,
                'order_no' => $log->order_no,
                'status' => $log->status,
                'action' => $log->action,
                'description' => $log->description,
                'operator_type' => $log->operator_type,
                'operator_id' => $log->operator_id,
                'operator_name' => $log->operator_name,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'extra_data' => $log->extra_data,
                'created_at' => $log->created_at
            ];
        });
    }

    /**
     * 获取通道配置
     */
    private function getChannelConfig($channel)
    {
        // 获取供应商配置
        $supplier = $channel->supplier;
        if (!$supplier) {
            throw new \Exception('通道未关联供应商');
        }

        // 构建通道配置
        $config = [
            'channel_id' => $channel->id,
            'channel_name' => $channel->channel_name,
            'interface_code' => $channel->interface_code,
            'product_code' => $channel->product_code,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'api_url' => $supplier->api_url,
            'api_key' => $supplier->api_key,
            'api_secret' => $supplier->api_secret,
            'callback_url' => $supplier->callback_url,
            'notify_url' => $supplier->notify_url,
        ];

        // 合并通道的basic_params配置（这是支付服务真正需要的配置）
        if (!empty($channel->basic_params) && is_array($channel->basic_params)) {
            $config = array_merge($config, $channel->basic_params);
        }

        return $config;
    }

    /**
     * 根据查询结果更新订单状态
     */
    private function updateOrderFromQueryResult($order, $result)
    {
        $queryData = $result->getData();
        
        // 记录查单日志
        \app\model\OrderLog::log(
            $order->id,
            $order->order_no,
            $order->status,
            'query_order',
            '系统查单',
            'system',
            0,
            '系统',
            '',
            '',
            $queryData
        );

        // 根据查询结果更新订单状态
        if (isset($queryData['status'])) {
            $newStatus = $this->mapPaymentStatusToOrderStatus($queryData['status']);
            if ($newStatus && $newStatus != $order->status) {
                $order->status = $newStatus;
                
                // 如果支付成功，更新支付时间
                if ($newStatus == OrderStatus::SUCCESS && !$order->paid_time) {
                    $order->paid_time = now();
                }
                
                $order->save();
                
                // 记录状态变更日志
                \app\model\OrderLog::log(
                    $order->id,
                    $order->order_no,
                    $newStatus,
                    'status_update',
                    '查单更新状态：' . $this->getStatusText($newStatus),
                    'system',
                    0,
                    '系统',
                    '',
                    '',
                    ['old_status' => $order->status, 'new_status' => $newStatus]
                );
            }
        }
    }

    /**
     * 将支付服务状态映射到订单状态
     */
    private function mapPaymentStatusToOrderStatus($paymentStatus)
    {
        $statusMap = [
            'pending' => OrderStatus::PENDING,
            'paying' => OrderStatus::PAYING,
            'success' => OrderStatus::SUCCESS,
            'failed' => OrderStatus::FAILED,
            'closed' => OrderStatus::CLOSED,
            'refunded' => OrderStatus::REFUNDED,
        ];

        return $statusMap[$paymentStatus] ?? null;
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status)
    {
        $statusMap = [
            OrderStatus::PENDING => '待支付',
            OrderStatus::PAYING => '支付中',
            OrderStatus::SUCCESS => '支付成功',
            OrderStatus::FAILED => '支付失败',
            OrderStatus::REFUNDED => '已退款',
            OrderStatus::CLOSED => '已关闭'
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 验证状态转换
     */
    private function validateStatusTransition($currentStatus, $newStatus)
    {
        $allowedTransitions = [
            OrderStatus::PENDING => [OrderStatus::PAYING, OrderStatus::CLOSED],
            OrderStatus::PAYING => [OrderStatus::SUCCESS, OrderStatus::FAILED, OrderStatus::CLOSED],
            OrderStatus::SUCCESS => [OrderStatus::REFUNDED],
            OrderStatus::FAILED => [OrderStatus::PENDING],
            OrderStatus::REFUNDED => [],
            OrderStatus::CLOSED => []
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            throw new \Exception('无效的状态转换');
        }
    }
}
