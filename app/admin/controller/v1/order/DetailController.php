<?php

namespace app\admin\controller\v1\order;

use app\service\order\DetailService;
use support\Request;
use support\Response;

class DetailController
{

    private DetailService $detailService;

    public function __construct(DetailService $detailService)
    {
        $this->detailService = $detailService;
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        try {
            if (!$id) {
                return error('订单ID不能为空');
            }

            $order = $this->detailService->getOrderDetail($id);

            return success($order);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}
