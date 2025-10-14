<?php
namespace app\model;

use support\Model;

class Merchant extends Model
{
    protected $table = 'merchant';
    protected $primaryKey = 'id';
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'merchant_name',
        'status',
        'is_deleted',
        'contact_person',
        'contact_phone',
        'contact_email',
        'remark',
        'login_account',
        'withdrawable_amount',
        'frozen_amount',
        'prepayment_total',
        'prepayment_remaining',
        'withdraw_fee',
        'admin_id',
        'withdraw_config_type',
        'withdraw_rate',
        'merchant_key',
        'merchant_secret',
        'whitelist_ips',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'status' => 'integer',
        'is_deleted' => 'integer',
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

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    // 关联产品分配
    public function productAssignments()
    {
        return $this->hasMany(ProductMerchant::class, 'merchant_id', 'id');
    }

    // 关联管理员
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }
}