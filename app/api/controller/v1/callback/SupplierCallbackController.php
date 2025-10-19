<?php

namespace app\api\controller\v1\callback;
use support\Request;
use support\Response;
use app\model\PaymentChannel;
use app\model\Order;
use app\service\TraceService;
use app\common\helpers\TraceIdHelper;

/**
 * 供货商回调控制器
 * 处理不同支付服务商的通知回调
 */
class SupplierCallbackController
{
    /**
     * 处理供货商回调
     * @param Request $request
     * @param string $payment_name 支付服务商名称 (动态：根据名称解析对应服务类)
     * @return Response
     */
    public function handleCallback(Request $request, string $payment_name): Response
    {
        try {
            // 记录调试信息
            \support\Log::info('SupplierCallbackController 开始处理', [
                'payment_name' => $payment_name,
                'request_method' => $request->method(),
                'request_uri' => $request->uri(),
                'request_route' => $request->route ?? [],
                'request_controller' => $request->controller ?? '',
                'request_action' => $request->action ?? ''
            ]);
            
            // 获取回调数据
            $callbackData = $this->getCallbackData($request);
            
            // 记录调试信息
            \support\Log::info('SupplierCallbackController 回调数据', [
                'payment_name' => $payment_name,
                'callback_data' => $callbackData,
                'request_post' => $request->post(),
                'request_get' => $request->get(),
                'request_raw_body' => $request->rawBody()
            ]);
            
            // 验证供应商回调IP（如果中间件没有处理）
            if (!$this->validateSupplierCallbackIp($request, $payment_name)) {
                return $this->errorResponse('IP验证失败');
            }
            
            // 根据支付服务商名称获取对应的服务实例
            $service = $this->getServiceInstance($payment_name);
            
            if (!$service) {
                return $this->errorResponse('不支持的支付服务商: ' . $payment_name);
            }
            
            // 处理回调
            \support\Log::info('SupplierCallbackController 开始调用支付服务处理回调', [
                'payment_name' => $payment_name,
                'callback_data' => $callbackData
            ]);
            
            $result = $service->handleCallback($callbackData);
            
            \support\Log::info('SupplierCallbackController 支付服务回调处理完成', [
                'payment_name' => $payment_name,
                'result_success' => $result->isSuccess(),
                'result_status' => $result->getStatus(),
                'result_message' => $result->getMessage(),
                'result_order_no' => $result->getOrderNo()
            ]);
            
            if ($result->isSuccess()) {
                // 更新订单状态
                $orderUpdated = $this->updateOrderStatus($result, $payment_name);
                
                if ($orderUpdated) {
                    // 记录成功日志
                    $this->logCallback('success', $payment_name, $callbackData, $result);
                    
                    // 记录到订单链路追踪
                    $this->logSupplierCallbackToTrace($result, $payment_name, $callbackData, 'success');
                    
                    return $this->callbackSuccessResponse($payment_name);
                } else {
                    // 订单更新失败，记录日志但返回成功（避免重复回调）
                    $this->logCallback('order_update_failed', $payment_name, $callbackData, $result);
                    
                    // 记录到订单链路追踪
                    $this->logSupplierCallbackToTrace($result, $payment_name, $callbackData, 'failed');
                    
                    return $this->callbackSuccessResponse($payment_name);
                }
            } else {
                // 记录失败日志
                $this->logCallback('failed', $payment_name, $callbackData, $result);
                
                // 记录到订单链路追踪
                $this->logSupplierCallbackToTrace($result, $payment_name, $callbackData, 'failed');
                
                return $this->callbackErrorResponse($payment_name);
            }
            
        } catch (\Exception $e) {
            // 记录异常日志
            $this->logCallback('exception', $payment_name, $request->all(), null, $e->getMessage());
            return $this->errorResponse('EXCEPTION');
        }
    }
    
    /**
     * 获取回调数据
     * @param Request $request
     * @return array
     */
    private function getCallbackData(Request $request): array
    {
        // 优先获取POST数据
        $data = $request->post();
        
        // 如果POST数据为空，尝试获取GET数据
        if (empty($data)) {
            $data = $request->get();
        }
        
        // 如果还是为空，尝试获取原始输入流
        if (empty($data)) {
            $rawInput = $request->rawBody();
            if (!empty($rawInput)) {
                // 尝试解析JSON
                $jsonData = json_decode($rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $jsonData;
                } else {
                    // 尝试解析表单数据
                    parse_str($rawInput, $data);
                }
            }
        }
        
        return $data ?: [];
    }
    
    /**
     * 根据支付服务商名称获取服务实例
     * @param string $paymentName
     * @return object|null
     */
    private function getServiceInstance(string $paymentName): ?object
    {
        $studly = $this->toStudlyCase($paymentName);
        $serviceClass = "app\\service\\thirdparty_payment\\services\\{$studly}Service";
        if (!class_exists($serviceClass)) {
            return null;
        }
        
        // 获取供应商配置
        $supplier = $this->getSupplierByPaymentName($paymentName);
        if (!$supplier) {
            return null;
        }
        
        // 从供应商的通道配置中获取基本参数
        $config = [];
        $channels = PaymentChannel::where('supplier_id', $supplier->id)->get();
        foreach ($channels as $channel) {
            if (!empty($channel->basic_params)) {
                $config = $channel->basic_params;
                break; // 使用第一个通道的配置
            }
        }
        
        return new $serviceClass($config);
    }
    
    /**
     * 记录订单状态更新到链路追踪
     * @param \app\model\Order $order 订单对象
     * @param int $oldStatus 旧状态
     * @param \app\service\thirdparty_payment\PaymentResult $result 支付结果
     * @param string $paymentName 支付服务商名称
     */
    private function logOrderStatusUpdateToTrace(\app\model\Order $order, int $oldStatus, \app\service\thirdparty_payment\PaymentResult $result, string $paymentName): void
    {
        try {
            // 使用订单的原始trace_id，如果没有则生成新的
            $traceId = $order->trace_id ?: \app\common\helpers\TraceIdHelper::get();
            
            // 创建TraceService实例
            $traceService = new \app\service\TraceService();
            
            // 记录订单状态更新步骤
            $traceService->logLifecycleStep(
                $traceId,
                $order->id,
                $order->merchant_id,
                'order_status_updated',
                'success',
                [
                    'old_status' => $oldStatus,
                    'new_status' => $order->status,
                    'payment_name' => $paymentName,
                    'transaction_id' => $result->getTransactionId(),
                    'paid_time' => $order->paid_time,
                    'update_time' => date('Y-m-d H:i:s')
                ],
                null,
                0,
                $order->order_no,
                $order->merchant_order_no
            );

            \support\Log::info('SupplierCallbackController 订单状态更新已记录到链路追踪', [
                'trace_id' => $traceId,
                'order_no' => $order->order_no,
                'old_status' => $oldStatus,
                'new_status' => $order->status,
                'payment_name' => $paymentName
            ]);

        } catch (\Exception $e) {
            \support\Log::error('SupplierCallbackController 记录订单状态更新链路追踪失败', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 记录供货商回调到订单链路追踪
     * @param mixed $result 处理结果
     * @param string $payment_name 支付服务商名称
     * @param array $callbackData 回调数据
     * @param string $status 状态
     */
    private function logSupplierCallbackToTrace($result, string $payment_name, array $callbackData, string $status): void
    {
        try {
            if (!$result || !$result->getOrderNo()) {
                return;
            }

            // 获取订单信息
            $order = Order::where('order_no', $result->getOrderNo())->first();
            if (!$order) {
                return;
            }

            // 使用订单的原始trace_id，如果没有则生成新的
            $traceId = $order->trace_id ?: TraceIdHelper::get();
            
            // 创建TraceService实例
            $traceService = new TraceService();
            
            // 记录供货商回调步骤
            $traceService->logLifecycleStep(
                $traceId,
                $order->id,
                $order->merchant_id,
                'supplier_callback',
                $status,
                [
                    'payment_name' => $payment_name,
                    'callback_data' => $callbackData,
                    'result_status' => $result->getStatus(),
                    'result_message' => $result->getMessage(),
                    'order_no' => $result->getOrderNo()
                ],
                null,
                0,
                $order->order_no,
                $order->merchant_order_no
            );

            \support\Log::info('SupplierCallbackController 已记录到订单链路追踪', [
                'trace_id' => $traceId,
                'order_no' => $result->getOrderNo(),
                'status' => $status,
                'payment_name' => $payment_name
            ]);

        } catch (\Exception $e) {
            \support\Log::error('SupplierCallbackController 记录链路追踪失败', [
                'error' => $e->getMessage(),
                'order_no' => $result ? $result->getOrderNo() : 'unknown'
            ]);
        }
    }

    /**
     * 记录回调日志
     * @param string $type
     * @param string $serviceName
     * @param array $callbackData
     * @param mixed $result
     * @param string|null $error
     */
    private function logCallback(string $type, string $serviceName, array $callbackData, $result = null, ?string $error = null): void
    {
        $logData = [
            'type' => $type,
            'service' => $serviceName,
            'callback_data' => $callbackData,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        if ($result) {
            $logData['result'] = [
                'status' => $result->getStatus(),
                'message' => $result->getMessage(),
                'order_no' => $result->getOrderNo(),
                'transaction_id' => $result->getTransactionId(),
                'amount' => $result->getAmount(),
                'currency' => $result->getCurrency()
            ];
        }
        
        if ($error) {
            $logData['error'] = $error;
        }
        
        // 记录到日志文件
        $logFile = runtime_path() . '/logs/supplier_callback_' . date('Y-m-d') . '.log';
        $logContent = date('Y-m-d H:i:s') . ' [' . strtoupper($type) . '] ' . json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 返回成功响应
     * @param string $message
     * @return Response
     */
    private function successResponse(string $message): Response
    {
        return json(['code' => 200, 'message' => $message, 'data' => null]);
    }
    
    /**
     * 返回错误响应
     * @param string $message
     * @return Response
     */
    private function errorResponse(string $message): Response
    {
        return json(['code' => 400, 'message' => $message, 'data' => null]);
    }
    
    /**
     * 验证供应商回调IP
     * @param Request $request
     * @param string $paymentName
     * @return bool
     */
    private function validateSupplierCallbackIp(Request $request, string $paymentName): bool
    {
        // 如果中间件已经处理了IP验证，直接返回true
        if ($request->supplier) {
            return true;
        }
        
        // 获取客户端IP
        $clientIp = $this->getClientIp($request);
        
        // 根据支付服务商名称获取供应商配置
        $supplier = $this->getSupplierByPaymentName($paymentName);
        
        if (!$supplier) {
            $this->logCallback('error', $paymentName, [], null, '未找到对应的供应商配置');
            return false;
        }
        
        // 检查供应商状态
        if ($supplier->status !== 1) {
            $this->logCallback('error', $paymentName, [], null, '供应商已被禁用');
            return false;
        }
        
        // 如果供应商没有设置IP白名单，则允许所有IP
        if (empty($supplier->callback_whitelist_ips)) {
            return true;
        }
        
        // 将字符串格式的白名单转换为数组
        $whitelistIps = $this->parseWhitelistIps($supplier->callback_whitelist_ips);
        
        // 检查IP是否在白名单中
        foreach ($whitelistIps as $allowedIp) {
            if ($this->ipMatches($clientIp, $allowedIp)) {
                return true;
            }
        }
        
        $this->logCallback('error', $paymentName, [], null, "回调IP地址 {$clientIp} 不在供应商允许的访问列表中");
        return false;
    }
    
    /**
     * 根据支付服务商名称获取供应商
     * @param string $paymentName
     * @return \app\model\Supplier|null
     */
    private function getSupplierByPaymentName(string $paymentName): ?\app\model\Supplier
    {
        // 根据支付名称动态映射到 interface_code（如 haitun -> Haitun, baiyi -> Baiyi）
        $studly = $this->toStudlyCase($paymentName);
        return \app\model\Supplier::where('interface_code', $studly)->first();
    }

    /**
     * 将字符串转为 StudlyCase（例："haitun_pay"/"haitun" -> "HaitunPay"/"Haitun"）
     */
    private function toStudlyCase(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', strtolower($name));
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }
    
    /**
     * 解析白名单IP字符串为数组
     * 
     * @param string $whitelistIps
     * @return array
     */
    private function parseWhitelistIps(string $whitelistIps): array
    {
        if (empty($whitelistIps)) {
            return [];
        }
        
        // 按|分隔符分割，并过滤空值
        return array_filter(array_map('trim', explode('|', $whitelistIps)));
    }
    
    /**
     * 获取客户端真实IP
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        // 优先从X-Forwarded-For头获取
        $xForwardedFor = $request->header('X-Forwarded-For');
        if ($xForwardedFor) {
            $ips = explode(',', $xForwardedFor);
            $clientIp = trim($ips[0]);
            if ($this->isValidIp($clientIp)) {
                return $clientIp;
            }
        }
        
        // 从X-Real-IP头获取
        $xRealIp = $request->header('X-Real-IP');
        if ($xRealIp && $this->isValidIp($xRealIp)) {
            return $xRealIp;
        }
        
        // 从CF-Connecting-IP头获取（Cloudflare）
        $cfConnectingIp = $request->header('CF-Connecting-IP');
        if ($cfConnectingIp && $this->isValidIp($cfConnectingIp)) {
            return $cfConnectingIp;
        }
        
        // 使用框架的getRealIp方法
        return $request->getRealIp(true);
    }
    
    /**
     * 验证IP是否有效
     * @param string $ip
     * @return bool
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    
    /**
     * 检查IP是否匹配（支持CIDR格式）
     * @param string $clientIp
     * @param string $allowedIp
     * @return bool
     */
    private function ipMatches(string $clientIp, string $allowedIp): bool
    {
        // 精确匹配
        if ($clientIp === $allowedIp) {
            return true;
        }
        
        // CIDR格式匹配
        if (strpos($allowedIp, '/') !== false) {
            return $this->ipInCidr($clientIp, $allowedIp);
        }
        
        // 通配符匹配（如 192.168.1.*）
        if (strpos($allowedIp, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($allowedIp, '/'));
            return preg_match('/^' . $pattern . '$/', $clientIp);
        }
        
        return false;
    }
    
    /**
     * 检查IP是否在CIDR范围内
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        // 将IP地址转换为长整型
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        // 计算网络掩码
        $maskLong = -1 << (32 - (int)$mask);
        
        // 检查IP是否在子网内
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
    
    /**
     * 更新订单状态（防重复回调）
     * @param \app\service\thirdparty_payment\PaymentResult $result
     * @param string $paymentName
     * @return bool
     */
    private function updateOrderStatus(\app\service\thirdparty_payment\PaymentResult $result, string $paymentName): bool
    {
        try {
            $orderNo = $result->getOrderNo();
            if (empty($orderNo)) {
                \support\Log::warning('SupplierCallbackController 订单号为空', [
                    'payment_name' => $paymentName,
                    'result' => $result->toArray()
                ]);
                return false;
            }
            
            // 使用分布式锁防止重复处理
            $lockKey = "callback_order_{$orderNo}";
            $lockAcquired = \support\Redis::set($lockKey, 1, 'EX', 30, 'NX'); // 30秒锁
            
            if (!$lockAcquired) {
                \support\Log::info('SupplierCallbackController 订单回调正在处理中，跳过', [
                    'order_no' => $orderNo,
                    'payment_name' => $paymentName
                ]);
                return true; // 返回true避免重复回调
            }
            
            // 查找订单
            $order = \app\model\Order::where('order_no', $orderNo)->first();
            if (!$order) {
                \support\Log::warning('SupplierCallbackController 订单不存在', [
                    'order_no' => $orderNo,
                    'payment_name' => $paymentName
                ]);
                \support\Redis::del($lockKey);
                return false;
            }
            
            // 检查订单状态，防止重复处理
            if ($order->status == 3) { // 3=支付成功
                \support\Log::info('SupplierCallbackController 订单已支付成功，跳过处理', [
                    'order_no' => $orderNo,
                    'current_status' => $order->status,
                    'payment_name' => $paymentName
                ]);
                \support\Redis::del($lockKey);
                return true; // 返回true避免重复回调
            }
            
            // 记录旧状态用于链路追踪
            $oldStatus = $order->status;
            
            // 更新订单状态
            $order->status = 3; // 3=支付成功
            $order->paid_time = date('Y-m-d H:i:s');
            $order->third_party_order_no = $result->getTransactionId();
            $order->notify_status = 0; // 重置通知状态，准备发送商户通知
            $order->save();
            
            \support\Log::info('SupplierCallbackController 订单状态更新成功', [
                'order_no' => $orderNo,
                'old_status' => $oldStatus,
                'new_status' => $order->status,
                'transaction_id' => $result->getTransactionId(),
                'payment_name' => $paymentName
            ]);
            
            // 记录订单状态更新到链路追踪
            $this->logOrderStatusUpdateToTrace($order, $oldStatus, $result, $paymentName);
            
            // 触发商户通知
            $this->triggerMerchantNotification($order, $paymentName);
            
            // 释放锁
            \support\Redis::del($lockKey);
            
            return true;
            
        } catch (\Exception $e) {
            \support\Log::error('SupplierCallbackController 更新订单状态失败', [
                'order_no' => $result->getOrderNo(),
                'payment_name' => $paymentName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 释放锁
            if (isset($lockKey) && isset($lockAcquired) && $lockAcquired) {
                \support\Redis::del($lockKey);
            }
            
            return false;
        }
    }
    
    /**
     * 触发商户通知
     * @param \app\model\Order $order
     * @param string $paymentName
     * @return void
     */
    private function triggerMerchantNotification(\app\model\Order $order, string $paymentName): void
    {
        try {
            // 检查是否有通知地址
            if (empty($order->notify_url)) {
                \support\Log::info('SupplierCallbackController 订单无通知地址，跳过商户通知', [
                    'order_no' => $order->order_no,
                    'payment_name' => $paymentName
                ]);
                return;
            }
            
            // 检查是否已经通知成功
            if ($order->notify_status == \app\model\Order::NOTIFY_STATUS_SUCCESS) {
                \support\Log::info('SupplierCallbackController 订单已通知成功，跳过重复通知', [
                    'order_no' => $order->order_no,
                    'payment_name' => $paymentName
                ]);
                return;
            }
            
            \support\Log::info('SupplierCallbackController 开始触发商户通知', [
                'order_no' => $order->order_no,
                'merchant_order_no' => $order->merchant_order_no,
                'notify_url' => $order->notify_url,
                'payment_name' => $paymentName
            ]);
            
            // 使用商户通知服务触发回调
            $notificationService = new \app\service\notification\MerchantNotificationService();
            $notificationService->notifyMerchantAsync($order);
            
            \support\Log::info('SupplierCallbackController 商户通知已触发', [
                'order_no' => $order->order_no,
                'payment_name' => $paymentName
            ]);
            
        } catch (\Exception $e) {
            \support\Log::error('SupplierCallbackController 触发商户通知失败', [
                'order_no' => $order->order_no,
                'payment_name' => $paymentName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 获取支付服务实例（用于响应格式配置）
     * @param string $paymentName
     * @return object|null
     */
    private function getPaymentService(string $paymentName): ?object
    {
        return $this->getServiceInstance($paymentName);
    }
    
    /**
     * 回调成功响应（根据支付服务商返回不同格式）
     * @param string $paymentName
     * @return Response
     */
    private function callbackSuccessResponse(string $paymentName): Response
    {
        // 尝试从服务类获取响应格式配置
        $service = $this->getPaymentService($paymentName);
        if ($service && method_exists($service, 'getCallbackResponseFormat')) {
            $format = $service->getCallbackResponseFormat(true);
            if ($format['type'] === 'text') {
                return response($format['content'], 200, $format['headers']);
            }
        }
        
        // 默认处理
        switch (strtolower($paymentName)) {
            case 'baiyi':
                // 百亿支付需要返回纯文本 "success"
                return response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            case 'haitun':
                // 海豚支付返回JSON格式
                return json(['code' => 200, 'message' => 'SUCCESS', 'data' => null]);
            default:
                // 默认返回JSON格式
                return json(['code' => 200, 'message' => 'SUCCESS', 'data' => null]);
        }
    }
    
    /**
     * 回调失败响应（根据支付服务商返回不同格式）
     * @param string $paymentName
     * @return Response
     */
    private function callbackErrorResponse(string $paymentName): Response
    {
        // 尝试从服务类获取响应格式配置
        $service = $this->getPaymentService($paymentName);
        if ($service && method_exists($service, 'getCallbackResponseFormat')) {
            $format = $service->getCallbackResponseFormat(false);
            if ($format['type'] === 'text') {
                return response($format['content'], 200, $format['headers']);
            }
        }
        
        // 默认处理
        switch (strtolower($paymentName)) {
            case 'baiyi':
                // 百亿支付失败时也返回 "success" 避免重复通知
                return response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            case 'haitun':
                // 海豚支付返回JSON格式
                return json(['code' => 400, 'message' => 'FAILED', 'data' => null]);
            default:
                // 默认返回JSON格式
                return json(['code' => 400, 'message' => 'FAILED', 'data' => null]);
        }
    }
}
