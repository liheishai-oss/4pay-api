<?php

namespace app\admin\controller\v1\supplier;

use app\exception\MyBusinessException;
use app\service\supplier\EditService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class EditController
{
    protected array $noNeedLogin = ['*'];

    /**
     * 更新供应商
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        $param = $request->all();
        
        try {
            $data = Validator::input($param, [
                'id' => Validator::notEmpty()->intVal()->setName('供应商ID'),
                'supplier_name' => Validator::optional(Validator::notEmpty())->setName('供应商名称'),
                'interface_code' => Validator::optional(Validator::notEmpty())->setName('接口代码'),
                'status' => Validator::optional(Validator::intVal()->between(0, 1))->setName('状态'),
                'prepayment_check' => Validator::optional(Validator::intVal()->between(0, 1))->setName('预付检验'),
                'remark' => Validator::optional(Validator::stringType())->setName('备注'),
                'telegram_chat_id' => Validator::optional(Validator::intVal())->setName('机器人ID'),
                'callback_whitelist_ips' => Validator::optional(Validator::arrayType())->setName('回调IP白名单'),
            ]);
            
            $service = new EditService();
            $result = $service->updateSupplier($data['id'], $data);
            
            return success([], '更新成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException($e->getMessage());
        }
    }
}
