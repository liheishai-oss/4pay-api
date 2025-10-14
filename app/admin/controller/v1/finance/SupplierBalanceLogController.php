<?php

namespace app\admin\controller\v1\finance;

use app\admin\service\finance\SupplierBalanceLogService;
use support\Request;
use support\Response;

/**
 * 供应商余额变动记录控制器
 */
class SupplierBalanceLogController
{
    protected $service;

    public function __construct()
    {
        $this->service = new SupplierBalanceLogService();
    }

    /**
     * 获取余额变动记录列表
     */
    public function index(Request $request): Response
    {
        try {
            $params = $request->all();
            $result = $this->service->getList($params);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败：' . $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * 获取余额变动记录详情
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $result = $this->service->getDetail($id);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败：' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取统计信息
     */
    public function statistics(Request $request): Response
    {
        try {
            $params = $request->all();
            $result = $this->service->getStatistics($params);
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败：' . $e->getMessage(),
                'data' => []
            ]);
        }
    }
}

