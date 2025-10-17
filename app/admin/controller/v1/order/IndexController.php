<?php

namespace app\admin\controller\v1\order;

use app\service\order\IndexService;
use support\Request;
use support\Response;

class IndexController
{
	// 必须登录
	protected array $noNeedLogin = [];

	private IndexService $indexService;

	public function __construct(IndexService $indexService)
	{
		$this->indexService = $indexService;
	}

	/**
	 * 获取订单列表
	 * @param Request $request
	 * @return Response
	 */
	public function index(Request $request): Response
	{
		try {
			$params = $request->all();

			// 商户登录：仅展示所属商户订单
			if (isset($params['merchant_id'])) {
				unset($params['merchant_id']);
			}
			if (isset($request->userData) && !empty($request->userData['is_merchant_admin']) && $request->userData['is_merchant_admin']) {
				$adminId = $request->userData['admin_id'] ?? null;
				if ($adminId) {
					$merchantId = \app\model\Merchant::where('admin_id', $adminId)->value('id');
					if (!empty($merchantId)) {
						$params['merchant_id'] = $merchantId;
					}
				}
			}

			$result = $this->indexService->getOrderList($params);

			return success($result->toArray());
		} catch (\Exception $e) {
			return error($e->getMessage());
		}
	}

	/**
	 * 补单
	 * @param Request $request
	 * @return Response
	 */
	public function reissue(Request $request): Response
	{
		try {
			$orderId = $request->post('order_id');
			if (!$orderId) {
				return error('订单ID不能为空');
			}

			$result = $this->indexService->reissueOrder($orderId);
			return success($result, '补单成功');
		} catch (\Exception $e) {
			return error($e->getMessage());
		}
	}

	/**
	 * 回调
	 * @param Request $request
	 * @return Response
	 */
	public function callback(Request $request): Response
	{
		try {
			$orderId = $request->post('order_id');
			if (!$orderId) {
				return error('订单ID不能为空');
			}

			$result = $this->indexService->callbackOrder($orderId);
			return success($result, '回调成功');
		} catch (\Exception $e) {
			return error($e->getMessage());
		}
	}

	/**
	 * 批量补单
	 * @param Request $request
	 * @return Response
	 */
	public function batchReissue(Request $request): Response
	{
		try {
			$orderIds = $request->post('order_ids');
			if (!$orderIds || !is_array($orderIds)) {
				return error('订单ID列表不能为空');
			}

			$result = $this->indexService->batchReissueOrders($orderIds);
			return success($result, '批量补单成功');
		} catch (\Exception $e) {
			return error($e->getMessage());
		}
	}

	/**
	 * 批量回调
	 * @param Request $request
	 * @return Response
	 */
	public function batchCallback(Request $request): Response
	{
		try {
			$orderIds = $request->post('order_ids');
			if (!$orderIds || !is_array($orderIds)) {
				return error('订单ID列表不能为空');
			}

			$result = $this->indexService->batchCallbackOrders($orderIds);
			return success($result, '批量回调成功');
		} catch (\Exception $e) {
			return error($e->getMessage());
		}
	}
}
