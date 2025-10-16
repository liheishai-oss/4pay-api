<?php

namespace app\admin\controller\v1;

use app\admin\service\CallbackMonitorService;
use support\Request;
use support\Response;
use support\Log;

/**
 * 回调监控控制器
 */
class CallbackMonitorController
{
    private $callbackMonitorService;

    public function __construct()
    {
        $this->callbackMonitorService = new CallbackMonitorService();
    }

    /**
     * 获取未通知订单列表
     */
    public function getUnnotifiedOrders(Request $request): Response
    {
        try {
            $page = $request->get('page', 1);
            $pageSize = $request->get('page_size', 20);
            $status = $request->get('status', 3); // 默认查询支付成功的订单
            $hours = $request->get('hours', 24); // 默认查询24小时内的订单

            $result = $this->callbackMonitorService->getUnnotifiedOrders($page, $pageSize, $status, $hours);

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取未通知订单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 手动触发订单通知
     */
    public function triggerNotification(Request $request): Response
    {
        try {
            $orderId = $request->get('order_id');
            $orderNo = $request->get('order_no');

            if (!$orderId && !$orderNo) {
                return json([
                    'code' => 400,
                    'msg' => '订单ID或订单号不能为空',
                    'data' => null
                ]);
            }

            $result = $this->callbackMonitorService->triggerNotification($orderId, $orderNo);

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('手动触发通知失败', [
                'order_id' => $request->get('order_id'),
                'order_no' => $request->get('order_no'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 批量触发通知
     */
    public function batchTriggerNotification(Request $request): Response
    {
        try {
            $orderIds = $request->get('order_ids', []);
            $orderNos = $request->get('order_nos', []);

            if (empty($orderIds) && empty($orderNos)) {
                return json([
                    'code' => 400,
                    'msg' => '订单ID列表或订单号列表不能为空',
                    'data' => null
                ]);
            }

            $result = $this->callbackMonitorService->batchTriggerNotification($orderIds, $orderNos);

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('批量触发通知失败', [
                'order_ids' => $request->get('order_ids'),
                'order_nos' => $request->get('order_nos'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取通知统计信息
     */
    public function getNotificationStats(Request $request): Response
    {
        try {
            $hours = $request->get('hours', 24);
            $result = $this->callbackMonitorService->getNotificationStats($hours);

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取通知统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 修复未通知订单
     */
    public function fixUnnotifiedOrders(Request $request): Response
    {
        try {
            $hours = $request->get('hours', 24);
            $status = $request->get('status', 3);
            $limit = $request->get('limit', 100);

            $result = $this->callbackMonitorService->fixUnnotifiedOrders($hours, $status, $limit);

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('修复未通知订单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
