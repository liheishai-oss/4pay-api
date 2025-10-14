<?php

namespace app\admin\controller\v1\supplier;

use app\service\supplier\TelegramAdminListService;
use support\Request;
use support\Response;

class TelegramAdminListController
{

    /**
     * 获取启用的Telegram管理员列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $service = new TelegramAdminListService();
        $result = $service->getEnabledAdminList();
        
        return success($result);
    }
}






