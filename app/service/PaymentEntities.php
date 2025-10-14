<?php

namespace app\service;
use app\model\PaymentEntities as PaymentEntitiesModel;

class PaymentEntities
{
    public static function save(array $param)
    {
        if ($param['id'] == 0) {
            // 新增操作
            $paymentEntity = new PaymentEntitiesModel();
        } else {
            // 更新操作
            $paymentEntity = PaymentEntitiesModel::find($param['id']);
            if (!$paymentEntity) {
                throw new \Exception('支付主体不存在');
            }
        }


        $paymentEntity->entity_name = $param['entity_name'];
        $paymentEntity->custom_product_name = $param['custom_product_name'];
        $paymentEntity->entity_status = $param['entity_status'];
        $paymentEntity->settlement_method = $param['settlement_method'];
        $paymentEntity->settlement_ratio = $param['settlement_ratio'];
        $paymentEntity->entity_type = $param['entity_type'];
        $paymentEntity->settlement_mode = $param['settlement_mode'];
        $paymentEntity->product_id = $param['product_id'];
        $paymentEntity->weight = $param['weight']??0;
        $paymentEntity->remark = $param['remark'];
        $paymentEntity->appid = $param['appid'];
        $paymentEntity->app_private_key = $param['app_private_key'];
        $paymentEntity->pid = $param['pid'];
        $paymentEntity->max_buy_number = $param['max_buy_number'];
        $paymentEntity->min_money = $param['min_money'];
        $paymentEntity->max_money = $param['max_money'];
        $paymentEntity->alipay_cert_public_key = $param['alipay_cert_public_key'];
        $paymentEntity->alipay_root_cert = $param['alipay_root_cert'];
        $paymentEntity->app_cert_public_key = $param['app_cert_public_key'];
        $paymentEntity->ip_restriction = $param['ip_restriction'];
        $paymentEntity->device_verification = $param['device_verification'];
        $paymentEntity->qr_scan_enabled = $param['qr_scan_enabled'];

        // 保存数据（如果是更新则会自动覆盖原数据）
        $paymentEntity->save();
    }
}