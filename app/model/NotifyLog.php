<?php

namespace app\model;

use support\Model;

class NotifyLog extends Model
{
    protected $table = 'notify_log';
    
    protected $fillable = [
        'order_id',
        'notify_url',
        'request_data',
        'response_data',
        'http_code',
        'status',
        'retry_count'
    ];

    protected $casts = [
        'request_data' => 'array',
        'http_code' => 'integer',
        'status' => 'integer',
        'retry_count' => 'integer'
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // 通知状态常量
    const STATUS_SUCCESS = 1;  // 成功
    const STATUS_FAILED = 0;   // 失败

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // 获取状态文本
    public function getStatusTextAttribute()
    {
        return $this->status == self::STATUS_SUCCESS ? '成功' : '失败';
    }

    // 获取HTTP状态码文本
    public function getHttpCodeTextAttribute()
    {
        $httpCodes = [
            200 => 'OK',
            201 => 'Created',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];
        return $httpCodes[$this->http_code] ?? 'Unknown';
    }
}
