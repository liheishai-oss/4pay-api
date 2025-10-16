<?php

namespace app\admin\controller\v1\payment\channel;

use app\exception\MyBusinessException;
use app\service\payment\channel\DestroyService;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use support\Request;
use support\Response;
use Throwable;

class DestroyController
{

    private DestroyService $service;

    public function __construct(DestroyService $service)
    {
        $this->service = $service;
    }

    public function destroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids');

            if (empty($ids)) {
                return error('请选择要删除的数据');
            }

            if (!is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            $result = $this->service->batchDestroyChannels($ids);
            
            if ($result) {
                return success([], '删除通道成功');
            } else {
                return error('删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), (int)($e->getCode() ?: 400));
        }
    }

    public function batchDestroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids');

            if (empty($ids)) {
                return error('请选择要删除的数据');
            }

            if (!is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            $result = $this->service->batchDestroyChannels($ids);
            
            if ($result) {
                return success([], '删除通道成功');
            } else {
                return error('删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), (int)($e->getCode() ?: 400));
        }
    }
}





