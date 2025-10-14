<?php

namespace app\admin\controller\v1\merchant;

use app\service\merchant\DetailService;
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
     * 获取商户详情
     * @param Request $request
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        try {
            if (!$id) {
                return error('商户ID不能为空');
            }

            $merchant = $this->detailService->getMerchantDetail($id);

            return success($merchant);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}


