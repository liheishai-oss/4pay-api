<?php

namespace app\admin\controller\v1\product;

use app\service\product\StoreService;
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
     * 创建产品
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            $data = $request->all();

            // 验证必填字段
            $validator = v::key('product_name', v::notEmpty()->stringType()->length(1, 128))
                ->key('status', v::optional(v::intVal()->between(0, 1)))
                ->key('sort', v::optional(v::intVal()->min(0)))
                ->key('default_rate_bp', v::optional(v::intVal()->min(0)))
                ->key('remark', v::optional(v::stringType()));

            $validator->assert($data);

            $product = $this->storeService->createProduct($data);

            return success([], '创建产品成功');
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
}
