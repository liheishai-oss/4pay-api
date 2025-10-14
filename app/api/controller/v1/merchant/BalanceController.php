<?php

namespace app\api\controller\v1\merchant;

use support\Request;
use support\Response;
use app\api\validator\v1\merchant\PreConditionValidator;
use app\api\service\v1\merchant\BalanceService;

class BalanceController
{
    protected array $noNeedLogin = ['*'];

    protected PreConditionValidator $validator;
    protected BalanceService $service;

    public function __construct(PreConditionValidator $validator, BalanceService $service)
    {
        $this->validator = $validator;
        $this->service = $service;
    }

    /**
     * 需要IP白名单验证的方法
     */
    protected array $needIpWhitelist = ['query'];

    /**
     * 查询余额
     * 
     * 请求参数：
     * - merchant_key (string, 必填): 商户唯一标识
     * - timestamp (int, 必填): 时间戳
     * - sign (string, 必填): 签名
     * 
     * @param Request $request
     * @return Response
     */
    public function query(Request $request): Response
    {
        try {
            // 前置验证 - 验证基本参数格式
            $validatedData = $this->validator->validate($request->all());
            
            // 调用服务层查询余额
            $result = $this->service->queryBalance($validatedData);
            
            return success($result, '查询成功');
            
        } catch (\Exception $e) {
            return error('查询失败: ' . $e->getMessage());
        }
    }
}
