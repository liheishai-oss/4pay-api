<?php

namespace app\model;

use support\Model;

class Supplier extends Model
{
    // 指定数据库表名
    protected $table = 'supplier';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'supplier_name',
        'interface_code',
        'status',
        'prepayment_check',
        'remark',
        'telegram_chat_id',
        'callback_whitelist_ips',
        'is_deleted',
        'deleted_at'
    ];

    // 字段类型转换
    protected $casts = [
        'status' => 'integer',
        'prepayment_check' => 'integer',
        'telegram_chat_id' => 'integer',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
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

    // 状态常量
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    // 预付检验常量
    const PREPAY_CHECK_NOT_REQUIRED = 0;
    const PREPAY_CHECK_REQUIRED = 1;
    
    // 软删除常量
    const NOT_DELETED = 0;
    const DELETED = 1;
    
    /**
     * 查询未删除的供应商
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', self::NOT_DELETED);
    }
    
    /**
     * 查询已删除的供应商
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', self::DELETED);
    }
}
