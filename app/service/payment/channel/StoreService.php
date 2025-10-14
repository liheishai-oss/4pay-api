<?php

namespace app\service\payment\channel;

use app\exception\MyBusinessException;
use app\model\PaymentChannel;
use app\model\Supplier;
use support\Db;

class StoreService
{
    /**
     * 创建支付通道
     * @param array $data
     * @return PaymentChannel
     * @throws MyBusinessException
     */
    public function createChannel(array $data): PaymentChannel
    {
        try {
            Db::beginTransaction();

            // 验证供应商是否存在
            $supplier = Supplier::find($data['supplier_id']);
            if (!$supplier) {
                throw new MyBusinessException('供应商不存在');
            }

            // 验证供应商是否有接口代码
            if (empty($supplier->interface_code)) {
                throw new MyBusinessException('供应商未设置接口代码');
            }

            $channel = new PaymentChannel();
            $channel->channel_name = $data['channel_name'];
            $channel->supplier_id = $data['supplier_id'];
            // 同步供应商的 interface_code 到通道表，避免每次关联查询
            $channel->interface_code = $supplier->interface_code;
            $channel->product_code = $data['product_code'] ?? null;
            $channel->status = $data['status'] ?? PaymentChannel::STATUS_ENABLED;
            $channel->weight = $data['weight'] ?? 0;
            $channel->min_amount = $data['min_amount'] ?? 0;
            $channel->max_amount = $data['max_amount'] ?? 0;
            $channel->cost_rate = $data['cost_rate'] ?? 0;
            $channel->remark = $data['remark'] ?? '';
            // 处理基础参数
            if (isset($data['basic_params'])) {
                if (empty($data['basic_params'])) {
                    $channel->basic_params = null;
                } else {
                    $channel->basic_params = $data['basic_params'];
                }
            }
            $channel->save();

            Db::commit();

            return $channel;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('创建支付通道失败：' . $e->getMessage());
        }
    }
}





