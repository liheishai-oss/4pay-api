<?php

namespace app\service\thirdparty_payment\interfaces;

use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 第三方支付服务接口
 * 定义所有支付服务必须实现的标准方法
 */
interface PaymentServiceInterface
{
    /**
     * 处理支付请求
     * @param array $params 支付参数
     * @return PaymentResult 支付结果
     */
    public function processPayment(array $params): PaymentResult;

    /**
     * 查询支付状态
     * @param string $orderNo 订单号
     * @return PaymentResult 查询结果
     */
    public function queryPayment(string $orderNo): PaymentResult;

    /**
     * 处理支付回调
     * @param array $callbackData 回调数据
     * @return PaymentResult 处理结果
     */
    public function handleCallback(array $callbackData): PaymentResult;


    /**
     * 获取服务名称
     * @return string
     */
    public function getServiceName(): string;

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string;

    /**
     * 验证参数
     * @param array $params
     * @return bool
     * @throws PaymentException
     */
    public function validateParams(array $params): bool;

}
