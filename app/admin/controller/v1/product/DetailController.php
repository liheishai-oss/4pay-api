<?php

namespace app\admin\controller\v1\product;

use app\service\product\DetailService;
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
     * 获取产品详情
     * @param Request $request
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        try {
            // 路由为 /product/detail/{id}，直接使用路径参数 $id
            if (empty($id)) return error('产品ID不能为空');

            $result = $this->detailService->getProductDetail($id);

            return success($result);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}

