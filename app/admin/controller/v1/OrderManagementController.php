<?php

namespace app\admin\controller\v1;

use app\admin\service\OrderManagementService;
use support\Request;
use support\Response;

class OrderManagementController
{
    // 临时允许所有操作无需登录（用于测试）
//    protected array $noNeedLogin = ['*'];

    protected $orderService;

    public function __construct(OrderManagementService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 获取订单列表
     */
    public function index(Request $request): Response
    {
        try {
            $page = $request->get('page', 1);
            $pageSize = $request->get('page_size', 10);
            $search = $request->get('search', '{}');
            $searchParams = json_decode($search, true) ?: [];
                
            // 处理前端传递的嵌套搜索参数格式
            // 如果搜索参数是嵌套在search对象中，则提取出来
            if (isset($searchParams['search']) && is_array($searchParams['search'])) {
                $searchParams = $searchParams['search'];
            }

            // 非管理员用户权限控制：只能看到自己的订单
            $adminId = $request->userData['admin_id'] ?? null;
            if ($adminId && $adminId != \app\common::ADMIN_USER_ID) {
                // 非超级管理员，需要限制只能看到自己的订单
                $merchantId = \app\model\Merchant::where('admin_id', $adminId)->value('id');

                if (!empty($merchantId)) {
                    // 强制设置商户ID，确保非管理员用户只能看到自己的订单
                    $searchParams['merchant_id'] = $merchantId;
                    \support\Log::info('非管理员用户订单查询', [
                        'admin_id' => $adminId,
                        'merchant_id' => $merchantId,
                        'is_merchant_admin' => $request->userData['is_merchant_admin'] ?? false
                    ]);
                }
            }

            // 记录搜索参数用于调试
            \support\Log::info('订单搜索参数', [
                'page' => $page,
                'page_size' => $pageSize,
                'search_params' => $searchParams,
                'is_merchant_admin' => $request->userData['is_merchant_admin'] ?? false
            ]);

            $result = $this->orderService->getOrderList($page, $pageSize, $searchParams);
            
            return success($result);
        } catch (\Exception $e) {
            \support\Log::error('订单列表查询失败', [
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
     * 获取订单详情
     */
    public function show(Request $request, $id): Response
    {
        try {
            $result = $this->orderService->getOrderDetail($id);
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 更新订单状态
     */
    public function update(Request $request): Response
    {
        try {
            $data = $request->post();
            $result = $this->orderService->updateOrderStatus($data);
            
            return json([
                'code' => 200,
                'msg' => '订单状态更新成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 关闭订单
     */
    public function close(Request $request): Response
    {
        try {
            $id = $request->post('id');
            $result = $this->orderService->closeOrder($id);
            
            return json([
                'code' => 200,
                'msg' => '订单已关闭',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }


    /**
     * 导出订单数据
     */
    public function export(Request $request): Response
    {
        try {
            $search = $request->post('search', '{}');
            $searchParams = json_decode($search, true) ?: [];
            
            $result = $this->orderService->exportOrderData($searchParams);
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取订单统计
     */
    public function statistics(Request $request): Response
    {
        try {
            // 直接获取URL参数，支持多种参数格式
            $searchParams = [];
            
            // 获取各个搜索参数
            if ($request->get('order_no')) {
                $searchParams['order_no'] = $request->get('order_no');
            }
            if ($request->get('merchant_order_no')) {
                $searchParams['merchant_order_no'] = $request->get('merchant_order_no');
            }
            if ($request->get('merchant_id')) {
                $searchParams['merchant_id'] = $request->get('merchant_id');
            }
            if ($request->get('status') !== null && $request->get('status') !== '') {
                $searchParams['status'] = $request->get('status');
            }
            if ($request->get('start_time')) {
                $searchParams['start_time'] = $request->get('start_time');
            }
            if ($request->get('end_time')) {
                $searchParams['end_time'] = $request->get('end_time');
            }
            
            // 兼容原有的JSON格式参数
            $search = $request->get('search', '{}');
            if ($search && $search !== '{}') {
                $jsonParams = json_decode($search, true);
                if ($jsonParams && is_array($jsonParams)) {
                    $searchParams = array_merge($searchParams, $jsonParams);
                }
            }
            
            // 非管理员用户权限控制：统计也只能看到自己的订单
            $adminId = $request->userData['admin_id'] ?? null;
            if ($adminId && $adminId != \app\common::ADMIN_USER_ID) {
                // 非超级管理员，需要限制只能统计自己的订单
                $merchantId = \app\model\Merchant::where('admin_id', $adminId)->value('id');
                if (!empty($merchantId)) {
                    // 强制设置商户ID，确保非管理员用户只能统计自己的订单
                    $searchParams['merchant_id'] = $merchantId;
                    \support\Log::info('非管理员用户订单统计', [
                        'admin_id' => $adminId,
                        'merchant_id' => $merchantId,
                        'is_merchant_admin' => $request->userData['is_merchant_admin'] ?? false
                    ]);
                }
            }
            
            $result = $this->orderService->getOrderStatistics($searchParams);
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 补单（支持单个和批量）
     */
    public function reissue(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            if (empty($ids) || !is_array($ids)) {
                return json([
                    'code' => 400,
                    'msg' => '订单ID列表不能为空',
                    'data' => null
                ]);
            }

            $result = $this->orderService->reissueOrders($ids);
            
            return json([
                'code' => 200,
                'msg' => '补单成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 回调（支持单个和批量）
     */
    public function callback(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            if (empty($ids) || !is_array($ids)) {
                return json([
                    'code' => 400,
                    'msg' => '订单ID列表不能为空',
                    'data' => null
                ]);
            }

            $result = $this->orderService->callbackOrders($ids);
            
            return json([
                'code' => 200,
                'msg' => '回调成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 查单
     */
    public function query(Request $request): Response
    {
        try {
            $orderNo = $request->post('order_no');
            if (!$orderNo) {
                return json([
                    'code' => 400,
                    'msg' => '订单号不能为空',
                    'data' => null
                ]);
            }

            $result = $this->orderService->queryOrder($orderNo);
            
            return json([
                'code' => 200,
                'msg' => '查单成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取订单流转日志
     */
    public function logs(Request $request): Response
    {
        try {
            $orderId = $request->get('order_id');
            if (!$orderId) {
                return json([
                    'code' => 400,
                    'msg' => '订单ID不能为空',
                    'data' => null
                ]);
            }

            $result = $this->orderService->getOrderLogs($orderId);
            
            return json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}


