<?php

namespace app\admin\controller\v1\supplier;

use app\service\supplier\DetailService;
use support\Request;
use support\Response;

class DetailController
{

    /**
     * 获取供应商详情
     * @param Request $request
     * @return Response
     */
    public function show(Request $request,int $id): Response
    {
        $service = new DetailService();
        $result = $service->getSupplierDetail($id);
        
        return success($result);
    }
}






