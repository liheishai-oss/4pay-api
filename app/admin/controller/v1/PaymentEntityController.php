<?php

namespace app\admin\controller\v1;

use app\common;
use app\exception\MyBusinessException;
use app\model\PaymentEntities as PaymentEntity;
use app\service\PaymentEntities;
use Respect\Validation\Validator;
use support\Request;
use support\Response;

class PaymentEntityController
{
//    protected array $noNeedLogin = ['save'];
    public function index(Request $request)
    {
        $param = $request->all();

        // 检查用户数据
        if (!isset($request->userData) || !is_array($request->userData)) {
            return error('用户未登录或登录信息无效', 401);
        }
        
        $user_id = $request->userData['admin_id'] ?? null;
        if (!$user_id) {
            return error('用户信息不完整', 401);
        }
        $data = PaymentEntity::orderBy('id','DESC')->paginate($param['page_size'])->toArray();
        return success($data);
    }
    public function demo(Request $request)
    {
        $param = $request->all();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL =>        'http://mclient.dev.alipay.lu:8081/v1/alipay/order/create?debug=1&entities_id='.$param['id'].'&n='.$param['notify_url'].'&m='.$param['payment_amount'].'&p='.$param['product_id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '',
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response,true);
        if(is_null($data) || $data['success'] != true){
            throw new MyBusinessException(is_null($data) ? '拉取失败' :$data['message']);
        }
        return success(['url'=>$data['data']['pay_url']]);
    }
    public function save(Request $request)
    {
        $param = $request->post();
//        try {
            $rules = [
                'entity_name'       => Validator::stringType()->notEmpty()->setName('主体名称'),
                'settlement_method' => Validator::in([0, 1, 2])->setName('分账方式'),
                'max_buy_number'    => Validator::intType()->min(1)->setName('最大购买数'),
                'min_money'         => Validator::floatVal()->min(0.01)->setName('最低付款金额'),
                'max_money'         => Validator::floatVal()->min(0.01)->setName('最大付款金额'),
                'product_id'        => Validator::intType()->min(1)->setName('支付产品'),
                'appid'             => Validator::stringType()->notEmpty()->setName('APPID'),
                'pid'               => Validator::stringType()->notEmpty()->setName('PID'),
                'app_private_key'   => Validator::stringType()->notEmpty()->setName('应用私钥'),
                'alipay_cert_public_key'    => Validator::stringType()->notEmpty()->setName('支付宝公钥证书'),
                'alipay_root_cert'          => Validator::stringType()->notEmpty()->setName('支付宝根证书'),
                'app_cert_public_key'       => Validator::stringType()->notEmpty()->setName('应用公钥证书'),
            ];

            // 动态添加 settlement_ratio 和 settlement_mode 的验证（如果开启分账）
            if (isset($param['settlement_method']) && (int)$param['settlement_method'] !== 0) {
                // 分账比例验证
                $rules['settlement_ratio'] = Validator::floatVal()->between(0, 100)->setName('分账比例');
                // 如果分账比例大于 0，验证分账模式
                if ($param['settlement_ratio'] > 0) {
                    $rules['settlement_mode'] = Validator::in([1, 2])->setName('分账模式');
                } else {
                    // 如果分账比例为 0，禁止分账
                    $param['settlement_method'] = 0;
                }
            }

            // 开始验证
            foreach ($rules as $field => $validator) {
                if (!isset($param[$field])) {
                    throw new \Exception("{$validator->getName()}不能为空");
                }
                if (!$validator->validate($param[$field])) {
                    throw new \Exception("{$validator->getName()}格式错误");
                }
            }
            $param['tenant_id'] = $user_id;
            $group_id = $request->userData['group_id'] ?? null;
            if($group_id != common::TENANT_GROUP_ID) {
//                throw new MyBusinessException('只有租户才能操作');
            }
            $service = new PaymentEntities();
            $service->save($param);

            return success();
//        }  catch (\Exception $e) {
//            Log::error('主体创建异常',[
//                'message' => $e->getMessage(),
//                'file'    => $e->getFile(),
//                'line'    => $e->getLine(),
//                'trace'   => $e->getTraceAsString(),
//            ]);
//            return error($e->getMessage());
//        }
    }

    public function destroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids');
            if (empty($ids)) {
                return error('缺少ID');
            }

            if (empty($ids)) {
                return error('无效的ID');
            }

            // 执行软删除
            PaymentEntity::whereIn('id', $ids)->update(['is_deleted' => 1]);

            return success([],'删除成功');
        } catch (\Throwable $e) {
            return error($e->getMessage());
        }
    }
    // 设置异地拉单开关
    public function setIpRestriction(Request $request)
    {
        $entity_id = $request->post('id');
        $entity = PaymentEntity::find($entity_id); // 查找对应的支付主体
        if (!$entity) {
            return error('主体信息错误');
        }

        $entity->ip_restriction = $entity->ip_restriction == 1 ? 0 : 1; // 更新支付主体的异地拉单状态
        $entity->save(); // 保存修改

        return success(); // 返回成功消息
    }

    // 设置设备校验开关
    public function setDeviceVerification(Request $request)
    {
        $entity_id = $request->post('id');
        $entity = PaymentEntity::find($entity_id); // 查找对应的支付主体
        if (!$entity) {
            return error('主体信息错误');
        }
        $entity->device_verification = $entity->device_verification == 1 ? 0 : 1; // 更新设备校验状态
        $entity->save(); // 保存修改

        return success(); // 返回成功消息
    }

    // 设置投诉查询开关
    public function setQueryComplaintsEnabled(Request $request)
    {
        $entity_id = $request->post('id');
        $entity = PaymentEntity::find($entity_id); // 查找对应的支付主体
        if (!$entity) {
            return error('主体信息错误'); // 如果没有找到支付主体，返回错误
        }

        $entity->query_complaints_enabled = $entity->query_complaints_enabled == 1 ? 0 : 1; // 更新投诉查询开关状态
        $entity->save(); // 保存修改

        return success(); // 返回成功消息
    }

    // 设置扫码开关
    public function setQrScanEnabled(Request $request)
    {
        $entity_id = $request->post('id');
        $entity = PaymentEntity::find($entity_id); // 查找对应的支付主体
        if (!$entity) {
            return error('主体信息错误');
        }

        $entity->qr_scan_enabled = $entity->qr_scan_enabled == 1 ? 0 : 1; // 更新扫码开关状态
        $entity->save(); // 保存修改

        return success(); // 返回成功消息
    }
}