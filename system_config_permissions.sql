-- 系统配置权限SQL脚本
-- 为系统配置功能添加完整的权限控制

-- 1. 系统配置主权限（如果不存在）
INSERT IGNORE INTO fourth_party_payment_permission_rule 
(rule, name, parent_id, weight, is_menu, icon, component, path, status, created_at, updated_at) 
VALUES 
('system:config', '系统配置', 76, 80, 1, 'el-icon-setting', 'system/config', '/system/config', 1, NOW(), NOW());

-- 2. 获取系统配置主权限ID
SET @system_config_id = (SELECT id FROM fourth_party_payment_permission_rule WHERE rule = 'system:config' AND parent_id = 76);

-- 3. 系统配置子权限
INSERT IGNORE INTO fourth_party_payment_permission_rule 
(rule, name, parent_id, weight, is_menu, status, created_at, updated_at) 
VALUES 
('system:config:list', '查看配置', @system_config_id, 90, 0, 1, NOW(), NOW()),
('system:config:save', '保存配置', @system_config_id, 85, 0, 1, NOW(), NOW()),
('system:config:reset', '重置配置', @system_config_id, 80, 0, 1, NOW(), NOW()),
('system:config:groups', '配置分组', @system_config_id, 75, 0, 1, NOW(), NOW());

-- 4. 为超级管理员组分配所有系统配置权限
INSERT IGNORE INTO fourth_party_payment_admin_group_rule 
(group_id, rule_id, created_at, updated_at) 
SELECT 1, id, NOW(), NOW() 
FROM fourth_party_payment_permission_rule 
WHERE rule IN ('system:config', 'system:config:list', 'system:config:save', 'system:config:reset', 'system:config:groups');

-- 5. 更新权限排序
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'system:config' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'system:config:list' AND parent_id = @system_config_id;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'system:config:save' AND parent_id = @system_config_id;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'system:config:reset' AND parent_id = @system_config_id;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'system:config:groups' AND parent_id = @system_config_id;

-- 6. 确保系统管理模块的排序
UPDATE fourth_party_payment_permission_rule SET weight = 30 WHERE id = 76; -- 系统管理主模块
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'system:config' AND parent_id = 76; -- 系统配置

-- 7. 显示插入结果
SELECT 
    '系统配置权限添加完成' as message,
    COUNT(*) as total_permissions
FROM fourth_party_payment_permission_rule 
WHERE rule LIKE 'system:config%';

