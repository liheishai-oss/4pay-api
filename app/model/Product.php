<?php

namespace app\model;

use support\Model;

class Product extends Model
{
    // 指定数据库表名
    protected $table = 'product';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'sort',
        'product_name',
        'external_code',
        'status',
        'default_rate_bp',
        'today_success_rate_bp',
        'bound_enabled_channel_count',
        'remark'
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // 字段类型转换
    protected $casts = [
        'sort' => 'integer',
        'status' => 'integer',
        'default_rate_bp' => 'integer',
        'today_success_rate_bp' => 'integer',
        'bound_enabled_channel_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 状态常量
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;
}

