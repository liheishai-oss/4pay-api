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
            $force = $request->post('force', false); // 是否强制删除

            if (empty($ids)) {
                return error('请选择要删除的数据');
            }

            if (!is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            // 使用安全删除方法
            $result = $this->destroyService->safeDeleteMerchants($ids, $force);
            
            if ($result) {
                $message = $force ? '强制删除商户成功（已删除关联数据）' : '删除商户成功';
                return success([], $message);
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
            $force = $data['force'] ?? false; // 是否强制删除
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }
            
            // 使用安全删除方法
            $result = $this->destroyService->safeDeleteMerchants($data['ids'], $force);
            
            if ($result) {
                $message = $force ? "强制批量删除成功，共删除" . count($data['ids']) . "条记录（已删除关联数据）" : "批量删除成功，共删除" . count($data['ids']) . "条记录";
                return success(['count' => count($data['ids'])], $message);
            } else {
                return error('批量删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}


