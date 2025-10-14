<?php

namespace app\admin\controller\v1;

use app\admin\service\MerchantCallbackMonitorService;
use support\Request;
use support\Response;
use support\Log;

class MerchantCallbackMonitorController
{
    protected array $noNeedLogin = ['*'];
    private MerchantCallbackMonitorService $monitorService;

    public function __construct()
    {
        $this->monitorService = new MerchantCallbackMonitorService();
    }

    /**
     * 获取实时监控数据
     * @param Request $request
     * @return Response
     */
    public function getRealTimeData(Request $request): Response
    {
        try {
            $timeRange = $request->get('time_range', '1m');
            $data = $this->monitorService->getRealTimeData($timeRange);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取实时监控数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取实时监控数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取商户状态统计
     * @param Request $request
     * @return Response
     */
    public function getMerchantStats(Request $request): Response
    {
        try {
            $timeRange = $request->get('time_range', '1m');
            $data = $this->monitorService->getMerchantStats($timeRange);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取商户状态统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取商户状态统计失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取通知日志
     * @param Request $request
     * @return Response
     */
    public function getNotifyLogs(Request $request): Response
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            
            // 构建筛选条件
            $filters = [
                'order_no' => $request->get('order_no', ''),
                'status' => $request->get('status', ''),
                'merchant_key' => $request->get('merchant_key', ''),
                'start_time' => $request->get('start_time', ''),
                'end_time' => $request->get('end_time', '')
            ];
            
            $data = $this->monitorService->getNotifyLogs($page, $limit, $filters);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取通知日志失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取通知日志失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取商户详情
     * @param Request $request
     * @return Response
     */
    public function getMerchantDetail(Request $request): Response
    {
        try {
            $merchantId = $request->get('merchant_id');
            
            if (!$merchantId) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '商户ID不能为空',
                    'data' => null
                ]);
            }
            
            $data = $this->monitorService->getMerchantDetail($merchantId);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取商户详情失败', [
                'merchant_id' => $request->get('merchant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取商户详情失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 重置熔断器
     * @param Request $request
     * @return Response
     */
    public function resetCircuitBreaker(Request $request): Response
    {
        try {
            $merchantKey = $request->input('merchant_key');
            
            if (!$merchantKey) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '商户标识不能为空',
                    'data' => null
                ]);
            }
            
            $result = $this->monitorService->resetCircuitBreaker($merchantKey);
            
            if ($result) {
                return json([
                    'code' => 200,
                    'status' => true,
                    'message' => '熔断器重置成功',
                    'data' => null
                ]);
            } else {
                return json([
                    'code' => 500,
                    'status' => false,
                    'message' => '熔断器重置失败',
                    'data' => null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('重置熔断器失败', [
                'merchant_key' => $request->input('merchant_key'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '重置熔断器失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取系统健康状态
     * @return Response
     */
    public function getHealthStatus(): Response
    {
        try {
            $data = $this->monitorService->getSystemHealth();
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取系统健康状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取系统健康状态失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取支付成功但回调失败的订单
     * @param Request $request
     * @return Response
     */
    public function getPaidButCallbackFailedOrders(Request $request): Response
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            
            $data = $this->monitorService->getPaidButCallbackFailedOrders($page, $limit);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取支付成功但回调失败的订单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取超时监控统计信息
     * @return Response
     */
    public function getTimeoutMonitorStats(): Response
    {
        try {
            $data = $this->monitorService->getTimeoutMonitorStats();
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取超时监控统计信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取超时监控进程状态
     * @return Response
     */
    public function getTimeoutMonitorStatus(): Response
    {
        try {
            $data = $this->monitorService->getTimeoutMonitorStatus();
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取超时监控进程状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 重启超时监控进程
     * @return Response
     */
    public function restartTimeoutMonitor(): Response
    {
        try {
            $result = $this->monitorService->restartTimeoutMonitor();
            
            if ($result['success']) {
                return json([
                    'code' => 200,
                    'status' => true,
                    'message' => '重启成功',
                    'data' => $result
                ]);
            } else {
                return json([
                    'code' => 500,
                    'status' => false,
                    'message' => '重启失败: ' . $result['message'],
                    'data' => $result
                ]);
            }
        } catch (\Exception $e) {
            Log::error('重启超时监控进程失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '重启失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取队列状态
     * @return Response
     */
    public function getQueueStatus(): Response
    {
        try {
            $data = $this->monitorService->getQueueStatus();
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取队列状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}