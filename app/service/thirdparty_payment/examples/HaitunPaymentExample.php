<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

use app\service\thirdparty_payment\services\HaitunService;

/**
 * 海豚支付使用示例
 */
class HaitunPaymentExample
{
    private $haitunService;

    public function __construct()
    {
        // 海豚支付配置
        $config = [
            'merchant_id' => 'HT20241201001',
            'api_key' => 'haitun_api_key_123456',
            'api_secret' => 'haitun_secret_abcdef',
            'gateway_url' => 'https://api.haitunpay.com/v1',
            'notify_url' => 'https://yourdomain.com/api/v1/callback/haitun',
            'return_url' => 'https://yourdomain.com/return/haitun',
            'callback_ips' => ['127.0.0.1', '::1', '43.156.128.199']
        ];

        $this->haitunService = new HaitunService($config);
    }

    /**
     * 创建支付订单示例
     */
    public function createPaymentExample()
    {
        echo "=== 海豚支付创建订单示例 ===\n";

        $paymentParams = [
            'out_trade_no' => 'HT' . time() . mt_rand(1000, 9999),
            'total_amount' => 100.00,
            'subject' => '海豚支付测试订单',
            'body' => '这是一个海豚支付测试订单',
            'payment_method' => 'alipay',
            'client_ip' => '127.0.0.1',
            'expire_time' => 1800
        ];

        echo "支付参数: " . json_encode($paymentParams, JSON_UNESCAPED_UNICODE) . "\n\n";

        $result = $this->haitunService->processPayment($paymentParams);

        echo "支付结果:\n";
        echo "状态: " . $result->getStatus() . "\n";
        echo "消息: " . $result->getMessage() . "\n";
        echo "订单号: " . $result->getOrderNo() . "\n";
        echo "交易号: " . $result->getTransactionId() . "\n";
        echo "金额: " . $result->getAmount() . "\n";
        echo "货币: " . $result->getCurrency() . "\n";
        echo "数据: " . json_encode($result->getData(), JSON_UNESCAPED_UNICODE) . "\n\n";

        return $result;
    }

    /**
     * 查询订单状态示例
     */
    public function queryPaymentExample($orderNo)
    {
        echo "=== 海豚支付查询订单示例 ===\n";

        echo "查询订单号: " . $orderNo . "\n\n";

        $result = $this->haitunService->queryPayment($orderNo);

        echo "查询结果:\n";
        echo "状态: " . $result->getStatus() . "\n";
        echo "消息: " . $result->getMessage() . "\n";
        echo "订单号: " . $result->getOrderNo() . "\n";
        echo "交易号: " . $result->getTransactionId() . "\n";
        echo "金额: " . $result->getAmount() . "\n";
        echo "货币: " . $result->getCurrency() . "\n";
        echo "数据: " . json_encode($result->getData(), JSON_UNESCAPED_UNICODE) . "\n\n";

        return $result;
    }

    /**
     * 处理回调示例
     */
    public function handleCallbackExample()
    {
        echo "=== 海豚支付回调处理示例 ===\n";

        // 模拟回调数据
        $callbackData = [
            'out_trade_no' => 'HT' . time() . mt_rand(1000, 9999),
            'order_id' => 'HT_ORDER_' . time(),
            'status' => 'SUCCESS',
            'amount' => '100.00',
            'payment_method' => 'alipay',
            'pay_time' => date('Y-m-d H:i:s'),
            'sign' => 'mock_signature_' . time()
        ];

        echo "回调数据: " . json_encode($callbackData, JSON_UNESCAPED_UNICODE) . "\n\n";

        $result = $this->haitunService->handleCallback($callbackData);

        echo "回调处理结果:\n";
        echo "状态: " . $result->getStatus() . "\n";
        echo "消息: " . $result->getMessage() . "\n";
        echo "订单号: " . $result->getOrderNo() . "\n";
        echo "交易号: " . $result->getTransactionId() . "\n";
        echo "金额: " . $result->getAmount() . "\n";
        echo "货币: " . $result->getCurrency() . "\n";
        echo "数据: " . json_encode($result->getData(), JSON_UNESCAPED_UNICODE) . "\n\n";

        return $result;
    }

    /**
     * 退款示例
     */
    public function refundExample($orderNo)
    {
        echo "=== 海豚支付退款示例 ===\n";

        $refundParams = [
            'out_trade_no' => $orderNo,
            'refund_amount' => 50.00,
            'refund_reason' => '用户申请部分退款',
            'refund_no' => 'RF' . time() . mt_rand(1000, 9999)
        ];

        echo "退款参数: " . json_encode($refundParams, JSON_UNESCAPED_UNICODE) . "\n\n";

        $result = $this->haitunService->refund($refundParams);

        echo "退款结果:\n";
        echo "状态: " . $result->getStatus() . "\n";
        echo "消息: " . $result->getMessage() . "\n";
        echo "订单号: " . $result->getOrderNo() . "\n";
        echo "交易号: " . $result->getTransactionId() . "\n";
        echo "金额: " . $result->getAmount() . "\n";
        echo "货币: " . $result->getCurrency() . "\n";
        echo "数据: " . json_encode($result->getData(), JSON_UNESCAPED_UNICODE) . "\n\n";

        return $result;
    }


    /**
     * 运行所有示例
     */
    public function runAllExamples()
    {
        echo "开始运行海豚支付示例...\n\n";

        // 1. 创建支付订单
        $paymentResult = $this->createPaymentExample();
        $orderNo = $paymentResult->getOrderNo();

        // 2. 查询订单状态
        if ($orderNo) {
            $this->queryPaymentExample($orderNo);
        }

        // 3. 处理回调
        $this->handleCallbackExample();

        // 4. 退款（如果订单存在）
        if ($orderNo) {
            $this->refundExample($orderNo);
        }

        // 5. 获取支持方式
//        $this->getSupportedMethodsExample();

        echo "海豚支付示例运行完成！\n";
    }
}

// 如果直接运行此文件
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $example = new HaitunPaymentExample();
    $example->runAllExamples();
}

