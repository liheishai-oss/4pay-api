<?php

use PHPUnit\Framework\TestCase;
use app\service\supplier\DetailService;
use app\model\Supplier;
use app\exception\MyBusinessException;

class SupplierDetailServiceTest extends TestCase
{
    private DetailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DetailService();
    }

    protected function tearDown(): void
    {
        // 清理测试数据
        Supplier::where('supplier_name', 'like', '测试%')->delete();
        parent::tearDown();
    }

    /**
     * 测试获取供应商详情 - 成功
     */
    public function testGetSupplierDetailSuccess()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $result = $this->service->getSupplierDetail($supplier->id);

        $this->assertInstanceOf(Supplier::class, $result);
        $this->assertEquals($supplier->id, $result->id);
        $this->assertEquals($supplier->supplier_name, $result->supplier_name);
        $this->assertEquals($supplier->status, $result->status);
        $this->assertEquals($supplier->prepayment_check, $result->prepayment_check);
        $this->assertEquals($supplier->remark, $result->remark);
    }

    /**
     * 测试获取供应商详情 - 供应商不存在
     */
    public function testGetSupplierDetailNotFound()
    {
        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('供应商不存在');

        $this->service->getSupplierDetail(99999);
    }

    /**
     * 测试获取供应商详情 - ID为0
     */
    public function testGetSupplierDetailZeroId()
    {
        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('供应商不存在');

        $this->service->getSupplierDetail(0);
    }

    /**
     * 测试获取供应商详情 - 负数ID
     */
    public function testGetSupplierDetailNegativeId()
    {
        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('供应商不存在');

        $this->service->getSupplierDetail(-1);
    }

    /**
     * 测试获取供应商详情 - 验证所有字段
     */
    public function testGetSupplierDetailAllFields()
    {
        $data = [
            'supplier_name' => '测试详情供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 1,
            'remark' => '测试详情备注',
            'prepayment_total_cent' => 100000,
            'prepayment_remaining_cent' => 50000,
            'withdrawable_balance_cent' => 25000,
            'today_receipt_cent' => 10000,
            'telegram_chat_id' => 123456789
        ];

        $supplier = $this->createTestSupplier($data);
        $result = $this->service->getSupplierDetail($supplier->id);

        $this->assertInstanceOf(Supplier::class, $result);
        $this->assertEquals($data['supplier_name'], $result->supplier_name);
        $this->assertEquals($data['status'], $result->status);
        $this->assertEquals($data['prepayment_check'], $result->prepayment_check);
        $this->assertEquals($data['remark'], $result->remark);
        $this->assertEquals($data['prepayment_total_cent'], $result->prepayment_total_cent);
        $this->assertEquals($data['prepayment_remaining_cent'], $result->prepayment_remaining_cent);
        $this->assertEquals($data['withdrawable_balance_cent'], $result->withdrawable_balance_cent);
        $this->assertEquals($data['today_receipt_cent'], $result->today_receipt_cent);
        $this->assertEquals($data['telegram_chat_id'], $result->telegram_chat_id);
    }

    /**
     * 测试获取供应商详情 - 已删除的供应商
     */
    public function testGetSupplierDetailDeleted()
    {
        // 创建并删除供应商
        $supplier = $this->createTestSupplier();
        $supplierId = $supplier->id;
        $supplier->delete();

        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('供应商不存在');

        $this->service->getSupplierDetail($supplierId);
    }

    /**
     * 测试获取供应商详情 - 不同状态的供应商
     */
    public function testGetSupplierDetailDifferentStatus()
    {
        // 测试启用状态的供应商
        $enabledSupplier = $this->createTestSupplier(['status' => 1]);
        $result = $this->service->getSupplierDetail($enabledSupplier->id);
        $this->assertEquals(1, $result->status);

        // 测试禁用状态的供应商
        $disabledSupplier = $this->createTestSupplier(['status' => 0]);
        $result = $this->service->getSupplierDetail($disabledSupplier->id);
        $this->assertEquals(0, $result->status);
    }

    /**
     * 测试获取供应商详情 - 不同预付检验状态
     */
    public function testGetSupplierDetailDifferentPrepayCheck()
    {
        // 测试需要预付检验的供应商
        $requiredSupplier = $this->createTestSupplier(['prepayment_check' => 1]);
        $result = $this->service->getSupplierDetail($requiredSupplier->id);
        $this->assertEquals(1, $result->prepayment_check);

        // 测试不需要预付检验的供应商
        $notRequiredSupplier = $this->createTestSupplier(['prepayment_check' => 0]);
        $result = $this->service->getSupplierDetail($notRequiredSupplier->id);
        $this->assertEquals(0, $result->prepayment_check);
    }

    /**
     * 创建测试供应商
     */
    private function createTestSupplier(array $data = []): Supplier
    {
        $defaultData = [
            'supplier_name' => '测试供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 0,
            'remark' => '测试备注',
            'prepayment_total_cent' => 0,
            'prepayment_remaining_cent' => 0,
            'withdrawable_balance_cent' => 0,
            'today_receipt_cent' => 0,
            'telegram_chat_id' => 0
        ];

        $data = array_merge($defaultData, $data);

        return Supplier::create($data);
    }
}






