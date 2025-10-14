<?php

namespace app\admin\controller\v1\supplier;

use app\exception\MyBusinessException;
use app\service\supplier\StoreService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class StoreController
{

    /**
     * 创建供应商
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $param = $request->all();
        
        try {
            $data = Validator::input($param, [
                'supplier_name'           => Validator::notEmpty()->setName('供应商名称'),
                'interface_code'          => Validator::notEmpty()->setName('接口代码'),
                'status'                  => Validator::optional(Validator::intVal()->between(0, 1))->setName('状态'),
                'prepayment_check'        => Validator::optional(Validator::intVal()->between(0, 1))->setName('预付检验'),
                'remark'                  => Validator::optional(Validator::stringType())->setName('备注'),
                'telegram_chat_id'        => Validator::optional(Validator::intVal())->setName('机器人ID'),
                'admin_id'                => Validator::optional(Validator::intVal()->min(1))->setName('管理员ID'),
                'telegram_chat_ids'       => Validator::optional(Validator::arrayType())->setName('管理员ID列表'),
                'callback_whitelist_ips'  => Validator::optional(Validator::arrayType())->setName('回调IP白名单'),
            ]);
            
            $service = new StoreService();
            $result = $service->createSupplier($data);
            
            return success([], '创建成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException($e->getMessage());
        }
    }
}
