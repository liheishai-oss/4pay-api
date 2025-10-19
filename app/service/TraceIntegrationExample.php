<?php

namespace app\service;

use app\service\TraceIntegrationService;
use app\common\helpers\TraceIdHelper;

/**
 * 追踪集成示例
 * 展示如何在现有业务流程中集成追踪服务
 */
class TraceIntegrationExample
{
    private TraceIntegrationService $traceIntegration;

    public function __construct()
    {
        $this->traceIntegration = new TraceIntegrationService();
    }

    /**
     * 模拟完整的订单创建到完成流程
     */
    public function simulateCompleteOrderFlow(): void
    {
        echo "=== 完整订单流程追踪示例 ===\n";
        
        $traceId = TraceIdHelper::get();
        $orderId = 12345;
        $merchantId = 1001;
        $merchant = ['id' => $merchantId, 'merchant_name' => '测试商户', 'merchant_key' => 'MERCHANT_001'];
        
        echo "追踪ID: {$traceId}\n";
        echo "订单ID: {$orderId}\n";
        echo "商户ID: {$merchantId}\n\n";

        // 1. 订单创建
        $orderData = [
            'merchant_key' => 'MERCHANT_001',
            'merchant_order_no' => 'ORDER_20250101_001',
            'order_amount' => '100.00',
            'product_code' => 'ALIPAY_WEB'
        ];
        
        $this->traceIntegration->integrateOrderCreation($orderData, $merchant, $orderId);
        $this->sleep(10);

        // 2. 参数验证
        $this->traceIntegration->integrateParameterValidation($orderData, $merchant, $orderId, true, [
            'validation_time_ms' => 15,
            'validated_fields' => array_keys($orderData)
        ]);
        $this->sleep(10);

        // 3. 商户验证
        $this->traceIntegration->integrateMerchantValidation($merchant, $orderId, true, [
            'merchant_status' => 'active',
            'merchant_balance' => 50000
        ]);
        $this->sleep(10);

        // 4. 产品验证
        $this->traceIntegration->integrateProductValidation($orderId, $merchantId, 1, true, [
            'product_name' => '支付宝网页支付',
            'product_status' => 'active',
            'product_fee_rate' => '0.006'
        ]);
        $this->sleep(10);

        // 5. 通道选择
        $selectedChannel = [
            'id' => 5,
            'name' => '海豚支付通道',
            'selection_reason' => '优先级最高'
        ];
        $availableChannels = [
            ['id' => 5, 'name' => '海豚支付通道'],
            ['id' => 6, 'name' => '百易支付通道'],
            ['id' => 7, 'name' => '宝石支付通道']
        ];
        $this->traceIntegration->integrateChannelSelection($orderId, $merchantId, $selectedChannel, $availableChannels);
        $this->sleep(10);

        // 6. 支付发起
        $paymentData = [
            'payment_url' => 'https://api.haitun.com/alipay/order/create',
            'payment_method' => 'alipay_web',
            'amount' => 10000,
            'channel_id' => 5
        ];
        $this->traceIntegration->integratePaymentInitiation($orderId, $merchantId, $paymentData);
        $this->sleep(1000); // 模拟支付处理时间

        // 7. 支付结果处理
        $paymentResult = [
            'third_party_order_no' => 'HT_20250101_123456',
            'status' => 'success',
            'payment_time' => '2025-01-01 10:30:45'
        ];
        $this->traceIntegration->integratePaymentResult($orderId, $merchantId, true, $paymentResult);
        $this->sleep(50);

        // 8. 订单状态更新
        $this->traceIntegration->integrateOrderStatusUpdate($orderId, $merchantId, 'pending', 'paid', [
            'paid_time' => '2025-01-01 10:30:45',
            'paid_amount' => 10000
        ]);
        $this->sleep(50);

        // 9. 商户回调发送
        $callbackData = [
            'callback_url' => 'https://merchant.example.com/notify',
            'callback_data' => [
                'order_no' => 'ORDER_20250101_001',
                'status' => 'success',
                'amount' => '100.00'
            ],
            'attempt' => 1
        ];
        $this->traceIntegration->integrateCallbackSent($orderId, $merchantId, $callbackData);
        $this->sleep(150); // 模拟回调处理时间

        // 10. 商户回调响应
        $responseData = [
            'response' => 'OK',
            'status_code' => 200,
            'response_time' => 150,
            'attempt' => 1
        ];
        $this->traceIntegration->integrateCallbackResponse($orderId, $merchantId, true, $responseData);
        $this->sleep(50);

        // 11. 订单完成
        $this->traceIntegration->integrateOrderCompleted($orderId, $merchantId, 'completed', [
            'total_duration' => 1500,
            'success_rate' => 100
        ]);

        echo "\n=== 订单流程完成 ===\n";
        echo "总耗时: 1500ms\n";
        echo "成功率: 100%\n";
        echo "最终状态: completed\n";
    }

    /**
     * 模拟失败的订单流程
     */
    public function simulateFailedOrderFlow(): void
    {
        echo "\n=== 失败订单流程追踪示例 ===\n";
        
        $traceId = TraceIdHelper::get();
        $orderId = 12346;
        $merchantId = 1001;
        $merchant = ['id' => $merchantId, 'merchant_name' => '测试商户', 'merchant_key' => 'MERCHANT_001'];
        
        echo "追踪ID: {$traceId}\n";
        echo "订单ID: {$orderId}\n";
        echo "商户ID: {$merchantId}\n\n";

        // 1. 订单创建
        $orderData = [
            'merchant_key' => 'MERCHANT_001',
            'merchant_order_no' => 'ORDER_20250101_002',
            'order_amount' => '200.00',
            'product_code' => 'ALIPAY_WEB'
        ];
        
        $this->traceIntegration->integrateOrderCreation($orderData, $merchant, $orderId);
        $this->sleep(10);

        // 2. 参数验证
        $this->traceIntegration->integrateParameterValidation($orderData, $merchant, $orderId, true);
        $this->sleep(10);

        // 3. 商户验证
        $this->traceIntegration->integrateMerchantValidation($merchant, $orderId, true);
        $this->sleep(10);

        // 4. 产品验证
        $this->traceIntegration->integrateProductValidation($orderId, $merchantId, 1, true);
        $this->sleep(10);

        // 5. 通道选择
        $selectedChannel = ['id' => 5, 'name' => '海豚支付通道', 'selection_reason' => '优先级最高'];
        $this->traceIntegration->integrateChannelSelection($orderId, $merchantId, $selectedChannel);
        $this->sleep(10);

        // 6. 支付发起
        $paymentData = [
            'payment_url' => 'https://api.haitun.com/alipay/order/create',
            'payment_method' => 'alipay_web',
            'amount' => 20000,
            'channel_id' => 5
        ];
        $this->traceIntegration->integratePaymentInitiation($orderId, $merchantId, $paymentData);
        $this->sleep(1000);

        // 7. 支付失败
        $paymentResult = [
            'status' => 'failed',
            'error_code' => 'INSUFFICIENT_BALANCE',
            'error_message' => '余额不足'
        ];
        $this->traceIntegration->integratePaymentResult($orderId, $merchantId, false, $paymentResult);
        $this->sleep(50);

        // 8. 订单关闭
        $this->traceIntegration->integrateOrderClosed($orderId, $merchantId, 'payment_failed', [
            'close_reason_detail' => '支付失败 - 余额不足',
            'close_time' => '2025-01-01 10:35:00'
        ]);

        echo "\n=== 失败订单流程完成 ===\n";
        echo "总耗时: 1250ms\n";
        echo "成功率: 87.5% (7/8)\n";
        echo "最终状态: closed\n";
        echo "关闭原因: payment_failed\n";
    }

    /**
     * 模拟订单超时流程
     */
    public function simulateTimeoutOrderFlow(): void
    {
        echo "\n=== 超时订单流程追踪示例 ===\n";
        
        $traceId = TraceIdHelper::get();
        $orderId = 12347;
        $merchantId = 1001;
        
        echo "追踪ID: {$traceId}\n";
        echo "订单ID: {$orderId}\n";
        echo "商户ID: {$merchantId}\n\n";

        // 1. 订单创建
        $merchant = ['id' => $merchantId, 'merchant_name' => '测试商户', 'merchant_key' => 'MERCHANT_001'];
        $orderData = [
            'merchant_key' => 'MERCHANT_001',
            'merchant_order_no' => 'ORDER_20250101_003',
            'order_amount' => '300.00',
            'product_code' => 'ALIPAY_WEB'
        ];
        
        $this->traceIntegration->integrateOrderCreation($orderData, $merchant, $orderId);
        $this->sleep(10);

        // 2-5. 正常流程
        $this->traceIntegration->integrateParameterValidation($orderData, $merchant, $orderId, true);
        $this->traceIntegration->integrateMerchantValidation($merchant, $orderId, true);
        $this->traceIntegration->integrateProductValidation($orderId, $merchantId, 1, true);
        $selectedChannel = ['id' => 5, 'name' => '海豚支付通道', 'selection_reason' => '优先级最高'];
        $this->traceIntegration->integrateChannelSelection($orderId, $merchantId, $selectedChannel);
        $this->sleep(40);

        // 6. 支付发起
        $paymentData = [
            'payment_url' => 'https://api.haitun.com/alipay/order/create',
            'payment_method' => 'alipay_web',
            'amount' => 30000,
            'channel_id' => 5
        ];
        $this->traceIntegration->integratePaymentInitiation($orderId, $merchantId, $paymentData);
        $this->sleep(1000);

        // 7. 支付超时
        $this->traceIntegration->integrateOrderTimeout($orderId, $merchantId, [
            'timeout_duration' => 1800, // 30分钟
            'timeout_reason' => '用户未完成支付'
        ]);
        $this->sleep(50);

        // 8. 订单关闭
        $this->traceIntegration->integrateOrderClosed($orderId, $merchantId, 'timeout', [
            'timeout_duration' => 1800,
            'close_time' => '2025-01-01 11:00:00'
        ]);

        echo "\n=== 超时订单流程完成 ===\n";
        echo "总耗时: 1800s (30分钟)\n";
        echo "最终状态: closed\n";
        echo "关闭原因: timeout\n";
    }

    /**
     * 模拟订单查询流程
     */
    public function simulateOrderQueryFlow(): void
    {
        echo "\n=== 订单查询流程追踪示例 ===\n";
        
        $traceId = TraceIdHelper::get();
        $merchantId = 1001;
        
        echo "追踪ID: {$traceId}\n";
        echo "商户ID: {$merchantId}\n\n";

        // 1. 查询请求
        $queryParams = [
            'order_no' => 'ORDER_20250101_001',
            'merchant_key' => 'MERCHANT_001'
        ];
        
        $this->traceIntegration->integrateOrderQuery($queryParams, $merchantId, true, [
            'order_id' => 12345,
            'order_no' => 'ORDER_20250101_001',
            'status' => 'paid',
            'amount' => '100.00',
            'paid_time' => '2025-01-01 10:30:45'
        ]);

        echo "\n=== 查询流程完成 ===\n";
        echo "查询结果: 找到订单\n";
        echo "订单状态: paid\n";
    }

    /**
     * 模拟睡眠
     */
    private function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}