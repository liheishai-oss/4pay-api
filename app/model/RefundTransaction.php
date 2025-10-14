<?php

namespace app\model;

use support\Model;

class RefundTransaction extends Model
{
    protected $table = 'payment_refund_transaction'; // 数据表名
    public $timestamps = true;
    protected $fillable = [
        'tenant_id',
        'platform_order_no',
        'merchant_order_no',
        'transaction_amount',
        'refund_amount',
        'remark',];
}
