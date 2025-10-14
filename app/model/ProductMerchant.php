<?php
namespace app\model;

use support\Model;

class ProductMerchant extends Model
{
    protected $table = 'product_merchant';
    protected $primaryKey = 'id';
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'product_id',
        'merchant_id',
        'merchant_rate',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'merchant_id' => 'integer',
        'merchant_rate' => 'integer',
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

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    // 关联产品
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // 关联商户
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }
}
