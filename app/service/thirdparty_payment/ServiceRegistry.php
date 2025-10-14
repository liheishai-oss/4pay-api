<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\factories\PaymentServiceFactory;

/**
 * 支付服务注册管理器
 * 提供更灵活的服务注册和管理功能
 */
class ServiceRegistry
{
    private PaymentServiceFactory $factory;
    private array $customConfigFiles = [];

    public function __construct()
    {
        $this->factory = new PaymentServiceFactory();
    }

    /**
     * 获取支付服务工厂实例
     * @return PaymentServiceFactory
     */
    public function getFactory(): PaymentServiceFactory
    {
        return $this->factory;
    }

    /**
     * 添加自定义配置文件
     * @param string $configFile 配置文件路径
     * @return self
     */
    public function addConfigFile(string $configFile): self
    {
        $this->customConfigFiles[] = $configFile;
        return $this;
    }

    /**
     * 加载所有配置的服务
     * @return self
     */
    public function loadAllServices(): self
    {
        // 重新创建工厂实例以触发自动发现
        $this->factory = new PaymentServiceFactory();
        
        // 加载自定义配置
        foreach ($this->customConfigFiles as $configFile) {
            $this->factory->loadServicesFromFile($configFile);
        }
        
        return $this;
    }

    /**
     * 注册单个服务
     * @param string $serviceType 服务类型
     * @param string $serviceClass 服务类名
     * @return self
     */
    public function registerService(string $serviceType, string $serviceClass): self
    {
        $this->factory->registerService($serviceType, $serviceClass);
        return $this;
    }

    /**
     * 批量注册服务
     * @param array $services 服务数组
     * @return self
     */
    public function registerServices(array $services): self
    {
        $this->factory->registerServices($services);
        return $this;
    }

    /**
     * 获取所有已注册的服务
     * @return array
     */
    public function getAllServices(): array
    {
        return $this->factory->getSupportedServices();
    }

    /**
     * 检查服务是否已注册
     * @param string $serviceType 服务类型
     * @return bool
     */
    public function isServiceRegistered(string $serviceType): bool
    {
        return $this->factory->isServiceSupported($serviceType);
    }

    /**
     * 移除服务
     * @param string $serviceType 服务类型
     * @return self
     */
    public function removeService(string $serviceType): self
    {
        $this->factory->removeService($serviceType);
        return $this;
    }

    /**
     * 清空所有服务
     * @return self
     */
    public function clearAllServices(): self
    {
        $this->factory->clearServices();
        return $this;
    }

    /**
     * 创建支付管理器实例
     * @return PaymentManager
     */
    public function createPaymentManager(): PaymentManager
    {
        return new PaymentManager();
    }
}
