<?php

namespace app\admin\controller\v1\supplier;

use app\service\supplier\SelectService;
use app\model\Supplier;
use support\Request;
use support\Response;
use support\Log;

/**
 * 供应商选择控制器
 * 用于支付通道添加/编辑时选择供应商
 */
class SelectController
{

    private SelectService $selectService;

    public function __construct()
    {
        $this->selectService = new SelectService();
    }

    /**
     * 获取所有供应商列表（包含禁用状态）
     * @param Request $request
     * @return Response
     */
    public function getAllSuppliers(Request $request): Response
    {
        try {
            // 参数验证和清理
            $params = $this->validateAndCleanParams($request->all(), [
                'keyword' => 'string',
                'status' => 'integer',
                'limit' => 'integer',
                'page' => 'integer',
                'sort_field' => 'string',
                'sort_order' => 'string'
            ]);

            // 设置默认值
            $params['limit'] = $params['limit'] ?? 50;
            $params['limit'] = min($params['limit'], 100); // 限制最大返回数量
            $params['page'] = $params['page'] ?? 1;

            // 状态验证
            if (isset($params['status']) && !in_array($params['status'], [Supplier::STATUS_DISABLED, Supplier::STATUS_ENABLED])) {
                return error('状态参数无效，只能为0（禁用）或1（启用）');
            }

            Log::info('获取所有供应商列表', [
                'params' => $params,
                'ip' => $request->getRealIp(),
                'user_agent' => $request->header('User-Agent')
            ]);

            $suppliers = $this->selectService->getAllSuppliers($params);


            return success($suppliers);

        } catch (\Exception $e) {
            Log::error('获取所有供应商列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);

            return error($e->getMessage());
        }
    }

    /**
     * 验证和清理参数
     * @param array $params
     * @param array $rules
     * @return array
     */
    private function validateAndCleanParams(array $params, array $rules): array
    {
        $cleaned = [];

        foreach ($rules as $field => $type) {
            if (!isset($params[$field])) {
                continue;
            }

            $value = $params[$field];

            switch ($type) {
                case 'string':
                    $cleaned[$field] = trim((string)$value);
                    break;
                case 'integer':
                    $cleaned[$field] = (int)$value;
                    break;
                case 'array':
                    $cleaned[$field] = is_array($value) ? $value : [];
                    break;
                default:
                    $cleaned[$field] = $value;
            }
        }

        return $cleaned;
    }
}