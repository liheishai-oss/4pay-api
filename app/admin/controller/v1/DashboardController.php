<?php

namespace app\admin\controller\v1;

use app\admin\service\DashboardService;
use support\Request;
use support\Response;

class DashboardController
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * 获取今日统计数据
     */
    public function getTodayStats(Request $request): Response
    {
        try {
            $result = $this->dashboardService->getTodayStats();
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \support\Log::error('获取今日统计失败', [
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
     * 获取最近7天订单变化趋势
     */
    public function getOrderTrend(Request $request): Response
    {
        try {
            $result = $this->dashboardService->getOrderTrend();
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \support\Log::error('获取订单趋势失败', [
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
     * 获取渠道订单排行榜
     */
    public function getChannelRanking(Request $request): Response
    {
        try {
            $result = $this->dashboardService->getChannelRanking();
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \support\Log::error('获取渠道排行榜失败', [
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
     * 获取综合仪表板数据
     */
    public function getDashboardData(Request $request): Response
    {
        try {
            $result = $this->dashboardService->getDashboardData();
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \support\Log::error('获取仪表板数据失败', [
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
