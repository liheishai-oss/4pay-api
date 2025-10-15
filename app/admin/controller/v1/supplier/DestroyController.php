<?php

namespace app\admin\controller\v1\supplier;

use app\exception\MyBusinessException;
use app\service\supplier\DestroyService;
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
     * 删除供应商
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids'); // 接收前端传来的 'ids' 字符串，例如 "1,2"

            if (empty($ids)) {
                return error('请选择要删除的数据');
            }

            // 将逗号分隔的ID字符串转换为数组
            if (is_string($ids)) {
                $ids = explode(',', $ids);
            }
            
            if (empty($ids) || !is_array($ids)) {
                throw new MyBusinessException('参数错误，缺少要删除的ID列表');
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);

            $result = $this->destroyService->deleteSuppliers($ids);
            
            if ($result) {
                return success([], '删除供应商成功');
            } else {
                return error('删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), (int)($e->getCode() ?: 400));
        }
    }

    /**
     * 批量删除供应商
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            $data = $request->all();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                return error('请选择要删除的数据');
            }
            
            // 批量删除
            $result = $this->destroyService->deleteSuppliers($data['ids']);
            
            if ($result) {
                $count = count($data['ids']);
                return success(['count' => $count], "批量删除成功，共删除{$count}条记录");
            } else {
                return error('删除失败', 500);
            }
        } catch (Throwable $e) {
            return error($e->getMessage(), (int)($e->getCode() ?: 400));
        }
    }
}




