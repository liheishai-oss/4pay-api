<?php

namespace app\model;

use support\Model;
use app\common\helpers\MoneyHelper;

class PaymentChannel extends Model
{
    protected $table = 'channel';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'channel_name',
        'supplier_id',
        'product_code',
        'status',
        'weight',
        'min_amount',
        'max_amount',
        'cost_rate',
        'remark',
        'basic_params',
    ];

    protected $casts = [
        'id' => 'integer',
        'supplier_id' => 'integer',
        'status' => 'integer',
        'weight' => 'integer',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'cost_rate' => 'integer',
        'basic_params' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 时间格式转换 - 解决新版ORM时间格式问题
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // Status constants
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * 格式化金额（分转元）
     * @param int $cent
     * @return string
     */
    public function formatAmount(int $cent): string
    {
        return MoneyHelper::convertToYuan($cent);
    }

    /**
     * 格式化费率（百分比显示）
     * @param int $rate
     * @return string
     */
    public function formatRate(int $rate): string
    {
        return number_format($rate, 2) . '%';
    }

    /**
     * 获取状态文本
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status === self::STATUS_ENABLED ? '启用' : '禁用';
    }

}
