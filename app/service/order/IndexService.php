<?php

namespace app\service\order;

use app\model\Order;
use support\Db;

class IndexService
{
    /**
     * 获取订单列表
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getOrderList(array $params = [])
    {
        $query = Order::with(['merchant', 'product', 'channel']);

        // 搜索条件
        if (!empty($params['order_no'])) {
            $query->where('order_no', 'like', '%' . $params['order_no'] . '%');
        }

        if (!empty($params['merchant_order_no'])) {
            $query->where('merchant_order_no', 'like', '%' . $params['merchant_order_no'] . '%');
        }

        if (!empty($params['third_party_order_no'])) {
            $query->where('third_party_order_no', 'like', '%' . $params['third_party_order_no'] . '%');
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['merchant_id'])) {
            $query->where('merchant_id', $params['merchant_id']);
        }

        if (!empty($params['channel_id'])) {
            $query->where('channel_id', $params['channel_id']);
        }

        if (!empty($params['payment_method'])) {
            $query->where('payment_method', 'like', '%' . $params['payment_method'] . '%');
        }

        // 金额范围查询
        if (!empty($params['min_amount'])) {
            $query->where('amount', '>=', $params['min_amount'] * 100); // 转换为分
        }

        if (!empty($params['max_amount'])) {
            $query->where('amount', '<=', $params['max_amount'] * 100); // 转换为分
        }

        // 时间范围查询
        if (!empty($params['start_time'])) {
            $query->where('created_at', '>=', $params['start_time']);
        } else {
            // 如果没有指定开始时间，默认搜索当天数据
            $today = date('Y-m-d');
            $query->where('created_at', '>=', $today . ' 00:00:00');
        }

        if (!empty($params['end_time'])) {
            $query->where('created_at', '<=', $params['end_time']);
        } else {
            // 如果没有指定结束时间，默认到当天结束
            $today = date('Y-m-d');
            $query->where('created_at', '<=', $today . ' 23:59:59');
        }

        // 排序
        $sortField = $params['sort_field'] ?? 'id';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $perPage = $params['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * 补单
     * @param int $orderId
     * @return array
     */
    public function reissueOrder(int $orderId): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 检查订单状态是否允许补单
        if (!in_array($order->status, [4, 6])) { // 4=支付失败, 6=已关闭
            throw new \Exception('订单状态不允许补单');
        }

        // 这里应该调用实际的补单逻辑
        // 例如：重新发起支付请求、更新订单状态等
        $order->status = 2; // 设置为支付中
        $order->save();

        return [
            'order_id' => $orderId,
            'status' => 'reissued',
            'message' => '补单成功'
        ];
    }

    /**
     * 回调
     * @param int $orderId
     * @return array
     */
    public function callbackOrder(int $orderId): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 检查订单状态是否允许回调
        if ($order->status !== 3) { // 3=支付成功
            throw new \Exception('只有支付成功的订单才能回调');
        }

        // 这里应该调用实际的回调逻辑
        // 例如：重新发送回调通知给商户
        $order->notify_status = 0; // 重置通知状态
        $order->save();

        return [
            'order_id' => $orderId,
            'status' => 'callback_sent',
            'message' => '回调成功'
        ];
    }

    /**
     * 批量补单
     * @param array $orderIds
     * @return array
     */
    public function batchReissueOrders(array $orderIds): array
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $this->reissueOrder($orderId);
                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "订单 {$orderId}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'message' => "批量补单完成，成功: {$successCount}, 失败: {$failedCount}"
        ];
    }

    /**
     * 批量回调
     * @param array $orderIds
     * @return array
     */
    public function batchCallbackOrders(array $orderIds): array
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $this->callbackOrder($orderId);
                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "订单 {$orderId}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'message' => "批量回调完成，成功: {$successCount}, 失败: {$failedCount}"
        ];
    }
}
