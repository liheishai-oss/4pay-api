<?php

namespace app\service\payment\channel;

use app\exception\MyBusinessException;
use app\model\PaymentChannel;

class DetailService
{
    /**
     * 获取支付通道详情
     * @param int $id
     * @return PaymentChannel
     * @throws MyBusinessException
     */
    public function getDetail(int $id): PaymentChannel
    {
        $channel = PaymentChannel::with('supplier')->find($id);
        if (!$channel) {
            throw new MyBusinessException('支付通道不存在');
        }
        return $channel;
    }
}





