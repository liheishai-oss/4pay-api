<?php

namespace app\model;

use support\Model;

class OrderQueryTrace extends Model
{
    // 指定数据库表名
    protected $table = 'order_query_traces';

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
        'query_type',
        'step_name',
        'step_status',
        'step_data',
        'duration_ms'
    ];

    // 字段类型转换
    protected $casts = [
        'step_data' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 步骤状态常量
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';

    // 查询类型常量
    const QUERY_BY_ORDER_NO = 'by_order_no';
    const QUERY_BY_TRACE_ID = 'by_trace_id';
    const QUERY_BY_MERCHANT_ORDER_NO = 'by_merchant_order_no';

    // 查询步骤名称常量
    const STEP_QUERY_REQUEST = 'query_request';
    const STEP_PARAM_VALIDATED = 'param_validated';
    const STEP_ORDER_FOUND = 'order_found';
    const STEP_ORDER_NOT_FOUND = 'order_not_found';
    const STEP_RESPONSE_FORMATTED = 'response_formatted';
    const STEP_QUERY_COMPLETED = 'query_completed';

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
     * 查询指定查询类型
     */
    public function scopeByQueryType($query, string $queryType)
    {
        return $query->where('query_type', $queryType);
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
     * 获取查询类型文本
     */
    public function getQueryTypeTextAttribute()
    {
        $typeMap = [
            self::QUERY_BY_ORDER_NO => '按订单号查询',
            self::QUERY_BY_TRACE_ID => '按追踪ID查询',
            self::QUERY_BY_MERCHANT_ORDER_NO => '按商户订单号查询'
        ];
        return $typeMap[$this->query_type] ?? $this->query_type;
    }

    /**
     * 获取步骤名称文本
     */
    public function getStepNameTextAttribute()
    {
        $nameMap = [
            self::STEP_QUERY_REQUEST => '查询请求',
            self::STEP_PARAM_VALIDATED => '参数验证',
            self::STEP_ORDER_FOUND => '订单找到',
            self::STEP_ORDER_NOT_FOUND => '订单未找到',
            self::STEP_RESPONSE_FORMATTED => '响应格式化',
            self::STEP_QUERY_COMPLETED => '查询完成'
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
}
