<?php

namespace app\api\validator\v1\order;

use app\exception\MyBusinessException;
use app\common\helpers\SignatureHelper;
use app\enums\MerchantStatus;
use app\model\Merchant;
use app\model\PaymentChannel;
use PhpParser\Node\Expr\Cast\Object_;

class CreateBusinessDataValidator
{
    /**
     * 验证订单创建业务数据
     * @param array $data
     * @param Merchant $merchant
     * @param array $channels 轮询池中的通道列表
     * @return void
     * @throws MyBusinessException
     */
    public function validate(array $data, Merchant $merchant, array $channels): void
    {
        // 验证商户状态
        if ($merchant->status !== MerchantStatus::ENABLED) {
            throw new MyBusinessException('商户已禁用');
        }

        // 验证是否有可用通道
        if (empty($channels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }

        // 验证订单金额是否在通道范围内（使用第一个通道作为参考）
        $firstChannel = $channels[0];
        $amountInCents = $data['order_amount_cents'];
        if ($amountInCents < $firstChannel['min_amount']) {
            throw new MyBusinessException('订单金额低于通道最小限额');
        }
        if ($amountInCents > $firstChannel['max_amount']) {
            throw new MyBusinessException('订单金额超过通道最大限额');
        }

        // 验证签名（debug模式下跳过）
        if (!isset($data['debug']) || $data['debug'] != '1') {
            $signatureHelper = new SignatureHelper();
            $isValid = $signatureHelper->verify($data, $merchant->merchant_secret);

            if (!$isValid) {
                throw new MyBusinessException('签名验证失败');
            }
        } else {
            // Debug模式下记录跳过签名验证的日志
            \support\Log::info('Debug模式：跳过签名验证', [
                'merchant_id' => $merchant->id,
                'merchant_key' => $merchant->merchant_key,
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * 简化版业务数据验证（用于企业级验证后的补充验证）
     * @param array $data
     * @param array $merchant
     * @return void
     * @throws MyBusinessException
     */
    public function validateBasicData(array $data, array $merchant): void
    {
        // 验证商户状态
        if ($merchant['status'] != MerchantStatus::ENABLED) {
            throw new MyBusinessException('商户已禁用');
        }

        // 验证必要字段
        $requiredFields = ['merchant_order_no', 'order_amount_cents', 'notify_url'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new MyBusinessException("缺少必要字段: {$field}");
            }
        }

        // 验证订单金额
        if (!is_numeric($data['order_amount_cents']) || $data['order_amount_cents'] <= 0) {
            throw new MyBusinessException('订单金额必须大于0');
        }

        // 验证商户订单号格式
        if (strlen($data['merchant_order_no']) > 64) {
            throw new MyBusinessException('商户订单号长度不能超过64个字符');
        }

        // 验证通知URL格式
        if (!filter_var($data['notify_url'], FILTER_VALIDATE_URL)) {
            throw new MyBusinessException('通知URL格式不正确');
        }

        // 验证签名（debug模式下跳过）
        \support\Log::info('签名验证调试', [
            'has_debug' => isset($data['debug']),
            'debug_value' => $data['debug'] ?? 'NOT_SET',
            'data_keys' => array_keys($data)
        ]);
        
        if (!isset($data['debug']) || $data['debug'] != '1') {
            $signatureHelper = new SignatureHelper();
            $isValid = $signatureHelper->verify($data, $merchant['merchant_secret']);

            if (!$isValid) {
                throw new MyBusinessException('签名验证失败');
            }
        } else {
            // Debug模式下记录跳过签名验证的日志
            \support\Log::info('Debug模式：跳过签名验证', [
                'merchant_id' => $merchant['id'],
                'merchant_key' => $merchant['merchant_key'],
                'data_keys' => array_keys($data)
            ]);
        }
    }
}
