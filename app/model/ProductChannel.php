<?php

namespace app\model;

use support\Model;

/**
 * 产品通道关联模型
 * 用于管理产品与支付通道的轮询池关系
 */
class ProductChannel extends Model
{
    // 指定数据库表名
    protected $table = 'product_channel';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'product_id',
        'channel_id',
        'status',
    ];

    // 字段类型转换
    protected $casts = [
        'product_id' => 'integer',
        'channel_id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 状态常量
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 关联产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * 关联支付通道
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function channel()
    {
        return $this->belongsTo(PaymentChannel::class, 'channel_id', 'id');
    }
}
