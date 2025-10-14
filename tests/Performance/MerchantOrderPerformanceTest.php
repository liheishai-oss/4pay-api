<?php

namespace Tests\Performance;

use PHPUnit\Framework\TestCase;
use support\Request;
use app\api\controller\v1\order\CreateController;
use app\api\validator\v1\order\CreatePreConditionValidator;
use app\api\service\v1\order\CreateService;

/**
 * 商户订单创建性能测试
 * 集成xhprof性能分析
 */
class MerchantOrderPerformanceTest extends TestCase
{
    protected CreateController $controller;
    protected string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 初始化控制器
        $validator = new CreatePreConditionValidator();
        $service = new CreateService();
        $this->controller = new CreateController($validator, $service);
        
        // 设置输出目录
        $this->outputDir = runtime_path() . '/xhprof/performance_tests';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * 测试单个订单创建性能
     */
    public function testSingleOrderCreationPerformance(): void
    {
        $testData = $this->generateTestData();
        $request = $this->createMockRequest($testData);
        
        // 开始性能分析
        $this->startXhprof('single_order_creation');
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $response = $this->controller->create($request);
            $this->assertInstanceOf(\support\Response::class, $response);
        } catch (\Exception $e) {
            $this->fail('订单创建失败: ' . $e->getMessage());
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $this->savePerformanceResult('single_order_creation', $startTime, $endTime, $startMemory, $endMemory, [
            'order_no' => $testData['merchant_order_no'],
            'merchant_key' => $testData['merchant_key']
        ]);
    }

    /**
     * 测试批量订单创建性能
     */
    public function testBatchOrderCreationPerformance(): void
    {
        $batchSize = 10;
        $results = [];
        
        $this->startXhprof('batch_order_creation');
        
        $totalStartTime = microtime(true);
        $totalStartMemory = memory_get_usage();
        
        for ($i = 0; $i < $batchSize; $i++) {
            $testData = $this->generateTestData($i);
            $request = $this->createMockRequest($testData);
            
            $orderStartTime = microtime(true);
            $orderStartMemory = memory_get_usage();
            
            try {
                $response = $this->controller->create($request);
                $results[] = [
                    'success' => true,
                    'duration' => (microtime(true) - $orderStartTime) * 1000,
                    'memory' => memory_get_usage() - $orderStartMemory
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration' => (microtime(true) - $orderStartTime) * 1000,
                    'memory' => memory_get_usage() - $orderStartMemory
                ];
            }
        }
        
        $totalEndTime = microtime(true);
        $totalEndMemory = memory_get_usage();
        
        $this->savePerformanceResult('batch_order_creation', $totalStartTime, $totalEndTime, $totalStartMemory, $totalEndMemory, [
            'batch_size' => $batchSize,
            'success_count' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results
        ]);
        
        // 验证至少有一些成功的订单
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->assertGreaterThan(0, $successCount, '应该有至少一个成功的订单创建');
    }

    /**
     * 测试并发订单创建性能
     */
    public function testConcurrentOrderCreationPerformance(): void
    {
        $concurrentCount = 5;
        $results = [];
        
        $this->startXhprof('concurrent_order_creation');
        
        $totalStartTime = microtime(true);
        $totalStartMemory = memory_get_usage();
        
        // 模拟并发请求（实际测试中可以使用多进程或多线程）
        for ($i = 0; $i < $concurrentCount; $i++) {
            $testData = $this->generateTestData($i);
            $request = $this->createMockRequest($testData);
            
            $concurrentStartTime = microtime(true);
            $concurrentStartMemory = memory_get_usage();
            
            try {
                $response = $this->controller->create($request);
                $results[] = [
                    'success' => true,
                    'duration' => (microtime(true) - $concurrentStartTime) * 1000,
                    'memory' => memory_get_usage() - $concurrentStartMemory,
                    'concurrent_id' => $i
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration' => (microtime(true) - $concurrentStartTime) * 1000,
                    'memory' => memory_get_usage() - $concurrentStartMemory,
                    'concurrent_id' => $i
                ];
            }
        }
        
        $totalEndTime = microtime(true);
        $totalEndMemory = memory_get_usage();
        
        $this->savePerformanceResult('concurrent_order_creation', $totalStartTime, $totalEndTime, $totalStartMemory, $totalEndMemory, [
            'concurrent_count' => $concurrentCount,
            'success_count' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results
        ]);
        
        // 验证并发性能
        $avgDuration = array_sum(array_column($results, 'duration')) / count($results);
        $this->assertLessThan(5000, $avgDuration, '平均响应时间应该小于5秒');
    }

    /**
     * 测试内存使用情况
     */
    public function testMemoryUsagePerformance(): void
    {
        $this->startXhprof('memory_usage_test');
        
        $initialMemory = memory_get_usage();
        $initialPeak = memory_get_peak_usage();
        
        $orderCount = 20;
        $memorySnapshots = [];
        
        for ($i = 0; $i < $orderCount; $i++) {
            $testData = $this->generateTestData($i);
            $request = $this->createMockRequest($testData);
            
            $beforeMemory = memory_get_usage();
            $beforePeak = memory_get_peak_usage();
            
            try {
                $this->controller->create($request);
            } catch (\Exception $e) {
                // 忽略错误，继续测试内存
            }
            
            $afterMemory = memory_get_usage();
            $afterPeak = memory_get_peak_usage();
            
            $memorySnapshots[] = [
                'order' => $i,
                'before_memory' => $beforeMemory,
                'after_memory' => $afterMemory,
                'memory_delta' => $afterMemory - $beforeMemory,
                'peak_delta' => $afterPeak - $beforePeak
            ];
        }
        
        $finalMemory = memory_get_usage();
        $finalPeak = memory_get_peak_usage();
        
        $this->savePerformanceResult('memory_usage_test', 0, 0, $initialMemory, $finalMemory, [
            'order_count' => $orderCount,
            'total_memory_usage' => $finalMemory - $initialMemory,
            'total_peak_usage' => $finalPeak - $initialPeak,
            'snapshots' => $memorySnapshots
        ]);
        
        // 验证内存使用合理
        $totalMemoryUsage = $finalMemory - $initialMemory;
        $this->assertLessThan(50 * 1024 * 1024, $totalMemoryUsage, '总内存使用应该小于50MB');
    }

    /**
     * 生成测试数据
     */
    protected function generateTestData(int $index = 0): array
    {
        return [
            'merchant_key' => 'test_merchant_' . $index,
            'merchant_order_no' => 'PERF_TEST_ORDER_' . $index . '_' . time(),
            'order_amount' => '100.00',
            'product_code' => 'ALIPAY_H5',
            'notify_url' => 'http://example.com/notify',
            'sign' => 'test_signature_' . $index,
            'return_url' => 'http://example.com/return',
            'payer_ip' => '127.0.0.1',
            'order_title' => '性能测试订单_' . $index,
            'order_body' => '这是一个性能测试订单_' . $index
        ];
    }

    /**
     * 创建模拟请求对象
     */
    protected function createMockRequest(array $data): Request
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->setUri('/api/v1/order/create');
        
        foreach ($data as $key => $value) {
            $request->set($key, $value);
        }
        
        return $request;
    }

    /**
     * 开始xhprof分析
     */
    protected function startXhprof(string $testName): void
    {
        if (function_exists('xhprof_enable')) {
            xhprof_enable(XHPROF_FLAGS_NO_BUILTINS + XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
            $GLOBALS['xhprof_test_name'] = $testName;
        }
    }

    /**
     * 保存性能测试结果
     */
    protected function savePerformanceResult(string $testName, float $startTime, float $endTime, int $startMemory, int $endMemory, array $extraData = []): void
    {
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
            
            // 保存xhprof运行数据
            if (class_exists('XHProfRuns_Default')) {
                $objXhprofRun = new \XHProfRuns_Default();
                $runName = $testName . '_' . date('YmdHis') . '_' . uniqid();
                $objXhprofRun->save_run($xhprofData, $runName);
                $performanceData['xhprof_run_name'] = $runName;
                $performanceData['xhprof_ui_url'] = $this->getXhprofUIUrl($runName);
            }
        }

        // 保存到文件
        $filename = $this->outputDir . '/performance_' . $testName . '_' . date('YmdHis') . '_' . uniqid() . '.json';
        file_put_contents($filename, json_encode($performanceData, JSON_PRETTY_PRINT));

        // 输出到控制台
        echo "\n=== 性能测试结果 - {$testName} ===\n";
        echo "执行时间: {$performanceData['duration_ms']}ms\n";
        echo "内存使用: " . round($performanceData['memory_usage'] / 1024 / 1024, 2) . "MB\n";
        echo "峰值内存: " . round($performanceData['memory_peak'] / 1024 / 1024, 2) . "MB\n";
        if (isset($performanceData['xhprof_ui_url'])) {
            echo "XHProf UI: {$performanceData['xhprof_ui_url']}\n";
        }
        echo "数据文件: {$filename}\n";
        echo "=====================================\n\n";
    }

    /**
     * 获取XHProf UI访问URL
     */
    protected function getXhprofUIUrl(string $runName): string
    {
        $baseUrl = config('app.app_host', 'http://localhost');
        return $baseUrl . '/xhprof/index.php?run=' . $runName . '&source=xhprof_ui';
    }
}

