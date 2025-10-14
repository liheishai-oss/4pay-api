<?php

namespace app\enums;

class OrderStatus
{
    // 订单状态
    const PENDING = 1;      // 待支付
    const PAYING = 2;        // 支付中
    const SUCCESS = 3;       // 支付成功
    const FAILED = 4;        // 支付失败
    const REFUNDED = 5;      // 已退款
    const CLOSED = 6;        // 已关闭

    /**
     * 获取状态文本
     * @param int $status
     * @return string
     */
    public static function getText(int $status): string
    {
        $statusMap = [
            self::PENDING => '待支付',
            self::PAYING => '支付中',
            self::SUCCESS => '支付成功',
            self::FAILED => '支付失败',
            self::REFUNDED => '已退款',
            self::CLOSED => '已关闭'
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取所有状态
     * @return array
     */
    public static function getAll(): array
    {
        return [
            self::PENDING => '待支付',
            self::PAYING => '支付中',
            self::SUCCESS => '支付成功',
            self::FAILED => '支付失败',
            self::REFUNDED => '已退款',
            self::CLOSED => '已关闭'
        ];
    }

    /**
     * 验证状态是否有效
     * @param int $status
     * @return bool
     */
    public static function isValid(int $status): bool
    {
        return in_array($status, [
            self::PENDING, self::PAYING, self::SUCCESS, 
            self::FAILED, self::REFUNDED, self::CLOSED
        ]);
    }
}

