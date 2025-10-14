<?php

namespace app\admin\controller\v1\merchant;

use app\service\merchant\EditService;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class EditController
{
    
    private EditService $editService;

    public function __construct(EditService $editService)
    {
        $this->editService = $editService;
    }

    /**
     * 更新商户
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        try {
            $id = $request->input('id');
            if (!$id) {
                return error('商户ID不能为空');
            }

            $data = $request->all();

            // 验证字段
            $validator = v::key('login_account', v::optional(v::notEmpty()->stringType()->length(1, 64)))
                ->key('merchant_name', v::optional(v::notEmpty()->stringType()->length(1, 100)))
                ->key('status', v::optional(v::intVal()->between(0, 1)))
                ->key('withdraw_fee', v::optional(v::intVal()->min(0)))
                ->key('withdraw_config_type', v::optional(v::intVal()->between(1, 3)))
                ->key('withdraw_rate', v::optional(v::intVal()->min(0)))
                ->key('whitelist_ips', v::optional(v::stringType()));

            $validator->assert($data);

            $merchant = $this->editService->updateMerchant($id, $data);

            return success([]);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}


