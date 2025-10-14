<?php

namespace app\admin\controller\v1\supplier;

use app\exception\MyBusinessException;
use app\service\supplier\PrepayCheckService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class PrepayCheckController
{

    /**
     * 切换预付检验状态
     * @param Request $request
     * @return Response
     */
    public function check(Request $request): Response
    {
        $param = $request->all();
        
        try {
            $data = Validator::input($param, [
                'id' => Validator::notEmpty()->intVal()->setName('供应商ID'),
                'prepayment_check' => Validator::intVal()->between(0, 1)->setName('预付检验状态'),
            ]);
            
            $service = new PrepayCheckService();
            $result = $service->togglePrepayCheck($data['id'], $data['prepayment_check']);
            
            return success([], '预付检验状态切换成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException($e->getMessage());
        }
    }
}






