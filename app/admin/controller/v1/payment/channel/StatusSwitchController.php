<?php

namespace app\admin\controller\v1\payment\channel;

use app\exception\MyBusinessException;
use app\service\payment\channel\StatusSwitchService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class StatusSwitchController
{

    private StatusSwitchService $service;

    public function __construct(StatusSwitchService $service)
    {
        $this->service = $service;
    }

    public function toggle(Request $request): Response
    {
        $param = $request->all();
        try {
            $data = Validator::input($param, [
                'id' => Validator::notEmpty()->intVal()->setName('支付通道ID'),
            ]);
            $channel = $this->service->toggleStatus($data['id']);
            return success($channel->toArray(), '状态切换成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException('状态切换失败：' . $e->getMessage());
        }
    }
}





