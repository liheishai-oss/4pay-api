<?php

namespace app\model;

use support\Model;

class PaymentOrder extends Model
{
    // 指定数据库表名
    protected $table = 'payment_orders';

    // 指定主键
    protected $primaryKey = 'id';

    // 不使用时间戳
    public $timestamps = false;

    // 可填充字段
    protected $fillable = [
        'merchant_id',
        'order_no',
        'amount',
        'status',
        'merchant_transaction_id',
        'third_party_transaction_id',
        'notification_status'
    ];

    // 支付主体关联
    public function paymentSubject()
    {
        return $this->belongsTo(PaymentEntities::class, 'payment_subject_id', 'id');
    }

}
