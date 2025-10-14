<?php

namespace app\api\validator\v1\merchant;

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
        $validator = v::key('merchant_key', v::stringType()->notEmpty())
            ->key('timestamp', v::intVal()->positive())
            ->key('sign', v::stringType()->notEmpty());

        if (!$validator->validate($data)) {
            throw new MyBusinessException('参数格式错误');
        }

        // 验证时间戳（5分钟内有效）
        if (abs(time() - $data['timestamp']) > 300) {
            throw new MyBusinessException('请求已过期');
        }

        return [
            'merchant_key' => $data['merchant_key'],
            'timestamp' => (int) $data['timestamp'],
            'sign' => $data['sign']
        ];
    }
}

