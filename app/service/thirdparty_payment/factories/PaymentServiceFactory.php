<?php

namespace app\service\thirdparty_payment\factories;

use app\service\thirdparty_payment\interfaces\PaymentFactoryInterface;
use app\service\thirdparty_payment\interfaces\PaymentServiceInterface;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 支付服务工厂
 * 负责动态创建不同的支付服务实例
 */
class PaymentServiceFactory implements PaymentFactoryInterface
{
    private array $serviceMap = [];
    private array $defaultServices = [];

    public function __construct()
    {
        $this->autoDiscoverServices();
    }

    /**
     * 创建支付服务实例
     * @param string $serviceType
     * @param array $config
     * @return PaymentServiceInterface
     * @throws PaymentException
     */
    public function createService(string $serviceType, array $config = []): PaymentServiceInterface
    {
        if (!$this->isServiceSupported($serviceType)) {
            throw PaymentException::serviceNotFound($serviceType);
        }

        $serviceClass = $this->serviceMap[$serviceType];
        
        if (!class_exists($serviceClass)) {
            throw PaymentException::configError("服务类不存在: {$serviceClass}");
        }

        if (!is_subclass_of($serviceClass, PaymentServiceInterface::class)) {
            throw PaymentException::configError("服务类必须实现 PaymentServiceInterface: {$serviceClass}");
        }

        try {
            return new $serviceClass($config);
        } catch (\Exception $e) {
            throw PaymentException::configError("创建服务实例失败: " . $e->getMessage(), [
                'service_type' => $serviceType,
                'service_class' => $serviceClass,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 注册服务类型
     * @param string $serviceType
     * @param string $serviceClass
     * @return void
     */
    public function registerService(string $serviceType, string $serviceClass): void
    {
        $this->serviceMap[$serviceType] = $serviceClass;
    }

    /**
     * 获取所有支持的服务类型
     * @return array
     */
    public function getSupportedServices(): array
    {
        return array_keys($this->serviceMap);
    }

    /**
     * 检查服务类型是否支持
     * @param string $serviceType
     * @return bool
     */
    public function isServiceSupported(string $serviceType): bool
    {
        return isset($this->serviceMap[$serviceType]);
    }

    /**
     * 自动发现并注册服务
     * @return void
     */
    private function autoDiscoverServices(): void
    {
        $discovery = new \app\service\thirdparty_payment\ServiceAutoDiscovery();
        $services = $discovery->discoverServices();
        
        foreach ($services as $serviceType => $serviceClass) {
            $this->registerService($serviceType, $serviceClass);
        }
    }

    /**
     * 批量注册服务
     * @param array $services
     * @return void
     */
    public function registerServices(array $services): void
    {
        foreach ($services as $serviceType => $serviceClass) {
            $this->registerService($serviceType, $serviceClass);
        }
    }

    /**
     * 获取服务类名
     * @param string $serviceType
     * @return string|null
     */
    public function getServiceClass(string $serviceType): ?string
    {
        return $this->serviceMap[$serviceType] ?? null;
    }

    /**
     * 移除服务
     * @param string $serviceType
     * @return void
     */
    public function removeService(string $serviceType): void
    {
        unset($this->serviceMap[$serviceType]);
    }

    /**
     * 清空所有服务
     * @return void
     */
    public function clearServices(): void
    {
        $this->serviceMap = [];
    }

    /**
     * 重新加载配置文件中的服务
     * @return void
     */
    public function reloadServices(): void
    {
        $this->serviceMap = [];
        $this->loadServicesFromConfig();
    }

    /**
     * 从外部配置文件加载服务
     * @param string $configFile 配置文件路径
     * @return void
     */
    public function loadServicesFromFile(string $configFile): void
    {
        if (file_exists($configFile)) {
            $services = require $configFile;
            
            if (is_array($services)) {
                foreach ($services as $serviceType => $serviceClass) {
                    $this->registerService($serviceType, $serviceClass);
                }
            }
        }
    }
}
