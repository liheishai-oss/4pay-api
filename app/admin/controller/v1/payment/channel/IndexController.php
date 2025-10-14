<?php

namespace app\admin\controller\v1\payment\channel;

use app\service\payment\channel\IndexService;
use support\Request;
use support\Response;

class IndexController
{

    private IndexService $service;

    public function __construct(IndexService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): Response
    {
        $param = $request->all();
        $data = $this->service->getList($param)->toArray();
        return success($data);
    }
}





