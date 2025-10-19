<?php

namespace app\api\service\v1\merchant;

use app\api\repository\v1\merchant\MerchantRepository;
use app\api\validator\v1\merchant\BusinessDataValidator;
use app\enums\MerchantStatus;
use app\exception\MyBusinessException;
use app\common\helpers\MoneyHelper;
use app\common\helpers\TraceIdHelper;

class BalanceService
{
    protected MerchantRepository $repository;
    protected BusinessDataValidator $validator;

    public function __construct(MerchantRepository $repository, BusinessDataValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    /**
     * 查询商户余额
     * @param array $data
     * @return array
     * @throws MyBusinessException
     */
    public function queryBalance(array $data): array
    {
        try {
        $merchantKey = $data['merchant_key'];
        
        // 通过数据仓库获取商户信息
        $merchant = $this->repository->getMerchantByKey($merchantKey);
        if (!$merchant) {
            throw new MyBusinessException('商户不存在4');
        }

        // 数据验证 - 验证业务数据
        $this->validator->validate($data, $merchant);

        // 计算可用余额（转换为元）
        $availableBalance = $merchant->withdrawable_amount - $merchant->frozen_amount;
        $balanceInYuan = MoneyHelper::convertToYuan($availableBalance);

        // 生成追踪ID
        $traceId = TraceIdHelper::get();

        // 准备返回数据
        $responseData = [
            'merchant_key' => $merchant->merchant_key,
            'balance'      => $balanceInYuan,
            'trace_id'     => $traceId
        ];

        // 商户余额查询返回结果不需要签名

        return $responseData;
        
        } catch (\Exception $e) {
            if (config('app.debug', false)) {
                $trace = $e->getTraceAsString();
                $file = $e->getFile();
                $line = $e->getLine();
                throw new MyBusinessException("Debug错误: {$file}:{$line} - {$e->getMessage()}\nTrace: {$trace}");
            }
            throw $e;
        }
    }
}
