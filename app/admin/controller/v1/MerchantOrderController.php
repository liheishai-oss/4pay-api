<?php

namespace app\admin\controller\v1;

use app\common;
use app\model\PaymentOrder;
use app\model\PaymentSystemLog;

use support\Request;
use support\Response;

class MerchantOrderController
{
    public function index(Request $request): Response
    {
        $param = $request->all();

        $user_id = $request->userData['admin_id'];
        $where = [];
        if($user_id != common::ADMIN_USER_ID) {
            $where = [
                'tenant_id' => $user_id,
            ];
        }

        $query = PaymentOrder::query();
        $query->with('paymentSubject', 'tenant');
        $list = $query->where($where)->orderByDesc('id')->paginate($param['page_size'])->toArray();

        return success($list);
    }

    public function logs(Request $request): Response
    {
        $param = $request->all();

        $user_id = $request->userData['admin_id'];
        $where = [];
        if($user_id != common::ADMIN_USER_ID) {
            $where = [
                'tenant_id' => $user_id,
            ];
        }

        $query = PaymentSystemLog::query();
        $list = $query->where($where)->orderByDesc('id')->paginate($param['page_size'])->toArray();

        return success($list);
    }
}