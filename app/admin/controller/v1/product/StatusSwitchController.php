<?php

namespace app\admin\controller\v1\product;

use app\exception\MyBusinessException;
use app\service\product\StatusSwitchService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Log;
use support\Request;
use support\Response;
use Throwable;

class StatusSwitchController
{
    // 临时允许无登录访问（测试用）
    protected array $noNeedLogin = ['*'];

    /**
     * 切换产品状态
     * @param Request $request
     * @return Response
     */
    public function toggle(Request $request): Response
    {
        $param = $request->all();
        
        echo "ProductStatusSwitchController received params: " . json_encode($param) . "\n";
        
        try {
            $data = Validator::input($param, [
                'id' => Validator::notEmpty()->intVal()->setName('产品ID'),
                'status' => Validator::intVal()->between(0, 1)->setName('状态'),
            ]);
            
            $service = new StatusSwitchService();
            echo "StatusSwitchController: 调用 toggleStatus - ID: {$data['id']}, Status: {$data['status']}\n";
            Log::info('StatusSwitchController: 调用 toggleStatus', ['id' => $data['id'], 'status' => $data['status']]);
            $result = $service->toggleStatus($data['id'], $data['status']);
            echo "StatusSwitchController: toggleStatus 完成\n";
            Log::info('StatusSwitchController: toggleStatus 完成');
            
            return success([], '状态切换成功');
        } catch (ValidationException $e) {
            throw new MyBusinessException($e->getMessage());
        } catch (Throwable $e) {
            throw new MyBusinessException($e->getMessage());
        }
    }
}

