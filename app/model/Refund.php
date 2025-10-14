<?php

namespace app\model;

use support\Model;

class Refund extends Model
{
    protected $table = 'refund';
    
    protected $fillable = [
        'refund_no',
        'order_id',
        'amount',
        'status',
        'reason',
        'third_party_refund_no',
        'third_party_response'
    ];

    protected $casts = [
        'third_party_response' => 'array',
        'amount' => 'integer',
        'status' => 'integer'
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // 退款状态常量
    const STATUS_PROCESSING = 1;   // 退款中
    const STATUS_SUCCESS = 2;      // 退款成功
    const STATUS_FAILED = 3;       // 退款失败

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // 获取状态文本
    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PROCESSING => '退款中',
            self::STATUS_SUCCESS => '退款成功',
            self::STATUS_FAILED => '退款失败'
        ];
        return $statusMap[$this->status] ?? '未知状态';
    }
}
