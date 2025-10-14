-- 添加订单有效期配置到系统配置表
-- 用于控制订单失败时间

INSERT INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`, `is_public`, `created_at`, `updated_at`) 
VALUES (
    'payment.order_validity_minutes', 
    '30', 
    'number', 
    '订单有效期（分钟），超过此时间订单将自动失败', 
    0, 
    NOW(), 
    NOW()
) ON DUPLICATE KEY UPDATE 
    `config_value` = VALUES(`config_value`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- 查看插入结果
SELECT * FROM `system_config` WHERE `config_key` = 'payment.order_validity_minutes';



