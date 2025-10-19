<?php

namespace app\service;

use app\service\TraceService;
use app\common\helpers\TraceIdHelper;
use support\Log;

/**
 * 追踪集成服务
 * 在现有业务流程中集成追踪服务
 */
class TraceIntegrationService
{
    private TraceService $traceService;

    public function __construct()
    {
        $this->traceService = new TraceService();
    }

    /**
     * 在订单创建服务中集成追踪
     * 修改 CreateService::createOrder 方法
     */
    public function integrateOrderCreation(array $data, $merchant, $orderId = null): void
    {
        $traceId = TraceIdHelper::get();
        
        // 1. 记录订单创建开始
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId ?? 0,
            $merchant['id'],
            'order_created',
            'success',
            [
                'merchant_key' => $data['merchant_key'] ?? '',
                'merchant_order_no' => $data['merchant_order_no'] ?? '',
                'order_amount' => $data['order_amount'] ?? '',
                'product_code' => $data['product_code'] ?? ''
            ]
        );

        // 同时记录到现有日志系统
        Log::info('订单创建追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'merchant_id' => $merchant['id'],
            'step' => 'order_created'
        ]);
    }

    /**
     * 在参数验证中集成追踪
     */
    public function integrateParameterValidation(array $data, $merchant, int $orderId, bool $success, array $validationData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchant['id'],
            'param_validated',
            $success ? 'success' : 'failed',
            array_merge([
                'validation_fields' => array_keys($data),
                'validation_time' => microtime(true)
            ], $validationData)
        );

        Log::info('参数验证追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'success' => $success,
            'step' => 'param_validated'
        ]);
    }

    /**
     * 在商户验证中集成追踪
     */
    public function integrateMerchantValidation($merchant, int $orderId, bool $success, array $validationData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchant['id'],
            'merchant_validated',
            $success ? 'success' : 'failed',
            array_merge([
                'merchant_status' => $merchant['status'] ?? '',
                'merchant_balance' => $merchant['balance'] ?? 0
            ], $validationData)
        );

        Log::info('商户验证追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'merchant_id' => $merchant['id'],
            'success' => $success,
            'step' => 'merchant_validated'
        ]);
    }

    /**
     * 在产品验证中集成追踪
     */
    public function integrateProductValidation(int $orderId, int $merchantId, int $productId, bool $success, array $productData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'product_validated',
            $success ? 'success' : 'failed',
            array_merge([
                'product_id' => $productId,
                'product_status' => $productData['status'] ?? ''
            ], $productData)
        );

        Log::info('产品验证追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'success' => $success,
            'step' => 'product_validated'
        ]);
    }

    /**
     * 在通道选择中集成追踪
     */
    public function integrateChannelSelection(int $orderId, int $merchantId, array $selectedChannel, array $availableChannels = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'channel_selected',
            'success',
            [
                'selected_channel_id' => $selectedChannel['id'] ?? 0,
                'selected_channel_name' => $selectedChannel['name'] ?? '',
                'available_channels_count' => count($availableChannels),
                'selection_criteria' => $selectedChannel['selection_reason'] ?? ''
            ]
        );

        Log::info('通道选择追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'channel_id' => $selectedChannel['id'] ?? 0,
            'step' => 'channel_selected'
        ]);
    }

    /**
     * 在支付发起中集成追踪
     */
    public function integratePaymentInitiation(int $orderId, int $merchantId, array $paymentData): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'payment_initiated',
            'success',
            [
                'payment_url' => $paymentData['payment_url'] ?? '',
                'payment_method' => $paymentData['payment_method'] ?? '',
                'payment_amount' => $paymentData['amount'] ?? 0,
                'channel_id' => $paymentData['channel_id'] ?? 0
            ]
        );

        Log::info('支付发起追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'payment_url' => $paymentData['payment_url'] ?? '',
            'step' => 'payment_initiated'
        ]);
    }

    /**
     * 在支付结果处理中集成追踪
     */
    public function integratePaymentResult(int $orderId, int $merchantId, bool $success, array $paymentResult): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            $success ? 'payment_success' : 'payment_failed',
            $success ? 'success' : 'failed',
            [
                'third_party_order_no' => $paymentResult['third_party_order_no'] ?? '',
                'payment_status' => $paymentResult['status'] ?? '',
                'payment_time' => $paymentResult['payment_time'] ?? '',
                'error_code' => $paymentResult['error_code'] ?? '',
                'error_message' => $paymentResult['error_message'] ?? ''
            ]
        );

        Log::info('支付结果追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'success' => $success,
            'step' => $success ? 'payment_success' : 'payment_failed'
        ]);
    }

    /**
     * 在订单状态更新中集成追踪
     */
    public function integrateOrderStatusUpdate(int $orderId, int $merchantId, string $oldStatus, string $newStatus, array $updateData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'order_status_updated',
            'success',
            array_merge([
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'update_time' => date('Y-m-d H:i:s')
            ], $updateData)
        );

        Log::info('订单状态更新追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'step' => 'order_status_updated'
        ]);
    }

    /**
     * 在商户回调发送中集成追踪
     */
    public function integrateCallbackSent(int $orderId, int $merchantId, array $callbackData): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'callback_sent',
            'success',
            [
                'callback_url' => $callbackData['callback_url'] ?? '',
                'callback_data' => $callbackData['callback_data'] ?? [],
                'callback_attempt' => $callbackData['attempt'] ?? 1,
                'callback_time' => date('Y-m-d H:i:s')
            ]
        );

        Log::info('商户回调发送追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'callback_url' => $callbackData['callback_url'] ?? '',
            'step' => 'callback_sent'
        ]);
    }

    /**
     * 在商户回调响应中集成追踪
     */
    public function integrateCallbackResponse(int $orderId, int $merchantId, bool $success, array $responseData): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            $success ? 'callback_success' : 'callback_failed',
            $success ? 'success' : 'failed',
            [
                'callback_response' => $responseData['response'] ?? '',
                'callback_status_code' => $responseData['status_code'] ?? 0,
                'callback_response_time' => $responseData['response_time'] ?? 0,
                'callback_attempt' => $responseData['attempt'] ?? 1
            ]
        );

        Log::info('商户回调响应追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'success' => $success,
            'step' => $success ? 'callback_success' : 'callback_failed'
        ]);
    }

    /**
     * 在订单完成中集成追踪
     */
    public function integrateOrderCompleted(int $orderId, int $merchantId, string $finalStatus, array $completionData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'order_completed',
            'success',
            array_merge([
                'final_status' => $finalStatus,
                'completion_time' => date('Y-m-d H:i:s'),
                'total_duration' => $completionData['total_duration'] ?? 0
            ], $completionData)
        );

        Log::info('订单完成追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'final_status' => $finalStatus,
            'step' => 'order_completed'
        ]);
    }

    /**
     * 在订单关闭中集成追踪
     */
    public function integrateOrderClosed(int $orderId, int $merchantId, string $closeReason, array $closeData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'order_closed',
            'success',
            array_merge([
                'close_reason' => $closeReason,
                'close_time' => date('Y-m-d H:i:s')
            ], $closeData)
        );

        Log::info('订单关闭追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'close_reason' => $closeReason,
            'step' => 'order_closed'
        ]);
    }

    /**
     * 在订单超时中集成追踪
     */
    public function integrateOrderTimeout(int $orderId, int $merchantId, array $timeoutData = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'order_timeout',
            'success',
            array_merge([
                'timeout_time' => date('Y-m-d H:i:s'),
                'timeout_duration' => $timeoutData['timeout_duration'] ?? 0
            ], $timeoutData)
        );

        Log::info('订单超时追踪', [
            'trace_id' => $traceId,
            'order_id' => $orderId,
            'step' => 'order_timeout'
        ]);
    }

    /**
     * 在订单查询中集成追踪
     */
    public function integrateOrderQuery(array $queryParams, int $merchantId, bool $found, array $queryResult = []): void
    {
        $traceId = TraceIdHelper::get();
        
        $this->traceService->logQueryStep(
            $traceId,
            $queryResult['order_id'] ?? null,
            $merchantId,
            'by_order_no',
            $found ? 'order_found' : 'order_not_found',
            $found ? 'success' : 'failed',
            [
                'query_params' => $queryParams,
                'query_result' => $queryResult,
                'query_time' => date('Y-m-d H:i:s')
            ]
        );

        Log::info('订单查询追踪', [
            'trace_id' => $traceId,
            'merchant_id' => $merchantId,
            'found' => $found,
            'step' => $found ? 'order_found' : 'order_not_found'
        ]);
    }
}
