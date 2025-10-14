<?php

use PHPUnit\Framework\TestCase;
use app\service\supplier\IndexService;
use app\model\Supplier;
use app\exception\MyBusinessException;

class SupplierIndexServiceTest extends TestCase
{
    private IndexService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndexService();
    }

    protected function tearDown(): void
    {
        // 清理测试数据
        Supplier::where('supplier_name', 'like', '测试%')->delete();
        parent::tearDown();
    }

    /**
     * 测试获取供应商列表 - 基本功能
     */
    public function testGetSupplierListBasic()
    {
        // 创建测试数据
        $this->createTestSuppliers(5);

        $params = ['page' => 1, 'page_size' => 10];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(10, $result->perPage());
    }

    /**
     * 测试获取供应商列表 - 按供应商名称筛选
     */
    public function testGetSupplierListFilterByName()
    {
        // 创建测试数据
        $this->createTestSupplier(['supplier_name' => '测试筛选供应商_' . time()]);
        $this->createTestSupplier(['supplier_name' => '其他供应商_' . time()]);

        $params = [
            'page' => 1,
            'page_size' => 10,
            'supplier_name' => '测试筛选'
        ];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertStringContainsString('测试筛选', $result->items()[0]->supplier_name);
    }

    /**
     * 测试获取供应商列表 - 按状态筛选
     */
    public function testGetSupplierListFilterByStatus()
    {
        // 创建测试数据
        $this->createTestSupplier(['status' => 1]);
        $this->createTestSupplier(['status' => 0]);

        $params = [
            'page' => 1,
            'page_size' => 10,
            'status' => 1
        ];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals(1, $result->items()[0]->status);
    }

    /**
     * 测试获取供应商列表 - 复合筛选
     */
    public function testGetSupplierListFilterCombined()
    {
        // 创建测试数据
        $this->createTestSupplier([
            'supplier_name' => '测试复合筛选供应商_' . time(),
            'status' => 1
        ]);
        $this->createTestSupplier([
            'supplier_name' => '测试复合筛选供应商2_' . time(),
            'status' => 0
        ]);
        $this->createTestSupplier([
            'supplier_name' => '其他供应商_' . time(),
            'status' => 1
        ]);

        $params = [
            'page' => 1,
            'page_size' => 10,
            'supplier_name' => '测试复合筛选',
            'status' => 1
        ];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertStringContainsString('测试复合筛选', $result->items()[0]->supplier_name);
        $this->assertEquals(1, $result->items()[0]->status);
    }

    /**
     * 测试获取供应商列表 - 分页
     */
    public function testGetSupplierListPagination()
    {
        // 创建多个测试数据
        $this->createTestSuppliers(15);

        $params = ['page' => 1, 'page_size' => 5];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals(1, $result->currentPage());
        $this->assertGreaterThanOrEqual(15, $result->total());
    }

    /**
     * 测试获取供应商列表 - 空结果
     */
    public function testGetSupplierListEmpty()
    {
        $params = [
            'page' => 1,
            'page_size' => 10,
            'supplier_name' => '不存在的供应商'
        ];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }

    /**
     * 测试获取供应商列表 - 默认参数
     */
    public function testGetSupplierListDefaultParams()
    {
        // 创建测试数据
        $this->createTestSuppliers(3);

        $result = $this->service->getSupplierList([]);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(15, $result->perPage()); // 默认每页15条
    }

    /**
     * 测试获取供应商列表 - 排序
     */
    public function testGetSupplierListOrdering()
    {
        // 创建测试数据，确保创建时间不同
        $this->createTestSupplier(['supplier_name' => '第一个供应商_' . time()]);
        sleep(1);
        $this->createTestSupplier(['supplier_name' => '第二个供应商_' . time()]);
        sleep(1);
        $this->createTestSupplier(['supplier_name' => '第三个供应商_' . time()]);

        $params = ['page' => 1, 'page_size' => 10];
        $result = $this->service->getSupplierList($params);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThanOrEqual(3, $result->total());

        // 验证按创建时间降序排列
        $items = $result->items();
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual(
                $items[1]->created_at,
                $items[0]->created_at
            );
        }
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

    /**
     * 创建多个测试供应商
     */
    private function createTestSuppliers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createTestSupplier([
                'supplier_name' => '批量测试供应商_' . $i . '_' . time()
            ]);
        }
    }
}






