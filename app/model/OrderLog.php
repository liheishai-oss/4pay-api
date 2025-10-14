<?php

namespace app\model;

use support\Model;

/**
 * 订单流转日志模型
 * @property int $id
 * @property int $order_id 订单ID
 * @property string $order_no 订单号
 * @property int $status 状态
 * @property string $action 操作动作
 * @property string $description 描述
 * @property string $operator_type 操作者类型 (admin, system, merchant)
 * @property int $operator_id 操作者ID
 * @property string $operator_name 操作者名称
 * @property string $ip_address IP地址
 * @property string $user_agent 用户代理
 * @property array $extra_data 扩展数据
 * @property string $created_at 创建时间
 */
class OrderLog extends Model
{
    protected $table = 'order_logs';
    
    protected $fillable = [
        'order_id',
        'order_no', 
        'status',
        'action',
        'description',
        'operator_type',
        'operator_id',
        'operator_name',
        'ip_address',
        'user_agent',
        'extra_data'
    ];

    protected $casts = [
        'extra_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 序列化日期格式
     */
    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * 记录订单日志
     */
    public static function log($orderId, $orderNo, $status, $action, $description, $operatorType = 'system', $operatorId = 0, $operatorName = '', $ipAddress = '', $userAgent = '', $extraData = [])
    {
        return self::create([
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'status' => $status,
            'action' => $action,
            'description' => $description,
            'operator_type' => $operatorType,
            'operator_id' => $operatorId,
            'operator_name' => $operatorName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'extra_data' => $extraData
        ]);
    }
}
