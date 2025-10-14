<?php

namespace app\api\controller\v1\order;

use support\Request;
use support\Response;
use app\api\validator\v1\order\PreConditionValidator;
use app\api\service\v1\order\QueryService;

class QueryController
{
    protected PreConditionValidator $validator;
    protected QueryService $service;

    public function __construct(PreConditionValidator $validator, QueryService $service)
    {
        $this->validator = $validator;
        $this->service = $service;
    }

    /**
     * 需要IP白名单验证的方法
     */
    protected array $needIpWhitelist = ['query'];
    
    /**
     * 不需要登录验证的方法
     */
    protected array $noNeedLogin = ['query'];

    /**
     * 查询订单
     * 
     * 请求参数：
     * - merchant_key (string, 必填): 商户唯一标识
     * - order_no (string, 可选): 平台订单号（与merchant_order_no二选一）
     * - merchant_order_no (string, 可选): 商户订单号（与order_no二选一）
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
            
            // 调用服务层查询订单
            $result = $this->service->queryOrder($validatedData);
            
            return success($result, '查询成功');
            
        } catch (\Exception $e) {
            return error('查询失败: ' . $e->getMessage());
        }
    }
}
