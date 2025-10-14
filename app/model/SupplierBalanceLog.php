<?php

namespace app\model;

use support\Model;

/**
 * 供应商余额变动记录模型
 */
class SupplierBalanceLog extends Model
{
    // 指定数据库表名
    protected $table = 'supplier_balance_log';

    // 指定主键
    protected $primaryKey = 'id';

    // 使用时间戳
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 可填充字段
    protected $fillable = [
        'supplier_id',
        'operation_type',
        'amount',
        'balance_before',
        'balance_after',
        'operator_type',
        'operator_id',
        'operator_name',
        'order_id',
        'order_no',
        'remark',
        'telegram_message',
        'ip_address',
        'user_agent'
    ];

    // 字段类型转换
    protected $casts = [
        'supplier_id' => 'integer',
        'operation_type' => 'integer',
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'operator_type' => 'integer',
        'operator_id' => 'integer',
        'order_id' => 'integer',
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

    // 操作类型常量
    const OPERATION_TYPE_PREPAYMENT = 1;      // 预付
    const OPERATION_TYPE_WITHDRAWAL = 2;      // 下发
    const OPERATION_TYPE_ORDER_DEDUCT = 3;    // 订单扣款
    const OPERATION_TYPE_REFUND = 4;          // 退款
    const OPERATION_TYPE_SYSTEM_ADJUST = 5;    // 系统调整

    // 操作人类型常量
    const OPERATOR_TYPE_ADMIN = 1;            // 管理员
    const OPERATOR_TYPE_SYSTEM = 2;           // 系统
    const OPERATOR_TYPE_ORDER = 3;            // 订单

    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 获取操作类型文本
     */
    public function getOperationTypeTextAttribute(): string
    {
        $types = [
            self::OPERATION_TYPE_PREPAYMENT => '预付',
            self::OPERATION_TYPE_WITHDRAWAL => '下发',
            self::OPERATION_TYPE_ORDER_DEDUCT => '订单扣款',
            self::OPERATION_TYPE_REFUND => '退款',
            self::OPERATION_TYPE_SYSTEM_ADJUST => '系统调整'
        ];

        return $types[$this->operation_type] ?? '未知';
    }

    /**
     * 获取操作人类型文本
     */
    public function getOperatorTypeTextAttribute(): string
    {
        $types = [
            self::OPERATOR_TYPE_ADMIN => '管理员',
            self::OPERATOR_TYPE_SYSTEM => '系统',
            self::OPERATOR_TYPE_ORDER => '订单'
        ];

        return $types[$this->operator_type] ?? '未知';
    }

    /**
     * 获取金额（元）
     */
    public function getAmountYuanAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * 获取变动前余额（元）
     */
    public function getBalanceBeforeYuanAttribute(): float
    {
        return $this->balance_before / 100;
    }

    /**
     * 获取变动后余额（元）
     */
    public function getBalanceAfterYuanAttribute(): float
    {
        return $this->balance_after / 100;
    }

    /**
     * 按操作类型查询
     */
    public function scopeByOperationType($query, int $operationType)
    {
        return $query->where('operation_type', $operationType);
    }

    /**
     * 按供应商查询
     */
    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * 按时间范围查询
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 按操作人查询
     */
    public function scopeByOperator($query, int $operatorType, int $operatorId)
    {
        return $query->where('operator_type', $operatorType)
                    ->where('operator_id', $operatorId);
    }
}

