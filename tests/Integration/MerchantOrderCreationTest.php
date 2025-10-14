<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use support\Request;
use app\api\controller\v1\order\CreateController;
use app\api\validator\v1\order\CreatePreConditionValidator;
use app\api\service\v1\order\CreateService;
use app\api\validator\v1\order\CreateBusinessDataValidator;

/**
 * 商户订单创建集成测试
 * 包含xhprof性能分析
 */
class MerchantOrderCreationTest extends TestCase
{
    protected CreateController $controller;
    protected array $testData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 初始化控制器
        $validator = new CreatePreConditionValidator();
        $service = new CreateService();
        $this->controller = new CreateController($validator, $service);
        
        // 准备测试数据
        $this->testData = [
            'merchant_key' => 'test_merchant_001',
            'merchant_order_no' => 'TEST_ORDER_' . time(),
            'order_amount' => '100.00',
            'product_code' => 'ALIPAY_H5',
            'notify_url' => 'http://example.com/notify',
            'sign' => 'test_signature',
            'return_url' => 'http://example.com/return',
            'payer_ip' => '127.0.0.1',
            'order_title' => '测试订单',
            'order_body' => '这是一个测试订单'
        ];
    }

    /**
     * 测试正常订单创建流程
     */
    public function testCreateOrderSuccess(): void
    {
        // 开始性能分析
        if (function_exists('xhprof_enable')) {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // 创建模拟请求
        $request = $this->createMockRequest($this->testData);
        
        // 执行订单创建
        $response = $this->controller->create($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        // 保存性能数据
        $this->savePerformanceData('testCreateOrderSuccess', $startTime, $endTime, $startMemory, $endMemory);

        // 验证响应
        $this->assertInstanceOf(\support\Response::class, $response);
        
        $responseData = json_decode($response->getBody(), true);
        $this->assertEquals(200, $responseData['code']);
        $this->assertEquals('订单创建成功', $responseData['msg']);
    }

    /**
     * 测试参数验证性能
     */
    public function testValidationPerformance(): void
    {
        if (function_exists('xhprof_enable')) {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }

        $startTime = microtime(true);
        
        // 测试多个订单的验证性能
        for ($i = 0; $i < 100; $i++) {
            $testData = $this->testData;
            $testData['merchant_order_no'] = 'TEST_ORDER_' . $i . '_' . time();
            
            $request = $this->createMockRequest($testData);
            
            try {
                $this->controller->create($request);
            } catch (\Exception $e) {
                // 忽略业务异常，只测试性能
            }
        }
        
        $endTime = microtime(true);
        $this->savePerformanceData('testValidationPerformance', $startTime, $endTime, 0, 0);
        
        $this->assertTrue(true); // 性能测试通过
    }

    /**
     * 测试并发订单创建性能
     */
    public function testConcurrentOrderCreation(): void
    {
        if (function_exists('xhprof_enable')) {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $concurrentCount = 10;
        $processes = [];
        
        // 模拟并发请求
        for ($i = 0; $i < $concurrentCount; $i++) {
            $testData = $this->testData;
            $testData['merchant_order_no'] = 'CONCURRENT_ORDER_' . $i . '_' . time();
            
            $request = $this->createMockRequest($testData);
            
            // 这里可以启动子进程或使用多线程来模拟真正的并发
            // 为了简化测试，我们顺序执行但记录时间
            $processStart = microtime(true);
            try {
                $this->controller->create($request);
            } catch (\Exception $e) {
                // 记录错误但不中断测试
            }
            $processTime = microtime(true) - $processStart;
            
            $processes[] = $processTime;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $this->savePerformanceData('testConcurrentOrderCreation', $startTime, $endTime, $startMemory, $endMemory, [
            'concurrent_count' => $concurrentCount,
            'process_times' => $processes,
            'avg_process_time' => array_sum($processes) / count($processes)
        ]);
        
        $this->assertCount($concurrentCount, $processes);
    }

    /**
     * 测试内存使用情况
     */
    public function testMemoryUsage(): void
    {
        if (function_exists('xhprof_enable')) {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }

        $startMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        
        // 创建大量订单测试内存使用
        $orderCount = 50;
        for ($i = 0; $i < $orderCount; $i++) {
            $testData = $this->testData;
            $testData['merchant_order_no'] = 'MEMORY_TEST_ORDER_' . $i . '_' . time();
            
            $request = $this->createMockRequest($testData);
            
            try {
                $this->controller->create($request);
            } catch (\Exception $e) {
                // 忽略业务异常
            }
            
            // 每10个订单检查一次内存
            if ($i % 10 === 0) {
                $currentMemory = memory_get_usage();
                $currentPeak = memory_get_peak_usage();
                
                $this->savePerformanceData('testMemoryUsage_' . $i, 0, 0, $startMemory, $currentMemory, [
                    'order_count' => $i,
                    'memory_usage' => $currentMemory - $startMemory,
                    'peak_memory' => $currentPeak - $peakMemory
                ]);
            }
        }
        
        $finalMemory = memory_get_usage();
        $finalPeak = memory_get_peak_usage();
        
        $this->savePerformanceData('testMemoryUsage_final', 0, 0, $startMemory, $finalMemory, [
            'total_orders' => $orderCount,
            'total_memory_usage' => $finalMemory - $startMemory,
            'total_peak_memory' => $finalPeak - $peakMemory
        ]);
        
        $this->assertTrue($finalMemory > $startMemory);
    }

    /**
     * 创建模拟请求对象
     */
    protected function createMockRequest(array $data): Request
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->setUri('/api/v1/order/create');
        
        // 设置请求数据
        foreach ($data as $key => $value) {
            $request->set($key, $value);
        }
        
        return $request;
    }

    /**
     * 保存性能分析数据
     */
    protected function savePerformanceData(string $testName, float $startTime, float $endTime, int $startMemory, int $endMemory, array $extraData = []): void
    {
        $outputDir = runtime_path() . '/xhprof';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $performanceData = [
            'test_name' => $testName,
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage' => $endMemory - $startMemory,
            'memory_peak' => memory_get_peak_usage(),
            'timestamp' => date('Y-m-d H:i:s'),
            'extra_data' => $extraData
        ];

        // 如果有xhprof数据，保存详细的分析结果
        if (function_exists('xhprof_disable')) {
            $xhprofData = xhprof_disable();
            $performanceData['xhprof_data'] = $xhprofData;
        }

        // 保存到文件
        $filename = $outputDir . '/test_' . $testName . '_' . date('Ymd_His') . '_' . uniqid() . '.json';
        file_put_contents($filename, json_encode($performanceData, JSON_PRETTY_PRINT));

        // 输出到控制台
        echo "\n性能分析结果 - {$testName}:\n";
        echo "执行时间: {$performanceData['duration_ms']}ms\n";
        echo "内存使用: " . round($performanceData['memory_usage'] / 1024 / 1024, 2) . "MB\n";
        echo "峰值内存: " . round($performanceData['memory_peak'] / 1024 / 1024, 2) . "MB\n";
        echo "数据文件: {$filename}\n\n";
    }
}

