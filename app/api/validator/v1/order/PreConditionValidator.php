<?php

namespace app\api\validator\v1\order;

use Respect\Validation\Validator as v;
use app\exception\MyBusinessException;

class PreConditionValidator
{
    /**
     * 前置验证 - 验证基本参数格式
     * @param array $data
     * @return array
     * @throws MyBusinessException
     */
    public function validate(array $data): array
    {
        // 验证必填参数
        $validator = v::key('merchant_key', v::stringType()->notEmpty())
            ->key('timestamp', v::intVal()->positive())
            ->key('sign', v::stringType()->notEmpty());

        if (!$validator->validate($data)) {
            throw new MyBusinessException('参数格式错误');
        }

        // 验证订单号参数（二选一）
        if (!isset($data['order_no']) && !isset($data['merchant_order_no'])) {
            throw new MyBusinessException('订单号参数缺失，请提供order_no或merchant_order_no');
        }

        if (isset($data['order_no']) && isset($data['merchant_order_no'])) {
            throw new MyBusinessException('订单号参数冲突，请只提供order_no或merchant_order_no其中一个');
        }

        // 验证时间戳（5分钟内有效）
        if (abs(time() - $data['timestamp']) > 300) {
            throw new MyBusinessException('请求已过期');
        }

        $result = [
            'merchant_key' => $data['merchant_key'],
            'timestamp' => (int) $data['timestamp'],
            'sign' => $data['sign']
        ];

        // 添加订单号参数
        if (isset($data['order_no'])) {
            $result['order_no'] = $data['order_no'];
            $result['query_type'] = 'platform';
        } else {
            $result['merchant_order_no'] = $data['merchant_order_no'];
            $result['query_type'] = 'merchant';
        }

        return $result;
    }
}
