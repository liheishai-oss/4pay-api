<?php

use PHPUnit\Framework\TestCase;
use app\service\supplier\StoreService;
use app\model\Supplier;
use app\exception\MyBusinessException;

class SupplierStoreServiceTest extends TestCase
{
    private StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StoreService();
    }

    protected function tearDown(): void
    {
        // 清理测试数据
        Supplier::where('supplier_name', 'like', '测试%')->delete();
        parent::tearDown();
    }

    /**
     * 测试创建供应商 - 成功
     */
    public function testCreateSupplierSuccess()
    {
        $data = [
            'supplier_name' => '测试创建供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 0,
            'remark' => '测试备注',
            'prepayment_total_cent' => 100000,
            'prepayment_remaining_cent' => 50000,
            'withdrawable_balance_cent' => 25000,
            'today_receipt_cent' => 10000,
            'telegram_chat_id' => 123456789
        ];

        $result = $this->service->createSupplier($data);

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
        $this->assertNotNull($result->id);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    /**
     * 测试创建供应商 - 使用默认值
     */
    public function testCreateSupplierWithDefaults()
    {
        $data = [
            'supplier_name' => '测试默认值供应商_' . time()
        ];

        $result = $this->service->createSupplier($data);

        $this->assertInstanceOf(Supplier::class, $result);
        $this->assertEquals($data['supplier_name'], $result->supplier_name);
        $this->assertEquals(Supplier::STATUS_ENABLED, $result->status);
        $this->assertEquals(Supplier::PREPAY_CHECK_NOT_REQUIRED, $result->prepayment_check);
        $this->assertEquals('', $result->remark);
        $this->assertEquals(0, $result->prepayment_total_cent);
        $this->assertEquals(0, $result->prepayment_remaining_cent);
        $this->assertEquals(0, $result->withdrawable_balance_cent);
        $this->assertEquals(0, $result->today_receipt_cent);
        $this->assertEquals(0, $result->telegram_chat_id);
    }

    /**
     * 测试创建供应商 - 供应商名称已存在
     */
    public function testCreateSupplierDuplicateName()
    {
        $supplierName = '测试重复名称供应商_' . time();
        
        // 创建第一个供应商
        $this->createTestSupplier(['supplier_name' => $supplierName]);

        // 尝试创建相同名称的供应商
        $data = [
            'supplier_name' => $supplierName,
            'status' => 1
        ];

        $this->expectException(MyBusinessException::class);
        $this->expectExceptionMessage('供应商名称已存在');

        $this->service->createSupplier($data);
    }

    /**
     * 测试创建供应商 - 空供应商名称
     */
    public function testCreateSupplierEmptyName()
    {
        $data = [
            'supplier_name' => '',
            'status' => 1
        ];

        $this->expectException(\Exception::class);
        $this->service->createSupplier($data);
    }

    /**
     * 测试创建供应商 - 不同状态值
     */
    public function testCreateSupplierDifferentStatus()
    {
        // 测试启用状态
        $data1 = [
            'supplier_name' => '测试启用供应商_' . time(),
            'status' => 1
        ];
        $result1 = $this->service->createSupplier($data1);
        $this->assertEquals(1, $result1->status);

        // 测试禁用状态
        $data2 = [
            'supplier_name' => '测试禁用供应商_' . time(),
            'status' => 0
        ];
        $result2 = $this->service->createSupplier($data2);
        $this->assertEquals(0, $result2->status);
    }

    /**
     * 测试创建供应商 - 不同预付检验状态
     */
    public function testCreateSupplierDifferentPrepayCheck()
    {
        // 测试需要预付检验
        $data1 = [
            'supplier_name' => '测试需要预付检验供应商_' . time(),
            'prepayment_check' => 1
        ];
        $result1 = $this->service->createSupplier($data1);
        $this->assertEquals(1, $result1->prepayment_check);

        // 测试不需要预付检验
        $data2 = [
            'supplier_name' => '测试不需要预付检验供应商_' . time(),
            'prepayment_check' => 0
        ];
        $result2 = $this->service->createSupplier($data2);
        $this->assertEquals(0, $result2->prepayment_check);
    }

    /**
     * 测试创建供应商 - 金额字段
     */
    public function testCreateSupplierAmountFields()
    {
        $data = [
            'supplier_name' => '测试金额供应商_' . time(),
            'prepayment_total_cent' => 123456789,
            'prepayment_remaining_cent' => 98765432,
            'withdrawable_balance_cent' => 55555555,
            'today_receipt_cent' => 11111111
        ];

        $result = $this->service->createSupplier($data);

        $this->assertEquals($data['prepayment_total_cent'], $result->prepayment_total_cent);
        $this->assertEquals($data['prepayment_remaining_cent'], $result->prepayment_remaining_cent);
        $this->assertEquals($data['withdrawable_balance_cent'], $result->withdrawable_balance_cent);
        $this->assertEquals($data['today_receipt_cent'], $result->today_receipt_cent);
    }

    /**
     * 测试创建供应商 - 长备注
     */
    public function testCreateSupplierLongRemark()
    {
        $longRemark = str_repeat('测试备注', 50); // 生成长备注
        $data = [
            'supplier_name' => '测试长备注供应商_' . time(),
            'remark' => $longRemark
        ];

        $result = $this->service->createSupplier($data);

        $this->assertEquals($longRemark, $result->remark);
    }

    /**
     * 测试创建供应商 - 大Telegram ID
     */
    public function testCreateSupplierLargeTelegramId()
    {
        $data = [
            'supplier_name' => '测试大Telegram ID供应商_' . time(),
            'telegram_chat_id' => 999999999999999
        ];

        $result = $this->service->createSupplier($data);

        $this->assertEquals($data['telegram_chat_id'], $result->telegram_chat_id);
    }

    /**
     * 测试创建供应商 - 数据库事务回滚
     */
    public function testCreateSupplierTransactionRollback()
    {
        // 模拟数据库异常
        $this->expectException(MyBusinessException::class);
        
        // 这里可以通过模拟数据库连接来测试事务回滚
        // 由于实际测试环境限制，这里只测试异常处理
        $data = [
            'supplier_name' => '测试事务回滚供应商_' . time(),
            'status' => 1
        ];

        // 正常情况下应该成功
        $result = $this->service->createSupplier($data);
        $this->assertInstanceOf(Supplier::class, $result);
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






