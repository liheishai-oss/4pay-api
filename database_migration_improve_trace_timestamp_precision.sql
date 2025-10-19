-- 提高链路追踪表的时间戳精度
-- 将 TIMESTAMP 改为支持微秒精度的 DATETIME(6)

-- 修改订单生命周期追踪表
ALTER TABLE `fourth_party_payment_order_lifecycle_traces` 
MODIFY COLUMN `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间(微秒精度)',
MODIFY COLUMN `updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间(微秒精度)';

-- 修改订单查询追踪表
ALTER TABLE `fourth_party_payment_order_query_traces` 
MODIFY COLUMN `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间(微秒精度)',
MODIFY COLUMN `updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间(微秒精度)';

-- 删除可能存在的索引，然后重新添加
ALTER TABLE `fourth_party_payment_order_lifecycle_traces` 
DROP INDEX IF EXISTS `idx_created_at`;

ALTER TABLE `fourth_party_payment_order_lifecycle_traces` 
ADD INDEX `idx_created_at` (`created_at`);

ALTER TABLE `fourth_party_payment_order_query_traces` 
DROP INDEX IF EXISTS `idx_created_at`;

ALTER TABLE `fourth_party_payment_order_query_traces` 
ADD INDEX `idx_created_at` (`created_at`);

-- 显示修改结果
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN ('fourth_party_payment_order_lifecycle_traces', 'fourth_party_payment_order_query_traces')
    AND COLUMN_NAME IN ('created_at', 'updated_at')
ORDER BY TABLE_NAME, COLUMN_NAME;
