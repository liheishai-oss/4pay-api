<?php

namespace app\service\thirdparty_payment\interfaces;

use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 支付服务工厂接口
 * 用于动态创建不同的支付服务实例
 */
interface PaymentFactoryInterface
{
    /**
     * 创建支付服务实例
     * @param string $serviceType 服务类型
     * @param array $config 服务配置
     * @return PaymentServiceInterface
     * @throws PaymentException
     */
    public function createService(string $serviceType, array $config = []): PaymentServiceInterface;

    /**
     * 注册服务类型
     * @param string $serviceType 服务类型
     * @param string $serviceClass 服务类名
     * @return void
     */
    public function registerService(string $serviceType, string $serviceClass): void;

    /**
     * 获取所有支持的服务类型
     * @return array
     */
    public function getSupportedServices(): array;

    /**
     * 检查服务类型是否支持
     * @param string $serviceType
     * @return bool
     */
    public function isServiceSupported(string $serviceType): bool;
}
