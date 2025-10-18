<?php

namespace app\api\controller\v1\order;

use support\Request;
use support\Response;
use app\api\validator\v1\order\CreatePreConditionValidator;
use app\api\service\v1\order\CreateService;

class CreateController
{
    protected CreatePreConditionValidator $validator;
    protected CreateService $service;

    public function __construct(CreatePreConditionValidator $validator, CreateService $service)
    {
        $this->validator = $validator;
        $this->service = $service;
    }

    /**
     * 需要IP白名单验证的方法
     */
    protected array $needIpWhitelist = ['create'];
    
    /**
     * 不需要登录验证的方法
     */
    protected array $noNeedLogin = ['create'];

    /**
     * 创建订单
     * 
     * 请求参数：
     * - merchant_key (string, 必填): 商户唯一标识
     * - merchant_order_no (string, 必填): 商户订单号（6-64位，字母、数字或下划线，建议ORDER_或TEST_开头）
     * - order_amount (string, 必填): 订单金额（单位：元，支持整数或两位小数）
     * - product_code (string, 必填): 产品代码
     * - notify_url (string, 必填): 异步通知地址
     * - sign (string, 必填): 签名
     * - return_url (string, 可选): 同步跳转地址
     * - terminal_ip (string, 必填): 终端IP地址
     * - extra_data (string, 可选): 扩展数据，JSON格式
     * 
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // 开始性能分析
        $profileStart = microtime(true);
        $profileMemory = memory_get_usage();
        
        try {
            // 记录请求开始
            \support\Log::info('商户订单创建开始', [
                'merchant_key' => $request->input('merchant_key', ''),
                'merchant_order_no' => $request->input('merchant_order_no', ''),
                'order_amount' => $request->input('order_amount', ''),
                'product_code' => $request->input('product_code', ''),
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // 前置验证 - 验证基本参数格式
            $validationStart = microtime(true);
            $validatedData = $this->validator->validate($request->all());
            $validationTime = round((microtime(true) - $validationStart) * 1000, 2);
            
            \support\Log::info('参数验证完成', [
                'validation_time_ms' => $validationTime,
                'validated_fields' => array_keys($validatedData)
            ]);

            // 调用服务层创建订单
            $serviceStart = microtime(true);
            $result = $this->service->createOrder($validatedData);
            $serviceTime = round((microtime(true) - $serviceStart) * 1000, 2);
            
            $totalTime = round((microtime(true) - $profileStart) * 1000, 2);
            $totalMemory = memory_get_usage() - $profileMemory;
            
            // 记录性能数据
            \support\Log::info('商户订单创建完成', [
                'order_no' => $result['order_no'] ?? '',
                'total_time_ms' => $totalTime,
                'validation_time_ms' => $validationTime,
                'service_time_ms' => $serviceTime,
                'memory_usage_mb' => round($totalMemory / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
            ]);

            // 根据服务层返回的结果决定响应格式
            if (isset($result['status']) && $result['status'] === false) {
                // 服务层返回失败，直接返回失败响应
                return error($result['message'], $result['code'] ?? 400, $result['data'] ?? []);
            } else {
                // 服务层已经返回了完整的响应格式，直接返回JSON
                return json($result);
            }
            
        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $profileStart) * 1000, 2);
            $totalMemory = memory_get_usage() - $profileMemory;
            
            // 记录错误性能数据
            \support\Log::error('商户订单创建失败', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'total_time_ms' => $totalTime,
                'memory_usage_mb' => round($totalMemory / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
            ]);
            
            return error('订单创建失败: ' . $e->getMessage());
        }
    }
}