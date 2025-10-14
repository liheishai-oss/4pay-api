<?php

namespace app\admin\controller\v1\supplier;

use app\service\supplier\IndexService;
use support\Request;
use support\Response;

class IndexController
{

    /**
     * 获取供应商列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $params = $request->all();
        $service = new IndexService();
        $result = $service->getSupplierList($params);
        
        return success($result);
    }
}






