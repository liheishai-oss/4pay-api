<?php

namespace app\service\thirdparty_payment\interfaces;

use app\service\thirdparty_payment\PaymentResult;

/**
 * 支付观察者接口
 * 用于实现观察者模式，监听支付事件
 */
interface PaymentObserverInterface
{
    /**
     * 支付成功事件
     * @param PaymentResult $result 支付结果
     * @return void
     */
    public function onPaymentSuccess(PaymentResult $result): void;

    /**
     * 支付失败事件
     * @param PaymentResult $result 支付结果
     * @return void
     */
    public function onPaymentFailed(PaymentResult $result): void;

    /**
     * 支付处理中事件
     * @param PaymentResult $result 支付结果
     * @return void
     */
    public function onPaymentProcessing(PaymentResult $result): void;

    /**
     * 退款成功事件
     * @param PaymentResult $result 退款结果
     * @return void
     */
    public function onRefundSuccess(PaymentResult $result): void;

    /**
     * 退款失败事件
     * @param PaymentResult $result 退款结果
     * @return void
     */
    public function onRefundFailed(PaymentResult $result): void;

    /**
     * 获取观察者名称
     * @return string
     */
    public function getObserverName(): string;
}


