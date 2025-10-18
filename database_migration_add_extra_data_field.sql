-- 为订单表添加扩展数据字段
-- 执行前请备份数据库

-- 添加扩展数据字段
ALTER TABLE `order` 
ADD COLUMN `extra_data` TEXT COMMENT '扩展数据，JSON格式存储' 
AFTER `body`;

-- 验证字段是否已添加
DESCRIBE `order`;
