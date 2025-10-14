<?php

namespace app\admin\controller\v1\payment\channel;

use app\exception\MyBusinessException;
use app\service\payment\channel\DetailService;
use support\Request;
use support\Response;

class DetailController
{
    private DetailService $service;

    public function __construct(DetailService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, int $id): Response
    {
        if (empty($id)) {
            throw new MyBusinessException('ID不能为空');
        }
        $channel = $this->service->getDetail($id);
        return success($channel);
    }
}





