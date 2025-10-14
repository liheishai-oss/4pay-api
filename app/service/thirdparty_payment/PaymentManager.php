<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\factories\PaymentServiceFactory;
use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 统一支付管理器
 * 提供统一的支付服务入口和观察者管理
 */
class PaymentManager
{
    private PaymentServiceFactory $factory;
    private PaymentObserverManager $observerManager;
    private array $serviceInstances = [];

    public function __construct()
    {
        $this->factory = new PaymentServiceFactory();
        $this->observerManager = new PaymentObserverManager();
    }

    /**
     * 处理支付
     * @param string $serviceType
     * @param array $params
     * @param array $config
     * @return PaymentResult
     * @throws PaymentException
     */
    public function processPayment(string $serviceType, array $params, array $config = []): PaymentResult
    {
        $service = $this->getService($serviceType, $config);
        
        // 添加观察者到服务
        $this->attachObserversToService($service);
        
        // 验证参数
        $service->validateParams($params);
        
        // 执行支付
        $result = $service->processPayment($params);
        
        // 通知观察者
        $this->notifyObservers($result);
        
        return $result;
    }

    /**
     * 查询支付状态
     * @param string $serviceType
     * @param string $orderNo
     * @param array $config
     * @return PaymentResult
     * @throws PaymentException
     */
    public function queryPayment(string $serviceType, string $orderNo, array $config = []): PaymentResult
    {
        $service = $this->getService($serviceType, $config);
        
        $result = $service->queryPayment($orderNo);
        
        return $result;
    }

    /**
     * 处理支付回调
     * @param string $serviceType
     * @param array $callbackData
     * @param array $config
     * @return PaymentResult
     * @throws PaymentException
     */
    public function handleCallback(string $serviceType, array $callbackData, array $config = []): PaymentResult
    {
        $service = $this->getService($serviceType, $config);
        
        $result = $service->handleCallback($callbackData);
        
        // 通知观察者
        $this->notifyObservers($result);
        
        return $result;
    }

    /**
     * 申请退款
     * @param string $serviceType
     * @param array $refundParams
     * @param array $config
     * @return PaymentResult
     * @throws PaymentException
     */
    public function refund(string $serviceType, array $refundParams, array $config = []): PaymentResult
    {
        $service = $this->getService($serviceType, $config);
        
        // 添加观察者到服务
        $this->attachObserversToService($service);
        
        $result = $service->refund($refundParams);
        
        // 通知观察者
        $this->notifyObservers($result);
        
        return $result;
    }

    /**
     * 添加观察者
     * @param PaymentObserverInterface $observer
     * @param array $events
     * @return void
     */
    public function addObserver(PaymentObserverInterface $observer, array $events = []): void
    {
        $this->observerManager->addObserver($observer, $events);
    }

    /**
     * 移除观察者
     * @param string $observerName
     * @return void
     */
    public function removeObserver(string $observerName): void
    {
        $this->observerManager->removeObserver($observerName);
    }

    /**
     * 注册服务类型
     * @param string $serviceType
     * @param string $serviceClass
     * @return void
     */
    public function registerService(string $serviceType, string $serviceClass): void
    {
        $this->factory->registerService($serviceType, $serviceClass);
    }

    /**
     * 获取支持的服务类型
     * @return array
     */
    public function getSupportedServices(): array
    {
        return $this->factory->getSupportedServices();
    }

    /**
     * 获取服务实例
     * @param string $serviceType
     * @param array $config
     * @return mixed
     * @throws PaymentException
     */
    private function getService(string $serviceType, array $config = [])
    {
        $cacheKey = $serviceType . '_' . md5(serialize($config));
        
        if (!isset($this->serviceInstances[$cacheKey])) {
            $this->serviceInstances[$cacheKey] = $this->factory->createService($serviceType, $config);
        }
        
        return $this->serviceInstances[$cacheKey];
    }

    /**
     * 将观察者附加到服务
     * @param mixed $service
     * @return void
     */
    private function attachObserversToService($service): void
    {
        if (method_exists($service, 'addObserver')) {
            foreach ($this->observerManager->getAllObservers() as $observer) {
                $service->addObserver($observer);
            }
        }
    }

    /**
     * 通知观察者
     * @param PaymentResult $result
     * @return void
     */
    private function notifyObservers(PaymentResult $result): void
    {
        $event = $this->getEventFromResult($result);
        $this->observerManager->notify($event, $result);
    }

    /**
     * 根据结果确定事件类型
     * @param PaymentResult $result
     * @return string
     */
    private function getEventFromResult(PaymentResult $result): string
    {
        if ($result->isSuccess()) {
            return 'payment_success';
        } elseif ($result->isFailed()) {
            return 'payment_failed';
        } else {
            return 'payment_processing';
        }
    }

    /**
     * 清空服务实例缓存
     * @return void
     */
    public function clearServiceCache(): void
    {
        $this->serviceInstances = [];
    }

    /**
     * 获取观察者管理器
     * @return PaymentObserverManager
     */
    public function getObserverManager(): PaymentObserverManager
    {
        return $this->observerManager;
    }

    /**
     * 获取服务工厂
     * @return PaymentServiceFactory
     */
    public function getFactory(): PaymentServiceFactory
    {
        return $this->factory;
    }
}


