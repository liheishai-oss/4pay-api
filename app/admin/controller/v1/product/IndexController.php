<?php

namespace app\admin\controller\v1\product;

use app\service\product\IndexService;
use support\Request;
use support\Response;

class IndexController
{

    private IndexService $indexService;

    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;
    }

    /**
     * 获取产品列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            $params = $request->all();
            $result = $this->indexService->getProductList($params);

            return success($result->toArray());
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}

