<?php

namespace app\admin\controller\v1\payment\channel;

use app\service\payment\channel\TestService;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

/**
 * 支付通道测试控制器
 */
class TestController
{

    private TestService $testService;

    public function __construct(TestService $testService)
    {
        $this->testService = $testService;
    }

    /**
     * 测试单个支付通道
     * @param Request $request
     * @return Response
     */
    public function testChannel(Request $request): Response
    {
        try {
            $channelId = $request->input('channel_id');
            $allParams = $request->all();

            if (!$channelId) {
                return error('通道ID不能为空');
            }

            // 从完整参数中提取测试参数（移除product_code，改为从通道获取）
            $testParams = [
                'payment_amount' => $allParams['payment_amount'] ?? null,
                'openid' => $allParams['openid'] ?? null,
                'timeout_express' => $allParams['timeout_express'] ?? null,
            ];

            // 只验证必要的参数
            if (isset($testParams['payment_amount'])) {
                $validator = v::key('payment_amount', v::floatVal()->min(0.01));
                $validator->assert($testParams);
            }

            $result = $this->testService->testChannel($channelId, $testParams);

            // 返回支付通道的原始响应，包装在data字段中
            return success($result, $result['message'] ?? '测试完成');

        } catch (\Exception $e) {
            return error('测试失败: ' . $e->getMessage());
        }
    }


}