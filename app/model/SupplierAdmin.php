<?php

namespace app\model;

use support\Model;

class SupplierAdmin extends Model
{
    // 指定数据库表名（去掉fourth_party_payment前缀）
    protected $table = 'supplier_admin';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null; // 这个表没有updated_at字段

    // 可填充字段
    protected $fillable = [
        'supplier_id',
        'telegram_user_id'
    ];

    // 字段类型转换
    protected $casts = [
        'supplier_id' => 'integer',
        'telegram_user_id' => 'integer',
        'created_at' => 'datetime'
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * 关联Telegram管理员
     */
    public function telegramAdmin()
    {
        return $this->belongsTo(TelegramAdmin::class, 'telegram_user_id', 'id');
    }
}





