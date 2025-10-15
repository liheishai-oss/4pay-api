-- 移除商户表中的提现配置字段
-- 执行前请备份数据库

-- 移除提现配置相关字段
ALTER TABLE `fourth_party_payment_merchant` 
DROP COLUMN IF EXISTS `withdraw_fee`,
DROP COLUMN IF EXISTS `withdraw_config_type`, 
DROP COLUMN IF EXISTS `withdraw_rate`;

-- 验证字段是否已删除
DESCRIBE `fourth_party_payment_merchant`;
