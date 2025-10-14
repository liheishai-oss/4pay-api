<?php

namespace app\admin\controller\v1\merchant;

use app\service\merchant\StatusSwitchService;
use support\Request;
use support\Response;

class StatusSwitchController
{

    private StatusSwitchService $statusSwitchService;

    public function __construct(StatusSwitchService $statusSwitchService)
    {
        $this->statusSwitchService = $statusSwitchService;
    }

    /**
     * 切换商户状态
     * @param Request $request
     * @return Response
     */
    public function switch(Request $request): Response
    {
        try {
            $id = $request->input('id');
            if (!$id) {
                return error('商户ID不能为空');
            }

            $merchant = $this->statusSwitchService->switchStatus($id);

            return success([]);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}


