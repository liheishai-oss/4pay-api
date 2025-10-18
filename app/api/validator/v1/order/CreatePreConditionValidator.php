<?php

namespace app\api\validator\v1\order;

use Respect\Validation\Validator as v;
use app\exception\MyBusinessException;
use app\common\helpers\MoneyHelper;

class CreatePreConditionValidator
{
    /**
     * 前置验证 - 验证基本参数格式
     * @param array $data
     * @return array
     * @throws MyBusinessException
     */
    public function validate(array $data): array
    {
        // 是否启用签名校验（默认启用）
        $signatureEnabled = config('security.signature.enabled', true);

        // 验证必填参数
        $requiredFields = ['merchant_key', 'merchant_order_no', 'order_amount', 'product_code', 'notify_url', 'terminal_ip'];
        if ($signatureEnabled) {
            $requiredFields[] = 'sign';
        }
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new MyBusinessException("缺少必要参数: {$field}");
            }
        }

        $validator = v::key('merchant_key', v::stringType()->notEmpty());
        if ($signatureEnabled) {
            $validator = $validator->key('sign', v::stringType()->notEmpty());
        }

        if (!$validator->validate($data)) {
            throw new MyBusinessException('参数格式错误');
        }

        // 验证可选参数 - 逐个验证存在的字段
        $optionalFields = [
            'return_url' => v::stringType(),
            'extra_data' => v::stringType()
        ];

        foreach ($optionalFields as $field => $validator) {
            if (isset($data[$field]) && $data[$field] !== '' && !$validator->validate($data[$field])) {
                throw new MyBusinessException("可选参数格式错误: {$field}");
            }
        }

        // 验证扩展数据JSON格式
        if (isset($data['extra_data']) && !empty($data['extra_data'])) {
            $decoded = json_decode($data['extra_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new MyBusinessException('扩展数据必须是有效的JSON格式');
            }
        }

        // 验证金额范围（最小0.01元，最大999999.99元）
        if (!is_numeric($data['order_amount'])) {
            throw new MyBusinessException('订单金额必须是有效的数字');
        }
        $amount = (float) $data['order_amount'];
        if ($amount < 0.01 || $amount > 999999.99) {
            throw new MyBusinessException('订单金额必须在0.01-999999.99元之间');
        }

        // 转换金额为分（用于数据库存储）
        $amountInCents = MoneyHelper::convertToCents($data['order_amount']);

        // 验证产品代码
        if (empty($data['product_code'])) {
            throw new MyBusinessException('产品代码不能为空');
        }

        // 验证通知URL格式
        if (!filter_var($data['notify_url'], FILTER_VALIDATE_URL)) {
            throw new MyBusinessException('通知URL格式不正确，请输入有效的URL地址');
        }

        // 验证终端IP格式
        if (!filter_var($data['terminal_ip'], FILTER_VALIDATE_IP)) {
            throw new MyBusinessException('终端IP格式不正确，请输入有效的IP地址');
        }

        // 验证订单号格式（支持系统规则：ORDER_、TEST_、或自定义格式）
        $merchantOrderNo = $data['merchant_order_no'];
        if (strlen($merchantOrderNo) > 64) {
            throw new MyBusinessException('商户订单号长度不能超过64个字符');
        }
        if (!preg_match('/^([A-Z0-9_]+)/', $merchantOrderNo)) {
            throw new MyBusinessException('商户订单号格式错误，只能包含大写字母、数字和下划线');
        }

        // 验证时间戳（5分钟内有效）
        if (isset($data['timestamp']) && abs(time() - $data['timestamp']) > 300) {
            throw new MyBusinessException('请求已过期');
        }

        // 返回验证后的数据
        $result = [
            'merchant_key' => $data['merchant_key'],
            'merchant_order_no' => $data['merchant_order_no'],
            'order_amount' => $data['order_amount'],
            'order_amount_cents' => $amountInCents, // 添加分单位金额
            'product_code' => $data['product_code'],
            'notify_url' => $data['notify_url'],
            'terminal_ip' => $data['terminal_ip'],
        ];
        if ($signatureEnabled && isset($data['sign'])) {
            $result['sign'] = $data['sign'];
        }

        // 添加可选参数
        if (isset($data['return_url'])) {
            $result['return_url'] = $data['return_url'];
        }
        if (isset($data['extra_data'])) {
            $result['extra_data'] = $data['extra_data'];
        }
        if (isset($data['debug'])) {
            $result['debug'] = $data['debug'];
        }

        return $result;
    }
}
