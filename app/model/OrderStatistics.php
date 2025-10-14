<?php

namespace app\model;

use support\Model;

/**
 * 订单统计汇总模型
 * 用于存储预计算的统计数据，提升查询性能
 */
class OrderStatistics extends Model
{
    // 指定数据库表名
    protected $table = 'fourth_party_payment_order_statistics';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'merchant_id',
        'stat_date',
        'total_orders',
        'pending_orders',
        'paid_orders',
        'failed_orders',
        'cancelled_orders',
        'total_amount',
        'pending_amount',
        'paid_amount',
        'failed_amount',
        'cancelled_amount'
    ];

    // 字段类型转换
    protected $casts = [
        'merchant_id' => 'integer',
        'stat_date' => 'date',
        'total_orders' => 'integer',
        'pending_orders' => 'integer',
        'paid_orders' => 'integer',
        'failed_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'total_amount' => 'integer',
        'pending_amount' => 'integer',
        'paid_amount' => 'integer',
        'failed_amount' => 'integer',
        'cancelled_amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联商户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    /**
     * 获取成功率
     * @return float
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0.0;
        }
        
        return round(($this->paid_orders / $this->total_orders) * 100, 2);
    }

    /**
     * 获取失败率
     * @return float
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0.0;
        }
        
        return round(($this->failed_orders / $this->total_orders) * 100, 2);
    }

    /**
     * 获取取消率
     * @return float
     */
    public function getCancellationRateAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0.0;
        }
        
        return round(($this->cancelled_orders / $this->total_orders) * 100, 2);
    }

    /**
     * 获取平均订单金额
     * @return float
     */
    public function getAverageOrderAmountAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0.0;
        }
        
        return round($this->total_amount / $this->total_orders, 2);
    }

    /**
     * 获取已支付平均订单金额
     * @return float
     */
    public function getAveragePaidAmountAttribute(): float
    {
        if ($this->paid_orders <= 0) {
            return 0.0;
        }
        
        return round($this->paid_amount / $this->paid_orders, 2);
    }

    /**
     * 按商户ID获取最新统计
     * @param int $merchantId
     * @return OrderStatistics|null
     */
    public static function getLatestByMerchant(int $merchantId): ?OrderStatistics
    {
        return self::where('merchant_id', $merchantId)
            ->orderBy('stat_date', 'desc')
            ->first();
    }

    /**
     * 按日期范围获取统计
     * @param string $startDate
     * @param string $endDate
     * @param int|null $merchantId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByDateRange(string $startDate, string $endDate, ?int $merchantId = null)
    {
        $query = self::whereBetween('stat_date', [$startDate, $endDate]);
        
        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }
        
        return $query->orderBy('stat_date', 'asc')->get();
    }

    /**
     * 获取汇总统计
     * @param string $startDate
     * @param string $endDate
     * @param int|null $merchantId
     * @return array
     */
    public static function getSummaryStats(string $startDate, string $endDate, ?int $merchantId = null): array
    {
        $query = self::whereBetween('stat_date', [$startDate, $endDate]);
        
        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }
        
        $stats = $query->selectRaw('
            SUM(total_orders) as total_orders,
            SUM(pending_orders) as pending_orders,
            SUM(paid_orders) as paid_orders,
            SUM(failed_orders) as failed_orders,
            SUM(cancelled_orders) as cancelled_orders,
            SUM(total_amount) as total_amount,
            SUM(pending_amount) as pending_amount,
            SUM(paid_amount) as paid_amount,
            SUM(failed_amount) as failed_amount,
            SUM(cancelled_amount) as cancelled_amount
        ')->first();
        
        if (!$stats) {
            return [
                'total_orders' => 0,
                'pending_orders' => 0,
                'paid_orders' => 0,
                'failed_orders' => 0,
                'cancelled_orders' => 0,
                'total_amount' => 0,
                'pending_amount' => 0,
                'paid_amount' => 0,
                'failed_amount' => 0,
                'cancelled_amount' => 0,
                'success_rate' => 0.0
            ];
        }
        
        $totalOrders = (int)$stats->total_orders;
        $paidOrders = (int)$stats->paid_orders;
        
        return [
            'total_orders' => $totalOrders,
            'pending_orders' => (int)$stats->pending_orders,
            'paid_orders' => $paidOrders,
            'failed_orders' => (int)$stats->failed_orders,
            'cancelled_orders' => (int)$stats->cancelled_orders,
            'total_amount' => (int)$stats->total_amount,
            'pending_amount' => (int)$stats->pending_amount,
            'paid_amount' => (int)$stats->paid_amount,
            'failed_amount' => (int)$stats->failed_amount,
            'cancelled_amount' => (int)$stats->cancelled_amount,
            'success_rate' => $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 2) : 0.0
        ];
    }
}
