<?php

namespace app\service\thirdparty_payment\observers;

use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;
use app\service\thirdparty_payment\PaymentResult;

/**
 * 支付日志观察者
 * 记录所有支付相关的日志
 */
class PaymentLogObserver implements PaymentObserverInterface
{
    public function onPaymentSuccess(PaymentResult $result): void
    {
        $this->logPayment('success', $result);
    }

    public function onPaymentFailed(PaymentResult $result): void
    {
        $this->logPayment('failed', $result);
    }

    public function onPaymentProcessing(PaymentResult $result): void
    {
        $this->logPayment('processing', $result);
    }

    public function onRefundSuccess(PaymentResult $result): void
    {
        $this->logPayment('refund_success', $result);
    }

    public function onRefundFailed(PaymentResult $result): void
    {
        $this->logPayment('refund_failed', $result);
    }

    public function getObserverName(): string
    {
        return 'PaymentLogObserver';
    }

    /**
     * 记录支付日志
     * @param string $event
     * @param PaymentResult $result
     * @return void
     */
    private function logPayment(string $event, PaymentResult $result): void
    {
        $logData = [
            'event' => $event,
            'order_no' => $result->getOrderNo(),
            'transaction_id' => $result->getTransactionId(),
            'amount' => $result->getAmount(),
            'currency' => $result->getCurrency(),
            'status' => $result->getStatus(),
            'message' => $result->getMessage(),
            'timestamp' => $result->getTimestamp(),
            'raw_response' => $result->getRawResponse()
        ];

        // 这里可以集成具体的日志系统
        error_log("Payment Log: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }
}


