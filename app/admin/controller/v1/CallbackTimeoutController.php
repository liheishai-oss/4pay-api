<?php

namespace app\admin\controller\v1;

use app\admin\service\CallbackTimeoutService;
use support\Request;
use support\Response;
use support\Log;

/**
 * 回调超时管理控制器
 */
class CallbackTimeoutController
{
    /**
     * 检查回调超时的订单
     * @param Request $request
     * @return Response
     */
    public function checkTimeout(Request $request): Response
    {
        try {
            $timeoutMinutes = $request->input('timeout_minutes', 10);
            $limit = $request->input('limit', 100);
            
            $callbackTimeoutService = new CallbackTimeoutService();
            $result = $callbackTimeoutService->checkCallbackTimeout($timeoutMinutes, $limit);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '回调超时检查完成',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('检查回调超时失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '检查回调超时失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取回调超时统计信息
     * @param Request $request
     * @return Response
     */
    public function getStats(Request $request): Response
    {
        try {
            $timeoutMinutes = $request->input('timeout_minutes', 10);
            $hours = $request->input('hours', 24);
            
            $callbackTimeoutService = new CallbackTimeoutService();
            $stats = $callbackTimeoutService->getCallbackTimeoutStats($timeoutMinutes, $hours);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取回调超时统计成功',
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取回调超时统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取回调超时统计失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 手动处理单个订单的回调超时
     * @param Request $request
     * @return Response
     */
    public function handleOrder(Request $request): Response
    {
        try {
            $orderId = $request->input('order_id');
            $orderNo = $request->input('order_no');
            
            if (!$orderId && !$orderNo) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '请提供订单ID或订单号',
                    'data' => null
                ]);
            }
            
            $callbackTimeoutService = new CallbackTimeoutService();
            
            // 查找订单
            $order = null;
            if ($orderId) {
                $order = \app\model\Order::find($orderId);
            } elseif ($orderNo) {
                $order = \app\model\Order::where('order_no', $orderNo)->first();
            }
            
            if (!$order) {
                return json([
                    'code' => 404,
                    'status' => false,
                    'message' => '订单不存在',
                    'data' => null
                ]);
            }
            
            // 检查订单状态
            if ($order->status != \app\model\Order::STATUS_SUCCESS) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '订单状态不是支付成功，无法处理回调超时',
                    'data' => null
                ]);
            }
            
            // 检查是否已经在队列中
            if ($callbackTimeoutService->isOrderInQueue($order->id)) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '订单仍在回调队列中，暂不标记为失败',
                    'data' => [
                        'order_no' => $order->order_no,
                        'order_id' => $order->id,
                        'status' => 'in_queue'
                    ]
                ]);
            }
            
            // 检查是否已经有成功的回调记录
            if ($callbackTimeoutService->hasSuccessfulCallback($order->id)) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '订单已有成功回调记录，无需处理',
                    'data' => [
                        'order_no' => $order->order_no,
                        'order_id' => $order->id,
                        'status' => 'already_success'
                    ]
                ]);
            }
            
            // 标记为回调失败
            $order->notify_status = \app\model\Order::NOTIFY_STATUS_FAILED;
            $order->save();
            
            Log::info('手动处理回调超时', [
                'order_no' => $order->order_no,
                'order_id' => $order->id,
                'paid_time' => $order->paid_time,
                'notify_url' => $order->notify_url
            ]);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '订单已标记为回调失败',
                'data' => [
                    'order_no' => $order->order_no,
                    'order_id' => $order->id,
                    'status' => 'marked_failed',
                    'notify_status' => $order->notify_status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('手动处理回调超时失败', [
                'order_id' => $orderId ?? null,
                'order_no' => $orderNo ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '处理回调超时失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
