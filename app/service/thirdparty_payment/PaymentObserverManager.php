<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;

/**
 * 支付观察者管理器
 * 统一管理所有支付观察者
 */
class PaymentObserverManager
{
    private array $observers = [];
    private array $eventObservers = [];

    /**
     * 添加观察者
     * @param PaymentObserverInterface $observer
     * @param array $events 监听的事件列表，为空则监听所有事件
     * @return void
     */
    public function addObserver(PaymentObserverInterface $observer, array $events = []): void
    {
        $observerName = $observer->getObserverName();
        $this->observers[$observerName] = $observer;

        if (empty($events)) {
            // 监听所有事件
            $events = ['payment_success', 'payment_failed', 'payment_processing', 'refund_success', 'refund_failed'];
        }

        foreach ($events as $event) {
            $this->eventObservers[$event][] = $observerName;
        }
    }

    /**
     * 移除观察者
     * @param string $observerName
     * @return void
     */
    public function removeObserver(string $observerName): void
    {
        unset($this->observers[$observerName]);

        // 从所有事件中移除该观察者
        foreach ($this->eventObservers as $event => $observerNames) {
            $key = array_search($observerName, $observerNames);
            if ($key !== false) {
                unset($this->eventObservers[$event][$key]);
                $this->eventObservers[$event] = array_values($this->eventObservers[$event]);
            }
        }
    }

    /**
     * 通知观察者
     * @param string $event
     * @param PaymentResult $result
     * @return void
     */
    public function notify(string $event, PaymentResult $result): void
    {
        if (!isset($this->eventObservers[$event])) {
            return;
        }

        foreach ($this->eventObservers[$event] as $observerName) {
            if (!isset($this->observers[$observerName])) {
                continue;
            }

            $observer = $this->observers[$observerName];
            
            try {
                switch ($event) {
                    case 'payment_success':
                        $observer->onPaymentSuccess($result);
                        break;
                    case 'payment_failed':
                        $observer->onPaymentFailed($result);
                        break;
                    case 'payment_processing':
                        $observer->onPaymentProcessing($result);
                        break;
                    case 'refund_success':
                        $observer->onRefundSuccess($result);
                        break;
                    case 'refund_failed':
                        $observer->onRefundFailed($result);
                        break;
                }
            } catch (\Exception $e) {
                // 观察者通知失败不应影响主流程
                error_log("Observer notification failed for {$observerName}: " . $e->getMessage());
            }
        }
    }

    /**
     * 获取所有观察者
     * @return array
     */
    public function getAllObservers(): array
    {
        return $this->observers;
    }

    /**
     * 获取指定事件的观察者
     * @param string $event
     * @return array
     */
    public function getEventObservers(string $event): array
    {
        if (!isset($this->eventObservers[$event])) {
            return [];
        }

        $observers = [];
        foreach ($this->eventObservers[$event] as $observerName) {
            if (isset($this->observers[$observerName])) {
                $observers[] = $this->observers[$observerName];
            }
        }

        return $observers;
    }

    /**
     * 清空所有观察者
     * @return void
     */
    public function clearObservers(): void
    {
        $this->observers = [];
        $this->eventObservers = [];
    }

    /**
     * 获取观察者数量
     * @return int
     */
    public function getObserverCount(): int
    {
        return count($this->observers);
    }

    /**
     * 检查观察者是否存在
     * @param string $observerName
     * @return bool
     */
    public function hasObserver(string $observerName): bool
    {
        return isset($this->observers[$observerName]);
    }
}


