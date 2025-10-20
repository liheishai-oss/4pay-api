<?php

namespace app\process;

use app\model\Order;
use app\model\SystemConfig;
use app\model\PaymentChannel;
use support\Log;
use support\Db;
use Workerman\Crontab\Crontab;

/**
 * 订单超时检查进程
 * 每10秒检查一次订单是否超时，关闭前检查供应商订单状态
 */
class OrderTimeoutCheckProcess
{
    /**
     * 进程启动
     */
    public function onWorkerStart(): void
    {
//        print_r("超时检测启动");
        Log::info('订单超时检查进程启动', [
            'process_id' => getmypid(),
            'start_time' => date('Y-m-d H:i:s')
        ]);
        
        // 每10秒执行一次检查
        new Crontab('*/10 * * * * *', function(){
            $this->checkTimeoutOrders();
        });
    }
    
    /**
     * 检查超时订单
     */
    private function checkTimeoutOrders(): void
    {

        // 超时检查进程使用统一的trace_id
        $traceId = 'timeout_check_' . date('YmdHis') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
        
        try {
            // 获取订单有效期配置（分钟）
            $orderValidityMinutes = $this->getOrderValidityMinutes();
            
            // 计算超时时间点
            $timeoutTime = date('Y-m-d H:i:s', time() - ($orderValidityMinutes * 60));
            
            Log::info('开始执行订单超时检查', [
                'trace_id' => $traceId,
                'order_validity_minutes' => $orderValidityMinutes,
                'timeout_time' => $timeoutTime,
                'current_time' => date('Y-m-d H:i:s')
            ]);

            // 1. 查找超时的待支付订单
            $timeoutOrders = $this->getTimeoutOrders($timeoutTime);
            
            // 2. 查找所有支付中的订单（不管是否超时）
            $processingOrders = $this->getProcessingOrders();
            
            // 合并两个数组，去重
            $allOrders = array_merge($timeoutOrders, $processingOrders);
            $allOrders = array_unique($allOrders, SORT_REGULAR);
            
            if (empty($allOrders)) {
                Log::info('未找到需要检查的订单', [
                    'trace_id' => $traceId,
                    'timeout_orders_count' => count($timeoutOrders),
                    'processing_orders_count' => count($processingOrders)
                ]);
                return;
            }
            
            Log::info('找到需要检查的订单', [
                'trace_id' => $traceId,
                'total_count' => count($allOrders),
                'timeout_orders_count' => count($timeoutOrders),
                'processing_orders_count' => count($processingOrders),
                'order_nos' => array_column($allOrders, 'order_no')
            ]);

            // 逐个检查并处理订单
            $this->processTimeoutOrders($allOrders, $traceId);
            
        } catch (\Exception $e) {
            Log::error('订单超时检查异常', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 处理超时订单（批量处理）
     * @param array $timeoutOrders
     * @param string $traceId
     */
    private function processTimeoutOrders(array $timeoutOrders, string $traceId): void
    {
        $totalCount = count($timeoutOrders);
        $successCount = 0;
        $errorCount = 0;
        $closeCount = 0;
        $skipCount = 0;
        
        Log::info('开始批量处理超时订单', [
            'trace_id' => $traceId,
            'total_count' => $totalCount,
            'batch_size' => $totalCount
        ]);
        
        $startTime = microtime(true);
        
        foreach ($timeoutOrders as $order) {
            try {
                $result = $this->processSingleTimeoutOrder($order, $traceId);
                
                if ($result['processed']) {
                    $successCount++;
                    if ($result['closed']) {
                        $closeCount++;
                    } else {
                        $skipCount++;
                    }
                } else {
                    $skipCount++;
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('处理单个订单失败', [
                    'trace_id' => $traceId,
                    'order_id' => $order['id'],
                    'order_no' => $order['order_no'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $endTime = microtime(true);
        $processingTime = round(($endTime - $startTime) * 1000, 2); // 毫秒
        
        Log::info('批量处理超时订单完成', [
            'trace_id' => $traceId,
            'total_count' => $totalCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'close_count' => $closeCount,
            'skip_count' => $skipCount,
            'processing_time_ms' => $processingTime,
            'avg_time_per_order_ms' => $totalCount > 0 ? round($processingTime / $totalCount, 2) : 0
        ]);
    }
    
    /**
     * 处理单个超时订单
     * @param array $order
     * @param string $traceId
     * @return array 处理结果
     */
    private function processSingleTimeoutOrder(array $order, string $traceId): array
    {
        $orderId = $order['id'];
        $orderNo = $order['order_no'];
        $channelId = $order['channel_id'];
        $currentStatus = $order['status'];
        $createdAt = $order['created_at'];
        
        Log::info('开始处理订单', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'channel_id' => $channelId,
            'current_status' => $currentStatus,
            'created_at' => $createdAt,
            'status_text' => $this->getStatusText($currentStatus)
        ]);
        
        // 检查供应商订单状态
        $supplierStatus = $this->checkSupplierOrderStatus($order, $traceId);
        
        Log::info('供应商订单状态检查完成', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'should_close' => $supplierStatus['should_close'],
            'reason' => $supplierStatus['reason']
        ]);
        
        if ($supplierStatus['should_close']) {
            // 如果订单已经是已关闭状态，且供货商显示支付成功，则更新为支付成功
            if ($currentStatus == 6 && $supplierStatus['reason'] === '供应商订单已支付成功') {
                Log::info('订单已被错误关闭，但供货商显示支付成功，更新为支付成功', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'current_status' => $currentStatus,
                    'supplier_reason' => $supplierStatus['reason']
                ]);
                // 这里不需要调用updateOrderToSuccess，因为checkSupplierOrderStatus已经处理了
                return [
                    'processed' => true,
                    'closed' => false,
                    'action' => 'updated_to_success'
                ];
            } else {
                // 关闭订单
                $this->closeTimeoutOrder($orderId, $orderNo, $supplierStatus['reason'], $traceId);
                return [
                    'processed' => true,
                    'closed' => true,
                    'action' => 'closed'
                ];
            }
        } else {
            Log::info('供应商订单状态正常，不关闭订单', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'supplier_status' => $supplierStatus
            ]);
            return [
                'processed' => true,
                'closed' => false,
                'action' => 'skipped'
            ];
        }
    }
    
    /**
     * 检查供应商订单状态
     * @param array $order
     * @param string $traceId
     * @return array
     */
    private function checkSupplierOrderStatus(array $order, string $traceId): array
    {
        try {
            $orderNo = $order['order_no'];
            $channelId = $order['channel_id'];
            
            // 获取支付通道信息
            $channel = PaymentChannel::find($channelId);
            if (!$channel) {
                Log::warning('支付通道不存在', [
                    'trace_id' => $traceId,
                    'order_no' => $orderNo,
                    'channel_id' => $channelId
                ]);
                return [
                    'should_close' => true,
                    'reason' => '支付通道不存在'
                ];
            }
            
            // 创建支付服务实例
            $paymentService = $this->createPaymentService($channel);
            
            if (!$paymentService) {
                Log::warning('无法创建支付服务实例', [
                    'trace_id' => $traceId,
                    'order_no' => $orderNo,
                    'channel_id' => $channelId,
                    'interface_code' => $channel->interface_code
                ]);
                return [
                    'should_close' => true,
                    'reason' => '无法创建支付服务实例'
                ];
            }
            
            // 查询供应商订单状态
            $result = $paymentService->queryPayment($orderNo);
            
            // 详细记录查询结果
            Log::info('供应商订单查询结果', [
                'trace_id' => $traceId,
                'order_no' => $orderNo,
                'interface_code' => $channel->interface_code,
                'result_status' => $result->getStatus(),
                'result_message' => $result->getMessage(),
                'is_success' => $result->isSuccess(),
                'raw_response' => $result->getRawResponse(),
                'data' => $result->getData()
            ]);
            
            // 改进判断逻辑：不仅检查isSuccess()，还要检查具体的状态
            $isSupplierPaid = $this->isSupplierOrderPaid($result, $channel->interface_code);
            
            if ($isSupplierPaid) {
                // 供应商订单已支付成功，更新本地订单状态
                Log::info('供应商订单已支付成功，更新本地订单状态', [
                    'trace_id' => $traceId,
                    'order_no' => $orderNo,
                    'interface_code' => $channel->interface_code,
                    'supplier_status' => $result->getStatus(),
                    'is_success' => $result->isSuccess(),
                    'current_order_status' => $order['status']
                ]);
                
                // 如果订单状态是6（已关闭），说明被错误关闭了，需要恢复为支付成功
                if ($order['status'] == 6) {
                    Log::info('订单被错误关闭，但供货商显示支付成功，恢复为支付成功状态', [
                        'trace_id' => $traceId,
                        'order_no' => $orderNo,
                        'order_id' => $order['id'],
                        'old_status' => 6,
                        'new_status' => 3
                    ]);
                }
                
                $this->updateOrderToSuccess($order['id'], $orderNo, $result, $traceId);
                
                return [
                    'should_close' => false,
                    'reason' => '供应商订单已支付成功'
                ];
            } else {
                // 供应商订单未支付，检查关闭条件
                $forceTimeoutMinutes = $this->getForceTimeoutMinutes(); // 获取强制超时配置
                $forceTimeoutTime = date('Y-m-d H:i:s', time() - ($forceTimeoutMinutes * 60));
                $isForceTimeout = $order['created_at'] < $forceTimeoutTime;
                $isSupplierFailed = $result->getStatus() === 'failed';
                
                Log::info('供应商订单未支付，判断是否关闭', [
                    'trace_id' => $traceId,
                    'order_no' => $orderNo,
                    'interface_code' => $channel->interface_code,
                    'supplier_status' => $result->getStatus(),
                    'message' => $result->getMessage(),
                    'is_success' => $result->isSuccess(),
                    'order_created_at' => $order['created_at'],
                    'force_timeout_minutes' => $forceTimeoutMinutes,
                    'force_timeout_time' => $forceTimeoutTime,
                    'is_force_timeout' => $isForceTimeout,
                    'is_supplier_failed' => $isSupplierFailed,
                    'current_order_status' => $order['status']
                ]);
                
                // 强制超时 或 供应商明确失败状态时关闭
                if ($isForceTimeout || $isSupplierFailed) {
                    $reason = $isForceTimeout ? 
                        "订单超过{$forceTimeoutMinutes}分钟强制超时，自动关闭：" . $result->getMessage() :
                        '供应商订单明确失败，自动关闭：' . $result->getMessage();
                    
                    return [
                        'should_close' => true,
                        'reason' => $reason
                    ];
                } else {
                    // 订单未超时且供应商状态正常，继续等待
                    return [
                        'should_close' => false,
                        'reason' => '订单未超时且供应商状态正常，继续等待'
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Log::error('检查供应商订单状态失败', [
                'trace_id' => $traceId,
                'order_no' => $order['order_no'],
                'error' => $e->getMessage(),
                'current_order_status' => $order['status']
            ]);
            
            // 查询失败时，不关闭订单，等待下次检查
            // 避免因网络问题误关闭已支付成功的订单
            return [
                'should_close' => false,
                'reason' => '查询供应商订单状态失败，等待下次检查：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 更新订单为支付成功状态
     * @param int $orderId
     * @param string $orderNo
     * @param \app\service\thirdparty_payment\PaymentResult $result
     * @param string $traceId
     */
    private function updateOrderToSuccess(int $orderId, string $orderNo, \app\service\thirdparty_payment\PaymentResult $result, string $traceId): void
    {
        try {
            // 先获取当前订单状态
            $currentOrder = Order::find($orderId);
            if (!$currentOrder) {
                Log::error('订单不存在，无法更新状态', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo
                ]);
                return;
            }
            
            $oldStatus = $currentOrder->status;
            $oldPaidTime = $currentOrder->paid_time;
            $oldThirdPartyOrderNo = $currentOrder->third_party_order_no;
            
            Log::info('准备更新订单状态为支付成功', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'old_status' => $oldStatus,
                'old_status_text' => $this->getStatusText($oldStatus),
                'old_paid_time' => $oldPaidTime,
                'old_third_party_order_no' => $oldThirdPartyOrderNo
            ]);
            
            Db::beginTransaction();
            
            // 获取供应商的支付时间和交易信息
            $supplierPaidTime = $this->getSupplierPaidTime($result);
            $transactionId = $result->getTransactionId();
            $supplierAmount = $result->getAmount();
            
            // 更新订单状态和相关信息
            $updateData = [
                'status' => 3, // 3-支付成功
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // 设置支付时间（优先使用供应商的支付时间）
            if ($supplierPaidTime) {
                $updateData['paid_time'] = $supplierPaidTime;
            } else {
                $updateData['paid_time'] = date('Y-m-d H:i:s');
            }
            
            // 设置第三方订单号
            if ($transactionId) {
                $updateData['third_party_order_no'] = $transactionId;
            }
            
            Log::info('订单状态更新数据', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'update_data' => $updateData,
                'supplier_paid_time' => $supplierPaidTime,
                'transaction_id' => $transactionId,
                'supplier_amount' => $supplierAmount
            ]);
            
            // 更新订单
            $updated = Order::where('id', $orderId)->update($updateData);
            
            if ($updated > 0) {
                Db::commit();
                
                Log::info('订单状态更新为支付成功 - 成功', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'old_status' => $oldStatus,
                    'old_status_text' => $this->getStatusText($oldStatus),
                    'new_status' => 3,
                    'new_status_text' => '支付成功',
                    'old_paid_time' => $oldPaidTime,
                    'new_paid_time' => $updateData['paid_time'],
                    'old_third_party_order_no' => $oldThirdPartyOrderNo,
                    'new_third_party_order_no' => $updateData['third_party_order_no'] ?? '',
                    'transaction_id' => $transactionId,
                    'supplier_amount' => $supplierAmount,
                    'supplier_paid_time' => $supplierPaidTime
                ]);
                
                // 记录订单状态更新到链路追踪
                $this->logOrderStatusUpdateToTrace($orderId, $oldStatus, $result, $traceId);
                
                // 触发商户回调通知
                $this->triggerMerchantCallback($orderId, $orderNo, $traceId);
            } else {
                Db::rollBack();
                
                Log::warning('订单状态更新失败 - 没有记录被更新', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'update_data' => $updateData
                ]);
            }
            
        } catch (\Exception $e) {
            Db::rollBack();
            
            Log::error('更新订单状态失败 - 异常', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 关闭超时订单
     * @param int $orderId
     * @param string $orderNo
     * @param string $reason
     * @param string $traceId
     */
    private function closeTimeoutOrder(int $orderId, string $orderNo, string $reason, string $traceId): void
    {
        try {
            // 先获取当前订单状态
            $currentOrder = Order::find($orderId);
            if (!$currentOrder) {
                Log::error('订单不存在，无法关闭', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo
                ]);
                return;
            }
            
            $oldStatus = $currentOrder->status;
            
            Log::info('准备关闭超时订单', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'old_status' => $oldStatus,
                'old_status_text' => $this->getStatusText($oldStatus),
                'reason' => $reason
            ]);
            
            Db::beginTransaction();
            
            // 更新订单状态为已关闭
            $updated = Order::where('id', $orderId)
                ->whereIn('status', [1, 2]) // 只更新待支付和支付中的订单
                ->update([
                    'status' => 6, // 6-已关闭
                    'closed_time' => date('Y-m-d H:i:s'), // 记录关闭时间
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            if ($updated > 0) {
                Db::commit();
                
                Log::info('超时订单已关闭 - 成功', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'old_status' => $oldStatus,
                    'old_status_text' => $this->getStatusText($oldStatus),
                    'new_status' => 6,
                    'new_status_text' => '已关闭',
                    'reason' => $reason
                ]);
                
                // 注意：订单关闭时不触发商户回调，只有支付成功时才通知
                Log::info('订单已关闭，不触发商户回调', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'reason' => '订单关闭不通知商户'
                ]);
            } else {
                Db::rollBack();
                
                Log::warning('订单状态已变更，无需关闭', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'old_status' => $oldStatus,
                    'old_status_text' => $this->getStatusText($oldStatus),
                    'reason' => '订单状态已变更，不在待支付或支付中状态'
                ]);
            }
            
        } catch (\Exception $e) {
            Db::rollBack();
            
            Log::error('关闭超时订单失败 - 异常', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 获取订单有效期配置
     * @return int 有效期（分钟）
     */
    private function getOrderValidityMinutes(): int
    {
        try {
            // 检查数据库连接
            if (!\support\Db::connection()->getPdo()) {
                Log::warning('数据库连接不可用，使用默认订单有效期', [
                    'default_minutes' => 30
                ]);
                return 30;
            }
            
            $config = SystemConfig::where('config_key', 'payment.order_validity_minutes')->first();
            if ($config && $config->config_value) {
                return (int)$config->config_value;
            }
        } catch (\Exception $e) {
            Log::warning('获取订单有效期配置失败，使用默认值', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // 默认30分钟
        return 30;
    }
    
    /**
     * 获取强制超时配置（分钟）
     * @return int
     */
    private function getForceTimeoutMinutes(): int
    {
        try {
            // 检查数据库连接
            if (!\support\Db::connection()->getPdo()) {
                Log::warning('数据库连接不可用，使用默认强制超时时间', [
                    'default_minutes' => 30
                ]);
                return 30;
            }
            
            $config = SystemConfig::where('config_key', 'payment.force_timeout_minutes')->first();
            if ($config && $config->config_value) {
                return (int)$config->config_value;
            }
        } catch (\Exception $e) {
            Log::warning('获取强制超时配置失败，使用默认值', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // 默认30分钟
        return 30;
    }
    
    /**
     * 获取超时订单（只查询未支付状态）
     * @param string $timeoutTime 超时时间点
     * @return array 超时订单列表
     */
    private function getTimeoutOrders(string $timeoutTime): array
    {
        return Order::where('status', 1) // 1-待支付
            ->where('created_at', '<', $timeoutTime)
            ->whereNull('paid_time') // 未支付
            ->select([
                'id', 'order_no', 'merchant_order_no', 'merchant_id', 
                'amount', 'status', 'created_at', 'expire_time', 'channel_id'
            ])
            ->get()
            ->toArray();
    }
    
    /**
     * 获取所有支付中的订单（不管是否超时）
     * @return array
     */
    private function getProcessingOrders(): array
    {
        return Order::where('status', 2) // 2-支付中
            ->whereNull('paid_time') // 未支付
            ->select([
                'id', 'order_no', 'merchant_order_no', 'merchant_id', 
                'amount', 'status', 'created_at', 'expire_time', 'channel_id'
            ])
            ->get()
            ->toArray();
    }
    
    
    /**
     * 判断供应商订单是否已支付
     * @param \app\service\thirdparty_payment\PaymentResult $result
     * @param string $interfaceCode
     * @return bool
     */
    private function isSupplierOrderPaid(\app\service\thirdparty_payment\PaymentResult $result, string $interfaceCode): bool
    {
        try {
            // 首先检查PaymentResult的isSuccess()方法
            if ($result->isSuccess()) {
                return true;
            }
            
            // 使用策略模式的状态检查器
            $statusChecker = \app\service\thirdparty_payment\status\StatusCheckerFactory::create($interfaceCode);
            $isPaid = $statusChecker->isPaid($result);
            
            Log::info('供应商订单支付状态判断', [
                'interface_code' => $interfaceCode,
                'checker_class' => get_class($statusChecker),
                'is_paid' => $isPaid,
                'result_status' => $result->getStatus(),
                'result_message' => $result->getMessage()
            ]);
            
            return $isPaid;
            
        } catch (\Exception $e) {
            Log::error('判断供应商订单支付状态异常', [
                'interface_code' => $interfaceCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 从供应商响应中获取支付时间
     * @param \app\service\thirdparty_payment\PaymentResult $result
     * @return string|null
     */
    private function getSupplierPaidTime(\app\service\thirdparty_payment\PaymentResult $result): ?string
    {
        try {
            $data = $result->getData();
            
            // 尝试从不同字段获取支付时间
            $paidTimeFields = [
                'paid_time',
                'payment_time', 
                'pay_time',
                'success_time',
                'payment_time_unix',
                'pay_time_unix',
                'success_time_unix',
                'order_success_time',
                'payment_time_unix_timestamp',
                'endtime',  // 百易支付支付完成时间
                'addtime'   // 百易支付创建时间
            ];
            
            foreach ($paidTimeFields as $field) {
                if (isset($data[$field]) && !empty($data[$field])) {
                    $timeValue = $data[$field];
                    
                    // 如果是时间戳，转换为日期时间格式
                    if (is_numeric($timeValue)) {
                        $timestamp = (int)$timeValue;
                        // 处理毫秒时间戳
                        if ($timestamp > 10000000000) {
                            $timestamp = $timestamp / 1000;
                        }
                        return date('Y-m-d H:i:s', $timestamp);
                    }
                    
                    // 如果已经是日期时间格式，直接返回
                    if (is_string($timeValue) && preg_match('/^\d{4}-\d{2}-\d{2}/', $timeValue)) {
                        return $timeValue;
                    }
                }
            }
            
            Log::info('未找到供应商支付时间字段', [
                'available_fields' => array_keys($data),
                'result_data' => $data
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('获取供应商支付时间失败', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 创建支付服务实例
     * @param PaymentChannel $channel
     * @return object|null
     */
    private function createPaymentService(PaymentChannel $channel): ?object
    {
        try {
            $serviceClass = "app\\service\\thirdparty_payment\\services\\{$channel->interface_code}Service";
            
            if (!class_exists($serviceClass)) {
                Log::error('支付服务类不存在', [
                    'interface_code' => $channel->interface_code,
                    'service_class' => $serviceClass
                ]);
                return null;
            }
            
            // 获取通道配置
            $config = $channel->basic_params ?? [];
            
            Log::info('创建支付服务实例', [
                'interface_code' => $channel->interface_code,
                'service_class' => $serviceClass,
                'has_config' => !empty($config)
            ]);
            
            return new $serviceClass($config);
            
        } catch (\Exception $e) {
            Log::error('创建支付服务实例失败', [
                'interface_code' => $channel->interface_code,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 生成追踪ID
     * @return string
     */
    private function generateTraceId(): string
    {
        return 'timeout_' . date('YmdHis') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }
    
    /**
     * 记录订单状态更新到链路追踪
     * @param int $orderId
     * @param int $oldStatus
     * @param \app\service\thirdparty_payment\PaymentResult $result
     * @param string $traceId
     */
    private function logOrderStatusUpdateToTrace(int $orderId, int $oldStatus, \app\service\thirdparty_payment\PaymentResult $result, string $traceId): void
    {
        try {
            // 获取订单信息
            $order = Order::find($orderId);
            if (!$order) {
                return;
            }
            
            // 使用订单的原始trace_id，如果没有则使用传入的trace_id
            $orderTraceId = $order->trace_id ?: $traceId;
            
            // 创建TraceService实例
            $traceService = new \app\service\TraceService();
            
            // 记录订单状态更新步骤
            $traceService->logLifecycleStep(
                $orderTraceId,
                $order->id,
                $order->merchant_id,
                'order_status_updated',
                'success',
                [
                    'old_status' => $oldStatus,
                    'new_status' => 3, // 支付成功
                    'update_source' => 'timeout_check_process',
                    'transaction_id' => $result->getTransactionId(),
                    'paid_time' => $order->paid_time,
                    'update_time' => date('Y-m-d H:i:s')
                ],
                null,
                0,
                $order->order_no,
                $order->merchant_order_no
            );

            Log::info('OrderTimeoutCheckProcess 订单状态更新已记录到链路追踪', [
                'trace_id' => $orderTraceId,
                'order_no' => $order->order_no,
                'old_status' => $oldStatus,
                'new_status' => 3,
                'update_source' => 'timeout_check_process'
            ]);

        } catch (\Exception $e) {
            Log::error('OrderTimeoutCheckProcess 记录订单状态更新链路追踪失败', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 触发商户回调通知
     * @param int $orderId
     * @param string $orderNo
     * @param string $traceId
     */
    private function triggerMerchantCallback(int $orderId, string $orderNo, string $traceId): void
    {
        try {
            // 获取订单信息
            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('订单不存在，无法触发商户回调', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo
                ]);
                return;
            }
            
            // 检查是否有通知地址
            if (empty($order->notify_url)) {
                Log::info('订单无通知地址，跳过商户回调', [
                    'trace_id' => $traceId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo
                ]);
                return;
            }
            
            // 注意：不检查notify_status，因为订单状态可能从成功变为关闭
            // 商户通知服务会根据当前订单状态发送正确的通知
            
            Log::info('开始触发商户回调通知', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'merchant_order_no' => $order->merchant_order_no,
                'notify_url' => $order->notify_url
            ]);
            
            // 使用商户通知服务触发回调
            $notificationService = new \app\service\notification\MerchantNotificationService();
            $notificationService->notifyMerchantAsync($order);
            
            Log::info('商户回调通知已触发', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'merchant_order_no' => $order->merchant_order_no,
                'notify_url' => $order->notify_url
            ]);
            
        } catch (\Exception $e) {
            Log::error('触发商户回调通知失败', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 获取状态文本描述
     * @param int $status
     * @return string
     */
    private function getStatusText(int $status): string
    {
        $statusMap = [
            1 => '待支付',
            2 => '支付中',
            3 => '支付成功',
            4 => '支付失败',
            5 => '已退款',
            6 => '已关闭'
        ];
        
        return $statusMap[$status] ?? "未知状态({$status})";
    }
    
    /**
     * 进程停止
     */
    public function onWorkerStop(): void
    {
        Log::info('订单超时检查进程停止', [
            'process_id' => getmypid(),
            'stop_time' => date('Y-m-d H:i:s')
        ]);
    }
}
