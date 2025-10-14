<?php

namespace app\admin\controller\v1\product;

use app\service\product\EditService;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class EditController
{
    protected array $noNeedLogin = ['*'];

    private EditService $editService;

    public function __construct(EditService $editService)
    {
        $this->editService = $editService;
    }

    /**
     * 更新产品
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        try {
            $id = $request->input('id');
            if (!$id) {
                return error('产品ID不能为空');
            }

            $data = $request->all();

            // 验证字段（禁止编辑对接编号）
            $validator = v::key('product_name', v::optional(v::notEmpty()->stringType()->length(1, 128)))
                ->key('status', v::optional(v::intVal()->between(0, 1)))
                ->key('sort', v::optional(v::intVal()->min(0)))
                ->key('default_rate_bp', v::optional(v::intVal()->min(0)))
                ->key('today_success_rate_bp', v::optional(v::intVal()->between(0, 10000)))
                ->key('remark', v::optional(v::stringType()));

            // 移除对接编号字段，禁止编辑
            unset($data['external_code']);

            $validator->assert($data);

            $product = $this->editService->updateProduct($id, $data);

            return success([], '更新产品成功');
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}

