<?php

namespace app\admin\controller\v1\payment\channel;

use app\exception\MyBusinessException;
use app\service\payment\channel\StoreService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class StoreController
{

    private StoreService $service;

    public function __construct(StoreService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request): Response
    {
        $param = $request->all();

        try {
            $data = Validator::input($param, [
                'channel_name' => Validator::notEmpty()->setName('通道名称'),
                'supplier_id' => Validator::notEmpty()->intVal()->min(1)->setName('供应商ID'),
                'product_code' => Validator::optional(Validator::stringType())->setName('产品编码'),
                'status' => Validator::optional(Validator::intVal()->between(0, 1))->setName('状态'),
                'weight' => Validator::optional(Validator::intVal()->min(0))->setName('权重'),
                'min_amount' => Validator::optional(Validator::intVal()->min(0))->setName('最小支付金额'),
                'max_amount' => Validator::optional(Validator::intVal()->min(0))->setName('最大支付金额'),
                'cost_rate' => Validator::optional(Validator::intVal()->min(0))->setName('成本费率'),
                'remark' => Validator::optional(Validator::stringType())->setName('备注'),
                'basic_params' => Validator::optional(Validator::arrayType())->setName('基础参数'),
            ]);

            $service = new StoreService();
            $result = $service->createChannel($data);

            return success([], '创建成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException('创建支付通道失败：' . $e->getMessage());
        }
    }
}





