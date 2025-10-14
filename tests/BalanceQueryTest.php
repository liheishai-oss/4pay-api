<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use app\api\service\v1\merchant\BalanceService;
use app\api\repository\v1\merchant\MerchantRepository;
use app\api\validator\v1\merchant\BusinessDataValidator;
use app\model\Merchant;
use app\exception\MyBusinessException;
use app\common\helpers\SignatureHelper;

class BalanceQueryTest extends TestCase
{
    private $balanceService;
    private $mockRepository;
    private $mockValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建模拟对象
        $this->mockRepository = $this->createMock(MerchantRepository::class);
        $this->mockValidator = $this->createMock(BusinessDataValidator::class);
        
        // 创建服务实例
        $this->balanceService = new BalanceService($this->mockRepository, $this->mockValidator);
    }

    /**
     * 测试成功查询余额
     */
    public function testQueryBalanceSuccess()
    {
        // 准备测试数据
        $merchantKey = 'MCH_test123456';
        $merchantSecret = 'secret123456';
        $withdrawableAmount = 100000; // 1000.00元（分）
        $frozenAmount = 5000; // 50.00元（分）
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->withdrawable_amount = $withdrawableAmount;
        $merchant->frozen_amount = $frozenAmount;

        $requestData = [
            'merchant_key' => $merchantKey,
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->with($merchantKey)
            ->willReturn($merchant);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate')
            ->with($requestData, $merchant);

        // 执行测试
        $result = $this->balanceService->queryBalance($requestData);

        // 验证结果
        $this->assertIsArray($result);
        $this->assertEquals($merchantKey, $result['merchant_key']);
        $this->assertEquals('950.00', $result['balance']); // (100000 - 5000) / 100
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertArrayHasKey('sign', $result);
        $this->assertStringStartsWith('TRACE_', $result['trace_id']);
    }

    /**
     * 测试商户不存在
     */
    public function testQueryBalanceMerchantNotFound()
    {
        $merchantKey = 'MCH_nonexistent';
        $requestData = [
            'merchant_key' => $merchantKey,
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

        $this->balanceService->queryBalance($requestData);
    }

    /**
     * 测试余额计算正确性
     */
    public function testBalanceCalculation()
    {
        $merchantKey = 'MCH_calculation_test';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->withdrawable_amount = 123456; // 1234.56元
        $merchant->frozen_amount = 7890; // 78.90元

        $requestData = [
            'merchant_key' => $merchantKey,
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->balanceService->queryBalance($requestData);

        // 验证余额计算：(123456 - 7890) / 100 = 1155.66
        $this->assertEquals('1155.66', $result['balance']);
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
        $merchant->withdrawable_amount = 50000; // 500.00元
        $merchant->frozen_amount = 0;

        $requestData = [
            'merchant_key' => $merchantKey,
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->balanceService->queryBalance($requestData);

        // 验证签名存在且不为空
        $this->assertArrayHasKey('sign', $result);
        $this->assertNotEmpty($result['sign']);
        $this->assertIsString($result['sign']);
    }

    /**
     * 测试零余额情况
     */
    public function testZeroBalance()
    {
        $merchantKey = 'MCH_zero_balance';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->withdrawable_amount = 0;
        $merchant->frozen_amount = 0;

        $requestData = [
            'merchant_key' => $merchantKey,
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->balanceService->queryBalance($requestData);

        // 验证零余额
        $this->assertEquals('0.00', $result['balance']);
    }

    /**
     * 测试负余额情况（冻结金额大于可提现金额）
     */
    public function testNegativeBalance()
    {
        $merchantKey = 'MCH_negative_balance';
        $merchantSecret = 'secret123456';
        
        $merchant = new Merchant();
        $merchant->merchant_key = $merchantKey;
        $merchant->merchant_secret = $merchantSecret;
        $merchant->withdrawable_amount = 10000; // 100.00元
        $merchant->frozen_amount = 15000; // 150.00元

        $requestData = [
            'merchant_key' => $merchantKey,
            'timestamp' => time(),
            'sign' => 'test_signature'
        ];

        // 设置模拟对象期望
        $this->mockRepository
            ->expects($this->once())
            ->method('getMerchantByKey')
            ->willReturn($merchant);

        $this->mockValidator
            ->expects($this->once())
            ->method('validate');

        // 执行测试
        $result = $this->balanceService->queryBalance($requestData);

        // 验证负余额：(10000 - 15000) / 100 = -50.00
        $this->assertEquals('-50.00', $result['balance']);
    }
}

