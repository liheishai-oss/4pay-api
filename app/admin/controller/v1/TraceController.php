<?php

namespace app\admin\controller\v1;

use app\service\TraceService;
use support\Request;
use support\Response;
use support\Log;

class TraceController
{
    private TraceService $traceService;

    public function __construct()
    {
        $this->traceService = new TraceService();
    }

    /**
     * 搜索链路
     * @param Request $request
     * @return Response
     */
    public function search(Request $request): Response
    {
        try {
            $keyword = $request->input('keyword', '');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            if (empty($keyword)) {
                return json([
                    'code' => 400,
                    'status' => false,
                    'message' => '搜索关键词不能为空',
                    'data' => null
                ]);
            }

            $result = $this->traceService->searchTraces($keyword, $page, $limit);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '搜索成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('搜索链路失败', [
                'keyword' => $request->input('keyword'),
                'error' => $e->getMessage()
            ]);

            return json([
                'code' => 500,
                'status' => false,
                'message' => '搜索失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取生命周期链路详情
     * @param Request $request
     * @param string $traceId
     * @return Response
     */
    public function lifecycleDetail(Request $request, string $traceId): Response
    {
        try {
            $traceData = $this->traceService->getLifecycleTrace($traceId);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $traceData
            ]);
        } catch (\Exception $e) {
            Log::error('获取生命周期链路详情失败', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
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
     * 获取查询链路详情
     * @param Request $request
     * @param string $traceId
     * @return Response
     */
    public function queryDetail(Request $request, string $traceId): Response
    {
        try {
            $traceData = $this->traceService->getQueryTrace($traceId);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $traceData
            ]);
        } catch (\Exception $e) {
            Log::error('获取查询链路详情失败', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
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
     * 获取链路统计信息
     * @param Request $request
     * @param string $traceId
     * @return Response
     */
    public function statistics(Request $request, string $traceId): Response
    {
        try {
            $type = $request->input('type', 'lifecycle');
            $statistics = $this->traceService->getTraceStatistics($traceId, $type);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('获取链路统计失败', [
                'trace_id' => $traceId,
                'type' => $request->input('type'),
                'error' => $e->getMessage()
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
     * 获取链路列表（分页）
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $type = $request->input('type', 'all'); // all, lifecycle, query
            $merchantId = $request->input('merchant_id');
            $status = $request->input('status');
            $startTime = $request->input('start_time');
            $endTime = $request->input('end_time');

            $result = $this->traceService->getTraceList($page, $limit, [
                'type' => $type,
                'merchant_id' => $merchantId,
                'status' => $status,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取链路列表失败', [
                'error' => $e->getMessage()
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
     * 清理过期数据
     * @param Request $request
     * @return Response
     */
    public function clean(Request $request): Response
    {
        try {
            $days = $request->input('days', 30);
            $result = $this->traceService->cleanExpiredTraces($days);

            return json([
                'code' => 200,
                'status' => true,
                'message' => '清理完成',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('清理过期数据失败', [
                'days' => $request->input('days'),
                'error' => $e->getMessage()
            ]);

            return json([
                'code' => 500,
                'status' => false,
                'message' => '清理失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
