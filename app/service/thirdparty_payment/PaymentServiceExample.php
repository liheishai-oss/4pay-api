<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\observers\PaymentLogObserver;
use app\service\thirdparty_payment\observers\PaymentNotificationObserver;

/**
 * 支付服务使用示例
 * 展示如何使用统一支付管理器
 */
class PaymentServiceExample
{
    private PaymentManager $paymentManager;

    public function __construct()
    {
        $this->paymentManager = new PaymentManager();
        $this->setupObservers();
    }

    /**
     * 设置观察者
     * @return void
     */
    private function setupObservers(): void
    {
        // 添加日志观察者
        $this->paymentManager->addObserver(new PaymentLogObserver());

        // 添加通知观察者
        $this->paymentManager->addObserver(new PaymentNotificationObserver());

        // 可以添加更多观察者...
    }

    /**
     * 处理支付宝网页支付
     * @param array $paymentData
     * @return PaymentResult
     */
    public function processAlipayWebPayment(array $paymentData): PaymentResult
    {
        $config = [
            'app_id' => 'your_app_id',
            'private_key' => 'your_private_key',
            'alipay_public_key' => 'alipay_public_key',
            'gateway_url' => 'https://openapi.alipay.com/gateway.do',
            'notify_url' => 'https://your-domain.com/payment/notify',
            'return_url' => 'https://your-domain.com/payment/return'
        ];

        return $this->paymentManager->processPayment('alipay_web', $paymentData, $config);
    }

    /**
     * 处理微信支付
     * @param array $paymentData
     * @return PaymentResult
     */
    public function processWechatPayment(array $paymentData): PaymentResult
    {
        $config = [
            'app_id' => 'your_wechat_app_id',
            'mch_id' => 'your_mch_id',
            'api_key' => 'your_api_key',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem'
        ];

        return $this->paymentManager->processPayment('wechat_jsapi', $paymentData, $config);
    }

    /**
     * 查询支付状态
     * @param string $serviceType
     * @param string $orderNo
     * @return PaymentResult
     */
    public function queryPaymentStatus(string $serviceType, string $orderNo): PaymentResult
    {
        $config = $this->getServiceConfig($serviceType);
        return $this->paymentManager->queryPayment($serviceType, $orderNo, $config);
    }

    /**
     * 处理支付回调
     * @param string $serviceType
     * @param array $callbackData
     * @return PaymentResult
     */
    public function handlePaymentCallback(string $serviceType, array $callbackData): PaymentResult
    {
        $config = $this->getServiceConfig($serviceType);
        return $this->paymentManager->handleCallback($serviceType, $callbackData, $config);
    }

    /**
     * 申请退款
     * @param string $serviceType
     * @param array $refundData
     * @return PaymentResult
     */
    public function processRefund(string $serviceType, array $refundData): PaymentResult
    {
        $config = $this->getServiceConfig($serviceType);
        return $this->paymentManager->refund($serviceType, $refundData, $config);
    }

    /**
     * 获取服务配置
     * @param string $serviceType
     * @return array
     */
    private function getServiceConfig(string $serviceType): array
    {
        $configs = [
            'alipay_web' => [
                'app_id' => 'your_app_id',
                'private_key' => 'your_private_key',
                'alipay_public_key' => 'alipay_public_key',
                'gateway_url' => 'https://openapi.alipay.com/gateway.do'
            ],
            'wechat_jsapi' => [
                'app_id' => 'your_wechat_app_id',
                'mch_id' => 'your_mch_id',
                'api_key' => 'your_api_key'
            ]
        ];

        return $configs[$serviceType] ?? [];
    }

    /**
     * 注册新的支付服务
     * @param string $serviceType
     * @param string $serviceClass
     * @return void
     */
    public function registerCustomService(string $serviceType, string $serviceClass): void
    {
        $this->paymentManager->registerService($serviceType, $serviceClass);
    }

    /**
     * 获取所有支持的服务
     * @return array
     */
    public function getSupportedServices(): array
    {
        return $this->paymentManager->getSupportedServices();
    }
}


