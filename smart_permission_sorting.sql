-- 智能化权限排序SQL脚本
-- 按照功能用途重新组织权限结构，提高用户体验和系统可维护性

-- 1. 核心业务模块（最高优先级）
UPDATE fourth_party_payment_permission_rule SET weight = 100 WHERE rule = 'dashboard' AND parent_id = 0;
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'order:home' AND parent_id = 0;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'merchant:home' AND parent_id = 0;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'supplier:home' AND parent_id = 0;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'product:home' AND parent_id = 0;
UPDATE fourth_party_payment_permission_rule SET weight = 50 WHERE rule = 'channel:home' AND parent_id = 0;

-- 2. 财务管理模块（高优先级）
UPDATE fourth_party_payment_permission_rule SET weight = 40 WHERE rule = 'finance:home' AND parent_id = 0;

-- 3. 系统管理模块（中优先级）
UPDATE fourth_party_payment_permission_rule SET weight = 30 WHERE rule = 'admin' AND parent_id = 0;

-- 4. 机器人管理模块（中优先级）
UPDATE fourth_party_payment_permission_rule SET weight = 20 WHERE rule = 'telegram:home' AND parent_id = 0;

-- 5. 订单管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'order:list' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'order:detail' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'order:query' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'order:resend' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'order:callback' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'order:edit' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'order:add' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 55 WHERE rule = 'order:export' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 50 WHERE rule = 'order:delete' AND parent_id = 313;
UPDATE fourth_party_payment_permission_rule SET weight = 45 WHERE rule = 'order:toggle_status' AND parent_id = 313;

-- 6. 商户管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'merchant:list' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'merchant:edit' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'merchant:add' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'merchant:toggle_status' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'merchant:product_management' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'merchant:api_docs' AND parent_id = 300;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'merchant:delete' AND parent_id = 300;

-- 7. 供应商管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'supplier:list' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'supplier:edit' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'supplier:add' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'supplier:toggle_status' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'supplier:delete' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'supplier:config' AND parent_id = 200;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'supplier:toggle_prepayment_check' AND parent_id = 200;

-- 8. 产品管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'product:list' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'product:edit' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'product:add' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'product:toggle_status' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'product:pool' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'product:assignment' AND parent_id = 220;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'product:delete' AND parent_id = 220;

-- 9. 通道管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'channel:list' AND parent_id = 210;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'channel:edit' AND parent_id = 210;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'channel:add' AND parent_id = 210;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'channel:toggle_status' AND parent_id = 210;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'channel:config' AND parent_id = 210;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'channel:delete' AND parent_id = 210;

-- 10. 财务管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'finance:supplier_balance_log:list' AND parent_id = 320;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'finance:supplier_balance_log:detail' AND parent_id = 320;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'finance:supplier_balance_log:export' AND parent_id = 320;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'finance:supplier_balance_log:statistics' AND parent_id = 320;

-- 11. 系统管理子权限排序（按重要性）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'admin:profile' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'admin:system' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'system:config' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'adminlog:manage' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'group: manage' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'admin_user: manage' AND parent_id = 76;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'rule:home' AND parent_id = 76;

-- 12. 系统管理子子权限排序
-- 用户管理
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'admin_user:index' AND parent_id = 102;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'admin_user:edit' AND parent_id = 102;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'admin_user:create' AND parent_id = 102;

-- 权限分组管理
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'group:list' AND parent_id = 92;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'group:edit' AND parent_id = 92;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'group:add' AND parent_id = 92;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'group:delete' AND parent_id = 92;

-- 权限规则管理
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'rule:list' AND parent_id = 97;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'rule:edit' AND parent_id = 97;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'rule:add' AND parent_id = 97;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'rule:delete' AND parent_id = 97;

-- 操作日志管理
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'adminlog:list' AND parent_id = 79;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'adminlog:delete' AND parent_id = 79;

-- 13. 机器人管理子权限排序（按使用频率）
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE rule = 'telegram_admin:list' AND parent_id = 230;
UPDATE fourth_party_payment_permission_rule SET weight = 85 WHERE rule = 'telegram_admin:edit' AND parent_id = 230;
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE rule = 'telegram_admin:add' AND parent_id = 230;
UPDATE fourth_party_payment_permission_rule SET weight = 75 WHERE rule = 'telegram_admin:toggle_status' AND parent_id = 230;
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE rule = 'telegram_admin:delete' AND parent_id = 230;
UPDATE fourth_party_payment_permission_rule SET weight = 65 WHERE rule = 'robot:monitor' AND parent_id = 229;
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE rule = 'robot:config' AND parent_id = 229;

-- 14. 更新所有菜单项的排序
UPDATE fourth_party_payment_permission_rule SET weight = 100 WHERE id = 1; -- 首页
UPDATE fourth_party_payment_permission_rule SET weight = 90 WHERE id = 313; -- 订单管理
UPDATE fourth_party_payment_permission_rule SET weight = 80 WHERE id = 300; -- 商户管理
UPDATE fourth_party_payment_permission_rule SET weight = 70 WHERE id = 200; -- 供应商管理
UPDATE fourth_party_payment_permission_rule SET weight = 60 WHERE id = 220; -- 产品管理
UPDATE fourth_party_payment_permission_rule SET weight = 50 WHERE id = 210; -- 通道管理
UPDATE fourth_party_payment_permission_rule SET weight = 40 WHERE id = 320; -- 财务管理
UPDATE fourth_party_payment_permission_rule SET weight = 30 WHERE id = 76;  -- 系统管理
UPDATE fourth_party_payment_permission_rule SET weight = 20 WHERE id = 229; -- 机器人管理

-- 15. 确保所有权限都有合理的排序
UPDATE fourth_party_payment_permission_rule SET weight = 0 WHERE weight IS NULL;

