<?php

namespace app\admin\controller\v1\merchant;

use app\exception\MyBusinessException;
use app\service\merchant\DestroyService;
use support\Request;
use support\Response;
use Throwable;

class DestroyController
{

    public function __construct(
        private readonly DestroyService $destroyService
    ) {
    }

    /**
     * 删除单个商户
     */
    public function destroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids'); // 接收前端传来的 'ids' 数组

            if (empty($ids)) {
                return error('请选择要删除的数据');
            }

            if (!is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            $result = $this->destroyService->deleteMerchants($ids);
            
            if ($result) {
                return success([], '删除商户成功');
            } else {
                return error('删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 批量删除商户
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            $data = $request->all();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }
            
            // 批量删除
            $count = $this->destroyService->batchDeleteMerchants($data['ids']);
            
            return success(['count' => $count], "批量删除成功，共删除{$count}条记录");
        } catch (Throwable $e) {
            return error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}


