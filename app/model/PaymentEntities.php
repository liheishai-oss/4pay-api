<?php

namespace app\model;

use support\Model;

class PaymentEntities extends Model
{
    protected $table = 'payment_entities';

    // 指定主键
    protected $primaryKey = 'id';

    // 定义可批量赋值的字段
    protected $fillable = [];

    // 定义日期字段
    protected $dates = [
        'gmt_complain',
        'gmt_overdue',
        'gmt_process',
        'gmt_risk_finish_time',
        'gmt_trade',
        'gmt_refund',
        'created_at',
        'updated_at',
    ];

    // 定义自定义方法来处理特定的查询
    public static function getComplaintsByStatus($status)
    {
        return self::where('status', $status)->get();
    }
}