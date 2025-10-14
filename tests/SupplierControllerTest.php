<?php

use PHPUnit\Framework\TestCase;
use app\admin\controller\v1\supplier\IndexController;
use app\admin\controller\v1\supplier\DetailController;
use app\admin\controller\v1\supplier\StoreController;
use app\admin\controller\v1\supplier\EditController;
use app\admin\controller\v1\supplier\DestroyController;
use app\admin\controller\v1\supplier\StatusSwitchController;
use app\admin\controller\v1\supplier\PrepayCheckController;
use app\model\Supplier;
use support\Request;

class SupplierControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        // 清理测试数据
        Supplier::where('supplier_name', 'like', '测试%')->delete();
        parent::tearDown();
    }

    /**
     * 测试IndexController的index方法
     */
    public function testIndexControllerIndex()
    {
        // 创建测试数据
        $this->createTestSupplier();

        $controller = new IndexController();
        $request = $this->createMockRequest(['page' => 1, 'page_size' => 10]);

        $response = $controller->index($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * 测试DetailController的show方法
     */
    public function testDetailControllerShow()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $controller = new DetailController();
        $request = $this->createMockRequestWithRoute(['id' => $supplier->id]);

        $response = $controller->show($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * 测试DetailController的show方法 - 供应商不存在
     */
    public function testDetailControllerShowNotFound()
    {
        $controller = new DetailController();
        $request = $this->createMockRequestWithRoute(['id' => 99999]);

        $response = $controller->show($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $data['code']);
        $this->assertEquals('供应商不存在', $data['msg']);
    }

    /**
     * 测试StoreController的store方法
     */
    public function testStoreControllerStore()
    {
        $controller = new StoreController();
        $data = [
            'supplier_name' => '测试创建控制器供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 0,
            'remark' => '测试备注',
            'prepayment_total_cent' => 100000,
            'prepayment_remaining_cent' => 50000,
            'withdrawable_balance_cent' => 25000,
            'today_receipt_cent' => 10000,
            'telegram_chat_id' => 123456789
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->store($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $responseData = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $responseData['code']);
        $this->assertEquals('创建成功', $responseData['msg']);
    }

    /**
     * 测试StoreController的store方法 - 验证失败
     */
    public function testStoreControllerStoreValidationFailed()
    {
        $controller = new StoreController();
        $data = [
            'supplier_name' => '', // 空名称应该验证失败
            'status' => 1
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->store($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $responseData = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $responseData['code']);
    }

    /**
     * 测试EditController的update方法
     */
    public function testEditControllerUpdate()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $controller = new EditController();
        $data = [
            'id' => $supplier->id,
            'supplier_name' => '已更新的控制器供应商_' . time(),
            'remark' => '已更新的备注',
            'status' => 0,
            'prepayment_check' => 1
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->update($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $responseData = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $responseData['code']);
        $this->assertEquals('更新成功', $responseData['msg']);
    }

    /**
     * 测试EditController的update方法 - 供应商不存在
     */
    public function testEditControllerUpdateNotFound()
    {
        $controller = new EditController();
        $data = [
            'id' => 99999,
            'supplier_name' => '不存在的供应商',
            'status' => 1
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->update($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $responseData = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $responseData['code']);
        $this->assertEquals('供应商不存在', $responseData['msg']);
    }

    /**
     * 测试DestroyController的destroy方法
     */
    public function testDestroyControllerDestroy()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $controller = new DestroyController();
        $request = $this->createMockRequest(['ids' => (string)$supplier->id]);

        $response = $controller->destroy($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('删除成功', $data['msg']);
    }

    /**
     * 测试DestroyController的destroy方法 - 批量删除
     */
    public function testDestroyControllerBatchDestroy()
    {
        // 创建多个测试数据
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $supplier = $this->createTestSupplier('批量删除测试_' . $i . '_' . time());
            $ids[] = $supplier->id;
        }

        $controller = new DestroyController();
        $request = $this->createMockRequest(['ids' => implode(',', $ids)]);

        $response = $controller->destroy($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('删除成功', $data['msg']);
    }

    /**
     * 测试DestroyController的destroy方法 - 验证失败
     */
    public function testDestroyControllerDestroyValidationFailed()
    {
        $controller = new DestroyController();
        $request = $this->createMockRequest([]); // 空数据应该验证失败

        $response = $controller->destroy($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $data['code']);
    }

    /**
     * 测试StatusSwitchController的toggle方法
     */
    public function testStatusSwitchControllerToggle()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $controller = new StatusSwitchController();
        $data = [
            'id' => $supplier->id,
            'status' => 0
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->toggle($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('状态切换成功', $data['msg']);
    }

    /**
     * 测试StatusSwitchController的toggle方法 - 供应商不存在
     */
    public function testStatusSwitchControllerToggleNotFound()
    {
        $controller = new StatusSwitchController();
        $data = [
            'id' => 99999,
            'status' => 0
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->toggle($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $data['code']);
        $this->assertEquals('供应商不存在', $data['msg']);
    }

    /**
     * 测试PrepayCheckController的check方法
     */
    public function testPrepayCheckControllerCheck()
    {
        // 创建测试数据
        $supplier = $this->createTestSupplier();

        $controller = new PrepayCheckController();
        $data = [
            'id' => $supplier->id,
            'prepayment_check' => 1
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->check($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('预付检验状态切换成功', $data['msg']);
    }

    /**
     * 测试PrepayCheckController的check方法 - 供应商不存在
     */
    public function testPrepayCheckControllerCheckNotFound()
    {
        $controller = new PrepayCheckController();
        $data = [
            'id' => 99999,
            'prepayment_check' => 1
        ];
        $request = $this->createMockRequest($data);

        $response = $controller->check($request);

        $this->assertInstanceOf(\support\Response::class, $response);
        $data = json_decode($response->rawBody(), true);
        $this->assertEquals(400, $data['code']);
        $this->assertEquals('供应商不存在', $data['msg']);
    }

    /**
     * 创建测试供应商
     */
    private function createTestSupplier(string $supplierName = null): Supplier
    {
        $data = [
            'supplier_name' => $supplierName ?: '测试控制器供应商_' . time(),
            'status' => 1,
            'prepayment_check' => 0,
            'remark' => '测试备注',
            'prepayment_total_cent' => 0,
            'prepayment_remaining_cent' => 0,
            'withdrawable_balance_cent' => 0,
            'today_receipt_cent' => 0,
            'telegram_chat_id' => 0
        ];

        return Supplier::create($data);
    }

    /**
     * 创建模拟Request对象
     */
    private function createMockRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('all')->willReturn($data);
        $request->method('input')->willReturnCallback(function ($key, $default = null) use ($data) {
            return $data[$key] ?? $default;
        });

        return $request;
    }

    /**
     * 创建模拟Request对象（带路由参数）
     */
    private function createMockRequestWithRoute(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('all')->willReturn($data);
        $request->method('input')->willReturnCallback(function ($key, $default = null) use ($data) {
            return $data[$key] ?? $default;
        });
        $request->method('route')->willReturnCallback(function ($key, $default = null) use ($data) {
            return $data[$key] ?? $default;
        });

        return $request;
    }
}






