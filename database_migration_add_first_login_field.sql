-- 添加首次登录标记字段
ALTER TABLE `admins` ADD COLUMN `is_first_login` TINYINT(1) DEFAULT 1 COMMENT '是否首次登录，1=是，0=否';
ALTER TABLE `admins` ADD COLUMN `password_changed_at` TIMESTAMP NULL COMMENT '密码最后修改时间';
