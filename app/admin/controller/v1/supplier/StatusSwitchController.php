<?php

namespace app\admin\controller\v1\supplier;

use app\exception\MyBusinessException;
use app\service\supplier\StatusSwitchService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class StatusSwitchController
{

    /**
     * 切换供应商状态
     * @param Request $request
     * @return Response
     */
    public function toggle(Request $request): Response
    {
        $param = $request->all();
        
        // 添加调试日志
        error_log('StatusSwitchController received params: ' . json_encode($param));
        
        try {
            $data = Validator::input($param, [
                'id' => Validator::notEmpty()->intVal()->setName('供应商ID'),
                'status' => Validator::intVal()->between(0, 1)->setName('状态'),
            ]);
            
            $service = new StatusSwitchService();
            $result = $service->toggleStatus($data['id'], $data['status']);
            
            return success([], '状态切换成功');
        } catch (ValidationException $e) {
            error_log('StatusSwitchController validation error: ' . $e->getMessage());
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            error_log('StatusSwitchController error: ' . $e->getMessage());
            throw new MyBusinessException($e->getMessage());
        }
    }
}






