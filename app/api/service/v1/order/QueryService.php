<?php

namespace app\api\service\v1\order;

use app\api\repository\v1\order\OrderRepository;
use app\api\validator\v1\order\BusinessDataValidator;
use app\enums\OrderStatus;
use app\exception\MyBusinessException;
use app\common\helpers\MoneyHelper;
use app\common\helpers\TraceIdHelper;

class QueryService
{
    protected OrderRepository $repository;
    protected BusinessDataValidator $validator;

    public function __construct(OrderRepository $repository, BusinessDataValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    /**
     * 查询订单
     * @param array $data
     * @return array
     * @throws MyBusinessException
     */
    public function queryOrder(array $data): array
    {
        try {
        $merchantKey = $data['merchant_key'];
        $queryType = $data['query_type'];
        
        // 通过数据仓库获取商户信息
        $merchant = $this->repository->getMerchantByKey($merchantKey);
        if (!$merchant) {
            throw new MyBusinessException('商户不存在7');
        }

        // 根据查询类型获取订单信息
        $order = null;
        if ($queryType === 'platform') {
            $order = $this->repository->getOrderByOrderNo($data['order_no']);
        } else {
            $order = $this->repository->getOrderByMerchantOrderNo($data['merchant_order_no']);
        }

        if (!$order) {
            throw new MyBusinessException('订单不存在');
        }

        // 验证订单归属
        if ($order->merchant_id !== $merchant['id']) {
            throw new MyBusinessException('订单不存在');
        }

        // 数据验证 - 验证业务数据
        $this->validator->validate($data, $merchant, $order);

        // 准备返回数据
        $responseData = [
            'merchant_key'         => $merchant->merchant_key,
            'order_no'             => $order->order_no,
            'merchant_order_no'    => $order->merchant_order_no,
            'third_party_order_no' => $order->third_party_order_no,
            'trace_id'             => TraceIdHelper::getFromOrder($order),
            'status'               => OrderStatus::getText($order->status),
            'amount'               => MoneyHelper::convertToYuan($order->amount),
            'fee'                  => MoneyHelper::convertToYuan($order->fee),
            'subject'              => $order->subject,
            'created_at'           => $order->created_at->format('Y-m-d H:i:s'),
            'paid_time'            => $order->paid_time ? $order->paid_time->format('Y-m-d H:i:s') : null,
            'extra_data'           => $order->extra_data
        ];

        // 使用SignatureHelper生成签名（trace_id不参与签名）
        $signatureHelper = new \app\common\helpers\SignatureHelper();
        $signData = [
            'merchant_key'         => $merchant->merchant_key,
            'order_no'             => $order->order_no,
            'merchant_order_no'    => $order->merchant_order_no,
            'third_party_order_no' => $order->third_party_order_no,
            'status'               => OrderStatus::getText($order->status),
            'amount'               => MoneyHelper::convertToYuan($order->amount),
            'fee'                  => MoneyHelper::convertToYuan($order->fee),
            'subject'              => $order->subject,
            'created_at'           => $order->created_at->format('Y-m-d H:i:s'),
            'paid_time'            => $order->paid_time ? $order->paid_time->format('Y-m-d H:i:s') : null
        ];
        $responseData['sign'] = $signatureHelper->generate($signData, $merchant->merchant_secret);

        return $responseData;
        
        } catch (\Exception $e) {
            if (config('app.debug', false)) {
                $trace = $e->getTraceAsString();
                $file = $e->getFile();
                $line = $e->getLine();
                throw new MyBusinessException("Debug错误: {$file}:{$line} - {$e->getMessage()}\nTrace: {$trace}");
            }
            throw $e;
        }
    }
}
