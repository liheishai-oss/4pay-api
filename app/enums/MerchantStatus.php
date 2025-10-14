<?php

namespace app\enums;

class MerchantStatus
{
    // 商户状态
    const DISABLED = 0;  // 禁用
    const ENABLED = 1;   // 启用

    /**
     * 获取状态文本
     * @param int $status
     * @return string
     */
    public static function getText(int $status): string
    {
        $statusMap = [
            self::DISABLED => '禁用',
            self::ENABLED => '启用'
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
            self::DISABLED => '禁用',
            self::ENABLED => '启用'
        ];
    }

    /**
     * 验证状态是否有效
     * @param int $status
     * @return bool
     */
    public static function isValid(int $status): bool
    {
        return in_array($status, [self::DISABLED, self::ENABLED]);
    }
}

