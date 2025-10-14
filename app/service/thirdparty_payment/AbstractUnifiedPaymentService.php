<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 统一支付服务抽象基类
 * 提供统一的状态封装和响应格式
 */
abstract class AbstractUnifiedPaymentService extends AbstractPaymentService
{
    /**
     * 统一响应状态封装
     * 将不同供应商的响应格式统一为 status=true/false 的标准格式
     * 
     * @param array $response 原始响应数据
     * @return array 统一格式的响应数据
     */
    protected function unifyResponseStatus(array $response): array
    {
        // 子类需要实现具体的成功判断逻辑
        $isSuccess = $this->isResponseSuccess($response);
        
        return [
            'status' => $isSuccess,  // 统一使用 status 字段：true=成功，false=失败
            'code' => $this->extractResponseCode($response),
            'message' => $this->extractResponseMessage($response, $isSuccess),
            'data' => $this->extractResponseData($response),
            'original_response' => $response  // 保留原始响应用于调试
        ];
    }

    /**
     * 判断响应是否成功
     * 子类必须实现此方法，定义具体的成功判断逻辑
     * 
     * @param array $response 原始响应数据
     * @return bool true=成功，false=失败
     */
    abstract protected function isResponseSuccess(array $response): bool;

    /**
     * 提取响应代码
     * 子类可以重写此方法来自定义代码提取逻辑
     * 
     * @param array $response 原始响应数据
     * @return int 响应代码
     */
    protected function extractResponseCode(array $response): int
    {
        return $response['code'] ?? 0;
    }

    /**
     * 提取响应消息
     * 子类可以重写此方法来自定义消息提取逻辑
     * 
     * @param array $response 原始响应数据
     * @param bool $isSuccess 是否成功
     * @return string 响应消息
     */
    protected function extractResponseMessage(array $response, bool $isSuccess): string
    {
        return $response['message'] ?? ($isSuccess ? '请求成功' : '请求失败');
    }

    /**
     * 提取响应数据
     * 子类可以重写此方法来自定义数据提取逻辑
     * 
     * @param array $response 原始响应数据
     * @return array 响应数据
     */
    protected function extractResponseData(array $response): array
    {
        return $response['data'] ?? [];
    }

    /**
     * 处理统一格式的响应
     * 根据统一格式的响应创建 PaymentResult
     * 
     * @param array $unifiedResponse 统一格式的响应
     * @param array $params 请求参数
     * @param array $headerInfo 请求头信息
     * @return PaymentResult
     */
    protected function processUnifiedResponse(array $unifiedResponse, array $params, array $headerInfo): PaymentResult
    {
        if ($unifiedResponse['status'] === true) {
            return $this->createSuccessResult($unifiedResponse, $params, $headerInfo);
        } else {
            return $this->createFailedResult($unifiedResponse, $params, $headerInfo);
        }
    }

    /**
     * 创建成功结果
     * 子类可以重写此方法来自定义成功结果的数据结构
     * 
     * @param array $unifiedResponse 统一格式的响应
     * @param array $params 请求参数
     * @param array $headerInfo 请求头信息
     * @return PaymentResult
     */
    protected function createSuccessResult(array $unifiedResponse, array $params, array $headerInfo): PaymentResult
    {
        $result = PaymentResult::success(
            '支付请求成功',
            $this->buildSuccessData($unifiedResponse),
            $params['order_no'] ?? '',
            $unifiedResponse['data']['order_id'] ?? '',
            (float)($params['total_amount'] ?? 0),
            'CNY',
            $unifiedResponse
        );
        
        $result->setDebugInfo($headerInfo);
        return $result;
    }

    /**
     * 创建失败结果
     * 子类可以重写此方法来自定义失败结果的处理
     * 
     * @param array $unifiedResponse 统一格式的响应
     * @param array $params 请求参数
     * @param array $headerInfo 请求头信息
     * @return PaymentResult
     */
    protected function createFailedResult(array $unifiedResponse, array $params, array $headerInfo): PaymentResult
    {
        $result = PaymentResult::failed(
            $unifiedResponse['message'] ?? '支付请求失败',
            $unifiedResponse,
            $params['order_no'] ?? '',
            $unifiedResponse
        );
        
        $result->setDebugInfo($headerInfo);
        return $result;
    }

    /**
     * 构建成功时的数据
     * 子类可以重写此方法来自定义成功数据的结构
     * 
     * @param array $unifiedResponse 统一格式的响应
     * @return array 成功数据
     */
    protected function buildSuccessData(array $unifiedResponse): array
    {
        $payUrl = $unifiedResponse['data']['pay_url'] ?? '';
        $qrCode = $unifiedResponse['data']['qr_code'] ?? '';
        
        // 如果pay_url为空但有qr_code，则使用qr_code作为payment_url
        if (empty($payUrl) && !empty($qrCode)) {
            $payUrl = $qrCode;
        }
        
        return [
            'payment_url' => $payUrl,
            'qr_code' => $qrCode,
            'order_id' => $unifiedResponse['data']['order_id'] ?? '',
            'expire_time' => $unifiedResponse['data']['expire_time'] ?? '',
            'form_data' => $unifiedResponse['data']['form_data'] ?? [],
            'redirect_url' => $unifiedResponse['data']['redirect_url'] ?? ''
        ];
    }
}
