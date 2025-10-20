<?php

namespace app\model;

use support\Model;

class Order extends Model
{
    protected $table = 'order';
    
    protected $fillable = [
        'order_no',
        'merchant_order_no',
        'third_party_order_no',
        'trace_id',
        'merchant_id',
        'product_id',
        'channel_id',
        'amount',
        'fee',
        'status',
        'payment_method',
        'notify_url',
        'return_url',
        'client_ip',
        'terminal_ip',
        'user_agent',
        'subject',
        'body',
        'extra_data',
        'third_party_response',
        'notify_count',
        'notify_status',
        'expire_time',
        'paid_time'
    ];
    protected $casts = [
        'extra_data' => 'array',
        'third_party_response' => 'array',
        'expire_time' => 'datetime',
        'paid_time' => 'datetime',
        'amount' => 'integer',
        'fee' => 'integer',
        'notify_count' => 'integer',
        'notify_status' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // 订单状态常量
    const STATUS_PENDING = 1;      // 待支付
    const STATUS_PAYING = 2;       // 支付中
    const STATUS_SUCCESS = 3;      // 支付成功
    const STATUS_FAILED = 4;       // 支付失败
    const STATUS_REFUNDED = 5;     // 已退款
    const STATUS_CLOSED = 6;       // 已关闭

    // 通知状态常量
    const NOTIFY_STATUS_NONE = 0;      // 未通知
    const NOTIFY_STATUS_SUCCESS = 1;   // 通知成功
    const NOTIFY_STATUS_FAILED = 2;    // 通知失败

    // 关联商户
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    // 关联产品
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // 关联通道
    public function channel()
    {
        return $this->belongsTo(PaymentChannel::class, 'channel_id', 'id');
    }

    // 关联退款记录
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'order_id', 'id');
    }

    // 关联通知日志
    public function notifyLogs()
    {
        return $this->hasMany(NotifyLog::class, 'order_id', 'id');
    }

    // 获取状态文本
    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAYING => '支付中',
            self::STATUS_SUCCESS => '支付成功',
            self::STATUS_FAILED => '支付失败',
            self::STATUS_REFUNDED => '已退款',
            self::STATUS_CLOSED => '已关闭'
        ];
        return $statusMap[$this->status] ?? '未知状态';
    }

    // 获取通知状态文本
    public function getNotifyStatusTextAttribute()
    {
        $statusMap = [
            self::NOTIFY_STATUS_NONE => '未通知',
            self::NOTIFY_STATUS_SUCCESS => '通知成功',
            self::NOTIFY_STATUS_FAILED => '通知失败'
        ];
        return $statusMap[$this->notify_status] ?? '未知状态';
    }
}
