<?php

namespace app\service\order;

use app\exception\MyBusinessException;
use app\model\Order;

class DetailService
{
    /**
     * 获取订单详情
     * @param int $id
     * @return Order
     * @throws MyBusinessException
     */
    public function getOrderDetail(int $id): Order
    {
        $order = Order::with(['merchant', 'product', 'channel', 'refunds', 'notifyLogs'])->find($id);

        if (!$order) {
            throw new MyBusinessException('订单不存在');
        }

        return $order;
    }
}
