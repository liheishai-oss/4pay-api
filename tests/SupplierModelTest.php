<?php

use PHPUnit\Framework\TestCase;
use app\model\Supplier;

class SupplierModelTest extends TestCase
{
    protected function tearDown(): void
    {
        // 清理测试数据
        Supplier::where('supplier_name', 'like', '测试%')->delete();
        parent::tearDown();
    }

    /**
     * 测试模型常量
     */
    public function testConstants()
    {
        $this->assertEquals(0, Supplier::STATUS_DISABLED);
        $this->assertEquals(1, Supplier::STATUS_ENABLED);
        $this->assertEquals(0, Supplier::PREPAY_CHECK_NOT_REQUIRED);
        $this->assertEquals(1, Supplier::PREPAY_CHECK_REQUIRED);
    }

    /**
     * 测试模型属性
     */
    public function testFillableAttributes()
    {
        $fillable = [
            'supplier_name',
            'status',
            'prepayment_check',
            'remark',
            'telegram_chat_id',
            'callback_whitelist_ips'
        ];

        $this->assertEquals($fillable, (new Supplier())->getFillable());
    }

    /**
     * 测试模型类型转换
     */
    public function testCasts()
    {
        $supplier = new Supplier();
        $casts = $supplier->getCasts();

        $this->assertEquals('integer', $casts['status']);
        $this->assertEquals('integer', $casts['prepayment_check']);
        $this->assertEquals('integer', $casts['prepayment_total']);
        $this->assertEquals('integer', $casts['prepayment_remaining']);
        $this->assertEquals('integer', $casts['withdrawable_balance']);
        $this->assertEquals('integer', $casts['today_receipt']);
        $this->assertEquals('integer', $casts['telegram_chat_id']);
        $this->assertEquals('array', $casts['callback_whitelist_ips']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
    }

    /**
     * 测试创建供应商
     */
    public function testCreateSupplier()
    {
        $data = [
            'supplier_name' => '测试供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 0,
            'remark' => '测试备注',
            'prepayment_total' => 100000,
            'prepayment_remaining' => 50000,
            'withdrawable_balance' => 25000,
            'today_receipt' => 10000,
            'telegram_chat_id' => 123456789
        ];

        $supplier = Supplier::create($data);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals($data['supplier_name'], $supplier->supplier_name);
        $this->assertEquals($data['status'], $supplier->status);
        $this->assertEquals($data['prepayment_check'], $supplier->prepayment_check);
        $this->assertEquals($data['remark'], $supplier->remark);
        $this->assertEquals($data['prepayment_total'], $supplier->prepayment_total);
        $this->assertEquals($data['prepayment_remaining'], $supplier->prepayment_remaining);
        $this->assertEquals($data['withdrawable_balance'], $supplier->withdrawable_balance);
        $this->assertEquals($data['today_receipt'], $supplier->today_receipt);
        $this->assertEquals($data['telegram_chat_id'], $supplier->telegram_chat_id);
        $this->assertNotNull($supplier->id);
        $this->assertNotNull($supplier->created_at);
        $this->assertNotNull($supplier->updated_at);
    }

    /**
     * 测试更新供应商
     */
    public function testUpdateSupplier()
    {
        $supplier = $this->createTestSupplier();

        $updateData = [
            'supplier_name' => '已更新的供应商_' . time(),
            'remark' => '已更新的备注',
            'status' => 0,
            'prepayment_check' => 1
        ];

        $supplier->update($updateData);

        $this->assertEquals($updateData['supplier_name'], $supplier->supplier_name);
        $this->assertEquals($updateData['remark'], $supplier->remark);
        $this->assertEquals($updateData['status'], $supplier->status);
        $this->assertEquals($updateData['prepayment_check'], $supplier->prepayment_check);
    }

    /**
     * 测试删除供应商
     */
    public function testDeleteSupplier()
    {
        $supplier = $this->createTestSupplier();
        $supplierId = $supplier->id;

        $result = $supplier->delete();

        $this->assertTrue($result);
        $this->assertNull(Supplier::find($supplierId));
    }

    /**
     * 测试批量操作
     */
    public function testBatchOperations()
    {
        // 创建多个测试数据
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $supplier = $this->createTestSupplier(['supplier_name' => '批量测试供应商_' . $i . '_' . time()]);
            $ids[] = $supplier->id;
        }

        // 测试批量更新
        $updateResult = Supplier::whereIn('id', $ids)->update(['status' => 0]);
        $this->assertEquals(3, $updateResult);

        // 验证更新结果
        foreach ($ids as $id) {
            $supplier = Supplier::find($id);
            $this->assertEquals(0, $supplier->status);
        }

        // 测试批量删除
        $deleteResult = Supplier::whereIn('id', $ids)->delete();
        $this->assertEquals(3, $deleteResult);

        // 验证删除结果
        foreach ($ids as $id) {
            $this->assertNull(Supplier::find($id));
        }
    }

    /**
     * 测试查询条件
     */
    public function testQueryConditions()
    {
        $supplierName = 'test_query_' . time();
        
        $supplier = $this->createTestSupplier([
            'supplier_name' => $supplierName,
            'status' => 1,
            'telegram_chat_id' => 987654321
        ]);

        // 测试根据供应商名称查询
        $foundByName = Supplier::where('supplier_name', $supplierName)->first();
        $this->assertInstanceOf(Supplier::class, $foundByName);
        $this->assertEquals($supplier->id, $foundByName->id);

        // 测试根据状态查询
        $foundByStatus = Supplier::where('status', 1)->where('supplier_name', $supplierName)->first();
        $this->assertInstanceOf(Supplier::class, $foundByStatus);
        $this->assertEquals($supplier->id, $foundByStatus->id);

        // 测试根据Telegram ID查询
        $foundByTelegramId = Supplier::where('telegram_chat_id', 987654321)->first();
        $this->assertInstanceOf(Supplier::class, $foundByTelegramId);
        $this->assertEquals($supplier->id, $foundByTelegramId->id);
    }

    /**
     * 测试排序
     */
    public function testOrdering()
    {
        // 创建多个测试数据
        for ($i = 0; $i < 3; $i++) {
            $this->createTestSupplier(['supplier_name' => '排序测试_' . $i . '_' . time()]);
            sleep(1); // 确保创建时间不同
        }

        // 测试按创建时间降序排列
        $suppliers = Supplier::where('supplier_name', 'like', '排序测试_%')
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertGreaterThan(1, $suppliers->count());
        
        // 验证排序
        for ($i = 0; $i < $suppliers->count() - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $suppliers[$i + 1]->created_at,
                $suppliers[$i]->created_at
            );
        }
    }

    /**
     * 测试分页
     */
    public function testPagination()
    {
        // 创建多个测试数据
        for ($i = 0; $i < 5; $i++) {
            $this->createTestSupplier(['supplier_name' => '分页测试_' . $i . '_' . time()]);
        }

        $paginated = Supplier::where('supplier_name', 'like', '分页测试_%')
            ->paginate(2);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $paginated);
        $this->assertEquals(2, $paginated->perPage());
        $this->assertGreaterThan(0, $paginated->total());
    }

    /**
     * 测试唯一约束
     */
    public function testUniqueConstraints()
    {
        $supplierName = 'test_unique_' . time();

        // 创建第一个供应商
        $supplier1 = $this->createTestSupplier([
            'supplier_name' => $supplierName
        ]);

        // 尝试创建相同名称的供应商应该失败
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createTestSupplier([
            'supplier_name' => $supplierName
        ]);
    }

    /**
     * 测试金额字段的精度
     */
    public function testAmountFields()
    {
        $data = [
            'supplier_name' => '测试金额供应商_' . time(),
            'prepayment_total' => 123456789,
            'prepayment_remaining' => 98765432,
            'withdrawable_balance' => 55555555,
            'today_receipt' => 11111111
        ];

        $supplier = Supplier::create($data);

        $this->assertEquals(123456789, $supplier->prepayment_total);
        $this->assertEquals(98765432, $supplier->prepayment_remaining);
        $this->assertEquals(55555555, $supplier->withdrawable_balance);
        $this->assertEquals(11111111, $supplier->today_receipt);
    }

    /**
     * 测试回调IP白名单字段
     */
    public function testCallbackWhitelistIps()
    {
        $data = [
            'supplier_name' => '测试IP白名单供应商_' . time(),
            'callback_whitelist_ips' => ['192.168.1.1', '192.168.1.2', '10.0.0.1']
        ];

        $supplier = Supplier::create($data);

        $this->assertIsArray($supplier->callback_whitelist_ips);
        $this->assertEquals(['192.168.1.1', '192.168.1.2', '10.0.0.1'], $supplier->callback_whitelist_ips);

        // 测试更新IP白名单
        $supplier->callback_whitelist_ips = ['203.0.113.1', '203.0.113.2'];
        $supplier->save();

        $this->assertEquals(['203.0.113.1', '203.0.113.2'], $supplier->callback_whitelist_ips);

        // 测试空数组
        $supplier->callback_whitelist_ips = [];
        $supplier->save();

        $this->assertEquals([], $supplier->callback_whitelist_ips);
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
            'prepayment_total' => 0,
            'prepayment_remaining' => 0,
            'withdrawable_balance' => 0,
            'today_receipt' => 0,
            'telegram_chat_id' => 0
        ];

        $data = array_merge($defaultData, $data);

        return Supplier::create($data);
    }
}
