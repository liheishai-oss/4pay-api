<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use app\api\service\v1\order\QueryService;
use app\api\repository\v1\order\OrderRepository;
use app\api\validator\v1\order\BusinessDataValidator;
use app\model\Order;
use app\model\Merchant;
use app\exception\MyBusinessException;
use app\enums\OrderStatus;

class OrderQueryTest extends TestCase
{
    private $queryService;
    private $mockRepository;
    private $mockValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建模拟对象
        $this->mockRepository = $this->createMock(OrderRepository::class);
        $this->mockValidator = $this->createMock(BusinessDataValidator::class);
        
        // 创建服务实例
        $this->queryService = new QueryService($this->mockRepository, $this->mockValidator);
    }

    /**
     * 测试通过平台订单号查询成功
     */
    public function testQueryOrderByOrderNoSuccess()
    {
        // 准备测试数据
        $merchantKey = 'MCH_test123456';
        $merchantSecret = 'secret123456';
        $orderNo = 'PLT_20240119153025001';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->id = 1;

        $order = new Order();
        $order->order_no = $orderNo;
        $order->merchant_order_no = 'ORDER_20240119_001';
        $order->third_party_order_no = 'TP_20240119153025001';
        $order->trace_id = 'TRACE_20240119153025001';
        $order->merchant_id = 1;
        $order->amount = 9950; // 99.50元
        $order->fee = 50; // 0.50元
        $order->status = OrderStatus::PAID;
        $order->subject = '测试订单';
        $order->created_at = now();
        $order->paid_time = now();

        $requestData = [
            'merchant_key' => $merchantKey,
            'order_no' => $orderNo,
            'query_type' => 'platform',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->with($merchantKey)
            ->willReturn($merchant);

        $this->mockRepository
            ->expects($this->once())
            ->method('getOrderByOrderNo')
            ->with($orderNo)
            ->willReturn($order);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate')
            ->with($requestData, $merchant, $order);

        // 执行测试
        $result = $this->queryService->queryOrder($requestData);

        // 验证结果
        $this->assertIsArray($result);
        $this->assertEquals($merchantKey, $result['merchant_key']);
        $this->assertEquals($orderNo, $result['order_no']);
        $this->assertEquals('ORDER_20240119_001', $result['merchant_order_no']);
        $this->assertEquals('TP_20240119153025001', $result['third_party_order_no']);
        $this->assertEquals('TRACE_20240119153025001', $result['trace_id']);
        $this->assertEquals('支付成功', $result['status']);
        $this->assertEquals('99.50', $result['amount']);
        $this->assertEquals('0.50', $result['fee']);
        $this->assertEquals('测试订单', $result['subject']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('paid_time', $result);
        $this->assertArrayHasKey('sign', $result);
    }

    /**
     * 测试通过商户订单号查询成功
     */
    public function testQueryOrderByMerchantOrderNoSuccess()
    {
        // 准备测试数据
        $merchantKey = 'MCH_test123456';
        $merchantSecret = 'secret123456';
        $merchantOrderNo = 'ORDER_20240119_001';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->id = 1;

        $order = new Order();
        $order->order_no = 'PLT_20240119153025001';
        $order->merchant_order_no = $merchantOrderNo;
        $order->third_party_order_no = 'TP_20240119153025001';
        $order->trace_id = 'TRACE_20240119153025001';
        $order->merchant_id = 1;
        $order->amount = 50000; // 500.00元
        $order->fee = 250; // 2.50元
        $order->status = OrderStatus::PENDING_PAYMENT;
        $order->subject = '商户订单测试';
        $order->created_at = now();
        $order->paid_time = null;

        $requestData = [
            'merchant_key' => $merchantKey,
            'merchant_order_no' => $merchantOrderNo,
            'query_type' => 'merchant',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->with($merchantKey)
            ->willReturn($merchant);

        $this->mockRepository
            ->expects($this->once())
            ->method('getOrderByMerchantOrderNo')
            ->with($merchantOrderNo)
            ->willReturn($order);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate')
            ->with($requestData, $merchant, $order);

        // 执行测试
        $result = $this->queryService->queryOrder($requestData);

        // 验证结果
        $this->assertIsArray($result);
        $this->assertEquals($merchantKey, $result['merchant_key']);
        $this->assertEquals('PLT_20240119153025001', $result['order_no']);
        $this->assertEquals($merchantOrderNo, $result['merchant_order_no']);
        $this->assertEquals('待支付', $result['status']);
        $this->assertEquals('500.00', $result['amount']);
        $this->assertEquals('2.50', $result['fee']);
        $this->assertEquals('商户订单测试', $result['subject']);
        $this->assertNull($result['paid_time']);
    }

    /**
     * 测试商户不存在
     */
    public function testQueryOrderMerchantNotFound()
    {
        $merchantKey = 'MCH_nonexistent';
        $requestData = [
            'merchant_key' => $merchantKey,
            'order_no' => 'PLT_20240119153025001',
            'query_type' => 'platform',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->with($merchantKey)
            ->willReturn(null);

        // 执行测试并验证异常
        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('商户不存在');

        $this->queryService->queryOrder($requestData);
    }

    /**
     * 测试订单不存在
     */
    public function testQueryOrderNotFound()
    {
        $merchantKey = 'MCH_test123456';
        $orderNo = 'PLT_nonexistent';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = 'secret123456';
        $merchant->id = 1;

        $requestData = [
            'merchant_key' => $merchantKey,
            'order_no' => $orderNo,
            'query_type' => 'platform',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->with($merchantKey)
            ->willReturn($merchant);

        $this->mockRepository
            ->expects($this->once())
            ->method('getOrderByOrderNo')
            ->with($orderNo)
            ->willReturn(null);

        // 执行测试并验证异常
        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('订单不存在');

        $this->queryService->queryOrder($requestData);
    }

    /**
     * 测试不同订单状态显示
     */
    public function testDifferentOrderStatuses()
    {
        $merchantKey = 'MCH_test123456';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->id = 1;

        $testCases = [
            [OrderStatus::PENDING_PAYMENT, '待支付'],
            [OrderStatus::PROCESSING, '支付中'],
            [OrderStatus::PAID, '支付成功'],
            [OrderStatus::FAILED, '支付失败'],
            [OrderStatus::REFUNDED, '已退款'],
            [OrderStatus::CLOSED, '已关闭']
        ];

        foreach ($testCases as $index => [$status, $expectedStatusText]) {
            $order = new Order();
            $order->order_no = 'PLT_test_' . $index;
            $order->merchant_order_no = 'ORDER_test_' . $index;
            $order->third_party_order_no = 'TP_test_' . $index;
            $order->trace_id = 'TRACE_test_' . $index;
            $order->merchant_id = 1;
            $order->amount = 10000; // 100.00元
            $order->fee = 50; // 0.50元
            $order->status = $status;
            $order->subject = '状态测试订单';
            $order->created_at = now();
            $order->paid_time = $status === OrderStatus::PAID ? now() : null;

            $requestData = [
                'merchant_key' => $merchantKey,
                'order_no' => $order->order_no,
                'query_type' => 'platform',
                'timestamp' => time(),
                'sign' => 'test_signature'
            ];

            // 设置模拟对象期望
            $this->mockRepository
                ->expects($this->at($index * 2))
                ->method('getMerchantByKey')
                ->willReturn($merchant);

            $this->mockRepository
                ->expects($this->at($index * 2 + 1))
                ->method('getOrderByOrderNo')
                ->willReturn($order);

            $this->mockValidator
                ->expects($this->at($index))
                ->method('validate');

            // 执行测试
            $result = $this->queryService->queryOrder($requestData);

            // 验证状态文本
            $this->assertEquals($expectedStatusText, $result['status']);
        }
    }

    /**
     * 测试金额格式化
     */
    public function testAmountFormatting()
    {
        $merchantKey = 'MCH_test123456';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->id = 1;

        $order = new Order();
        $order->order_no = 'PLT_amount_test';
        $order->merchant_order_no = 'ORDER_amount_test';
        $order->third_party_order_no = 'TP_amount_test';
        $order->trace_id = 'TRACE_amount_test';
        $order->merchant_id = 1;
        $order->amount = 123456; // 1234.56元
        $order->fee = 1234; // 12.34元
        $order->status = OrderStatus::PAID;
        $order->subject = '金额测试订单';
        $order->created_at = now();
        $order->paid_time = now();

        $requestData = [
            'merchant_key' => $merchantKey,
            'order_no' => $order->order_no,
            'query_type' => 'platform',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockRepository
            ->expects($this->once())
            ->method('getOrderByOrderNo')
            ->willReturn($order);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->queryService->queryOrder($requestData);

        // 验证金额格式化
        $this->assertEquals('1234.56', $result['amount']);
        $this->assertEquals('12.34', $result['fee']);
    }

    /**
     * 测试签名生成
     */
    public function testSignatureGeneration()
    {
        $merchantKey = 'MCH_signature_test';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->id = 1;

        $order = new Order();
        $order->order_no = 'PLT_signature_test';
        $order->merchant_order_no = 'ORDER_signature_test';
        $order->third_party_order_no = 'TP_signature_test';
        $order->trace_id = 'TRACE_signature_test';
        $order->merchant_id = 1;
        $order->amount = 10000;
        $order->fee = 50;
        $order->status = OrderStatus::PAID;
        $order->subject = '签名测试订单';
        $order->created_at = now();
        $order->paid_time = now();

        $requestData = [
            'merchant_key' => $merchantKey,
            'order_no' => $order->order_no,
            'query_type' => 'platform',
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockRepository
            ->expects($this->once())
            ->method('getOrderByOrderNo')
            ->willReturn($order);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->queryService->queryOrder($requestData);

        // 验证签名存在且不为空
        $this->assertArrayHasKey('sign', $result);
        $this->assertNotEmpty($result['sign']);
        $this->assertIsString($result['sign']);
    }
}

