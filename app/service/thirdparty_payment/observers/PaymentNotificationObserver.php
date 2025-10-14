<?php

namespace app\service\thirdparty_payment\observers;

use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;
use app\service\thirdparty_payment\PaymentResult;

/**
 * 支付通知观察者
 * 发送支付状态通知（邮件、短信、推送等）
 */
class PaymentNotificationObserver implements PaymentObserverInterface
{
    public function onPaymentSuccess(PaymentResult $result): void
    {
        $this->sendNotification('payment_success', $result);
    }

    public function onPaymentFailed(PaymentResult $result): void
    {
        $this->sendNotification('payment_failed', $result);
    }

    public function onPaymentProcessing(PaymentResult $result): void
    {
        $this->sendNotification('payment_processing', $result);
    }

    public function onRefundSuccess(PaymentResult $result): void
    {
        $this->sendNotification('refund_success', $result);
    }

    public function onRefundFailed(PaymentResult $result): void
    {
        $this->sendNotification('refund_failed', $result);
    }

    public function getObserverName(): string
    {
        return 'PaymentNotificationObserver';
    }

    /**
     * 发送通知
     * @param string $event
     * @param PaymentResult $result
     * @return void
     */
    private function sendNotification(string $event, PaymentResult $result): void
    {
        $notificationData = [
            'event' => $event,
            'order_no' => $result->getOrderNo(),
            'amount' => $result->getAmount(),
            'currency' => $result->getCurrency(),
            'status' => $result->getStatus(),
            'message' => $result->getMessage(),
            'timestamp' => $result->getTimestamp()
        ];

        // 这里可以集成具体的通知系统
        // 例如：发送邮件、短信、推送通知等
        $this->sendEmail($notificationData);
        $this->sendSms($notificationData);
        $this->sendPushNotification($notificationData);
    }

    /**
     * 发送邮件通知
     * @param array $data
     * @return void
     */
    private function sendEmail(array $data): void
    {
        // 实现邮件发送逻辑
        error_log("Email notification: " . json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送短信通知
     * @param array $data
     * @return void
     */
    private function sendSms(array $data): void
    {
        // 实现短信发送逻辑
        error_log("SMS notification: " . json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送推送通知
     * @param array $data
     * @return void
     */
    private function sendPushNotification(array $data): void
    {
        // 实现推送通知逻辑
        error_log("Push notification: " . json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}


