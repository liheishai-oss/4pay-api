<?php

namespace app\admin\controller\v1\merchant;

use app\service\merchant\StoreService;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class StoreController
{

    private StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * 创建商户
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            $data = $request->all();

            // 验证必填字段
            $validator = v::key('login_account', v::notEmpty()->stringType()->length(1, 64))
                ->key('merchant_name', v::notEmpty()->stringType()->length(1, 100))
                ->key('status', v::optional(v::intVal()->between(0, 1)))
                ->key('withdraw_fee', v::optional(v::intVal()->min(0)))
                ->key('withdraw_config_type', v::optional(v::intVal()->between(1, 3)))
                ->key('withdraw_rate', v::optional(v::intVal()->min(0)))
                ->key('whitelist_ips', v::optional(v::stringType()));

            $validator->assert($data);

            $merchant = $this->storeService->createMerchant($data);

            return success([]);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}
