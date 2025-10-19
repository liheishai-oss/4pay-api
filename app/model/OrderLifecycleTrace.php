<?php

namespace app\model;

use support\Model;

class OrderLifecycleTrace extends Model
{
    // 指定数据库表名
    protected $table = 'order_lifecycle_traces';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'trace_id',
        'order_id',
        'order_no',
        'merchant_order_no',
        'merchant_id',
        'step_name',
        'step_status',
        'step_data',
        'duration_ms',
        'parent_step_id'
    ];

    // 字段类型转换
    protected $casts = [
        'step_data' => 'array',
        'duration_ms' => 'integer',
        'parent_step_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 步骤状态常量
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';

    // 生命周期步骤名称常量
    const STEP_ORDER_CREATED = 'order_created';
    const STEP_PARAM_VALIDATED = 'param_validated';
    const STEP_MERCHANT_VALIDATED = 'merchant_validated';
    const STEP_PRODUCT_VALIDATED = 'product_validated';
    const STEP_CHANNEL_SELECTED = 'channel_selected';
    const STEP_PAYMENT_INITIATED = 'payment_initiated';
    const STEP_PAYMENT_SUCCESS = 'payment_success';
    const STEP_PAYMENT_FAILED = 'payment_failed';
    const STEP_CALLBACK_SENT = 'callback_sent';
    const STEP_CALLBACK_SUCCESS = 'callback_success';
    const STEP_CALLBACK_FAILED = 'callback_failed';
    const STEP_ORDER_COMPLETED = 'order_completed';
    const STEP_ORDER_CLOSED = 'order_closed';
    const STEP_ORDER_TIMEOUT = 'order_timeout';

    /**
     * 时间格式转换
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s.u');
    }

    /**
     * 查询指定追踪ID的所有步骤
     */
    public function scopeByTraceId($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    /**
     * 查询指定订单的所有追踪
     */
    public function scopeByOrderId($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * 查询指定平台订单号的追踪
     */
    public function scopeByOrderNo($query, string $orderNo)
    {
        return $query->where('order_no', $orderNo);
    }

    /**
     * 查询指定商户订单号的追踪
     */
    public function scopeByMerchantOrderNo($query, string $merchantOrderNo)
    {
        return $query->where('merchant_order_no', $merchantOrderNo);
    }

    /**
     * 查询指定商户的所有追踪
     */
    public function scopeByMerchantId($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * 查询成功状态的步骤
     */
    public function scopeSuccess($query)
    {
        return $query->where('step_status', self::STATUS_SUCCESS);
    }

    /**
     * 查询失败状态的步骤
     */
    public function scopeFailed($query)
    {
        return $query->where('step_status', self::STATUS_FAILED);
    }

    /**
     * 查询待处理状态的步骤
     */
    public function scopePending($query)
    {
        return $query->where('step_status', self::STATUS_PENDING);
    }

    /**
     * 按创建时间排序
     */
    public function scopeOrderByCreated($query, string $order = 'asc')
    {
        return $query->orderBy('created_at', $order);
    }

    /**
     * 获取步骤状态文本
     */
    public function getStepStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败',
            self::STATUS_PENDING => '待处理'
        ];
        return $statusMap[$this->step_status] ?? '未知';
    }

    /**
     * 获取步骤名称文本
     */
    public function getStepNameTextAttribute()
    {
        $nameMap = [
            self::STEP_ORDER_CREATED => '订单创建',
            self::STEP_PARAM_VALIDATED => '参数验证',
            self::STEP_MERCHANT_VALIDATED => '商户验证',
            self::STEP_PRODUCT_VALIDATED => '产品验证',
            self::STEP_CHANNEL_SELECTED => '通道选择',
            self::STEP_PAYMENT_INITIATED => '支付发起',
            self::STEP_PAYMENT_SUCCESS => '支付成功',
            self::STEP_PAYMENT_FAILED => '支付失败',
            self::STEP_CALLBACK_SENT => '回调发送',
            self::STEP_CALLBACK_SUCCESS => '回调成功',
            self::STEP_CALLBACK_FAILED => '回调失败',
            self::STEP_ORDER_COMPLETED => '订单完成',
            self::STEP_ORDER_CLOSED => '订单关闭',
            self::STEP_ORDER_TIMEOUT => '订单超时'
        ];
        return $nameMap[$this->step_name] ?? $this->step_name;
    }

    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * 关联商户
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联父步骤
     */
    public function parentStep()
    {
        return $this->belongsTo(OrderLifecycleTrace::class, 'parent_step_id');
    }

    /**
     * 关联子步骤
     */
    public function childSteps()
    {
        return $this->hasMany(OrderLifecycleTrace::class, 'parent_step_id');
    }
}
