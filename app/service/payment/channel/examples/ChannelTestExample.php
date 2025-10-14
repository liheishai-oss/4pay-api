<?php

namespace app\service\payment\channel\examples;

use app\service\payment\channel\TestService;

/**
 * 支付通道测试示例
 */
class ChannelTestExample
{
    private TestService $testService;

    public function __construct()
    {
        $this->testService = new TestService();
    }

    /**
     * 测试GEM支付通道
     * @param int $channelId
     * @return array
     */
    public function testGemChannel(int $channelId): array
    {
        $testParams = [
            'payment_amount' => '0.01',
            'product_code' => '222', // 微信支付
        ];

        return $this->testService->testChannel($channelId, $testParams);
    }

    /**
     * 测试支付宝通道
     * @param int $channelId
     * @return array
     */
    public function testAlipayChannel(int $channelId): array
    {
        $testParams = [
            'payment_amount' => '0.01',
        ];

        return $this->testService->testChannel($channelId, $testParams);
    }

    /**
     * 测试微信支付通道
     * @param int $channelId
     * @return array
     */
    public function testWechatChannel(int $channelId): array
    {
        $testParams = [
            'payment_amount' => '0.01',
            'openid' => 'test_openid_123456',
        ];

        return $this->testService->testChannel($channelId, $testParams);
    }

    /**
     * 运行所有测试示例
     * @return array
     */
    public function runAllExamples(): array
    {
        $results = [];

        // 模拟通道数据
        $channels = [
            [
                'id' => 1,
                'channel_name' => 'GEM支付-微信',
                'interface_code' => 'GemPayment',
                'supplier_id' => 1,
                'status' => 1,
                'min_amount' => 100,
                'max_amount' => 2000000,
            ],
            [
                'id' => 2,
                'channel_name' => '支付宝网页支付',
                'interface_code' => 'AlipayWeb',
                'supplier_id' => 2,
                'status' => 1,
                'min_amount' => 100,
                'max_amount' => 5000000,
            ],
            [
                'id' => 3,
                'channel_name' => '微信JSAPI支付',
                'interface_code' => 'WechatJsapi',
                'supplier_id' => 3,
                'status' => 1,
                'min_amount' => 100,
                'max_amount' => 3000000,
            ]
        ];

        foreach ($channels as $channel) {
            $channelId = $channel['id'];
            $interfaceCode = $channel['interface_code'];

            echo "测试通道: {$channel['channel_name']} (ID: {$channelId})\n";

            try {
                switch ($interfaceCode) {
                    case 'GemPayment':
                        $result = $this->testGemChannel($channelId);
                        break;
                    case 'AlipayWeb':
                        $result = $this->testAlipayChannel($channelId);
                        break;
                    case 'WechatJsapi':
                        $result = $this->testWechatChannel($channelId);
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => "不支持的接口代码: {$interfaceCode}",
                            'data' => []
                        ];
                }

                $results[] = [
                    'channel_id' => $channelId,
                    'channel_name' => $channel['channel_name'],
                    'interface_code' => $interfaceCode,
                    'result' => $result
                ];

                echo "结果: " . ($result['success'] ? '成功' : '失败') . "\n";
                echo "消息: " . $result['message'] . "\n\n";

            } catch (\Exception $e) {
                $results[] = [
                    'channel_id' => $channelId,
                    'channel_name' => $channel['channel_name'],
                    'interface_code' => $interfaceCode,
                    'result' => [
                        'success' => false,
                        'message' => '测试异常: ' . $e->getMessage(),
                        'data' => []
                    ]
                ];

                echo "异常: " . $e->getMessage() . "\n\n";
            }
        }

        return $results;
    }

    /**
     * 生成测试报告
     * @param array $results
     * @return string
     */
    public function generateTestReport(array $results): string
    {
        $total = count($results);
        $success = count(array_filter($results, function($item) {
            return $item['result']['success'] ?? false;
        }));
        $failed = $total - $success;

        $report = "=== 支付通道测试报告 ===\n\n";
        $report .= "测试时间: " . date('Y-m-d H:i:s') . "\n";
        $report .= "总通道数: {$total}\n";
        $report .= "成功: {$success}\n";
        $report .= "失败: {$failed}\n";
        $report .= "成功率: " . round(($success / $total) * 100, 2) . "%\n\n";

        $report .= "=== 详细结果 ===\n";
        foreach ($results as $item) {
            $status = $item['result']['success'] ? '✓' : '✗';
            $report .= "{$status} {$item['channel_name']} ({$item['interface_code']})\n";
            $report .= "   消息: {$item['result']['message']}\n";
            
            if (isset($item['result']['data']['order_no'])) {
                $report .= "   订单号: {$item['result']['data']['order_no']}\n";
            }
            
            $report .= "\n";
        }

        return $report;
    }

    /**
     * 测试特定通道配置
     * @param array $channelConfig
     * @return array
     */
    public function testChannelConfig(array $channelConfig): array
    {
        $channelId = $channelConfig['id'] ?? 0;
        $interfaceCode = $channelConfig['interface_code'] ?? '';
        $productCode = $channelConfig['product_code'] ?? '222';
        $paymentAmount = $channelConfig['payment_amount'] ?? '0.01';

        $testParams = [
            'payment_amount' => $paymentAmount,
            'product_code' => $productCode,
        ];

        // 根据接口代码添加特定参数
        switch ($interfaceCode) {
            case 'WechatJsapi':
                $testParams['openid'] = 'test_openid_' . time();
                break;
        }

        return $this->testService->testChannel($channelId, $testParams);
    }
}


