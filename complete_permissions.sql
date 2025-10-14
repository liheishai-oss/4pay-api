-- 补全所有前端页面权限的SQL脚本
-- 基于现有权限结构，为所有Vue页面添加完整的权限控制

-- 1. 订单管理相关权限补全
INSERT INTO fourth_party_payment_permission_rule (title, parent_id, is_menu, rule, weight, status, remark, path, icon, has_children, created_at, updated_at) VALUES
-- 订单详情权限
('订单详情', 313, 0, 'order:detail', 65, 1, '查看订单详情权限', NULL, NULL, 0, NOW(), NOW()),
('订单查询', 313, 0, 'order:query', 60, 1, '查询订单状态权限', NULL, NULL, 0, NOW(), NOW()),
('订单重发', 313, 0, 'order:resend', 55, 1, '重发订单权限', NULL, NULL, 0, NOW(), NOW()),
('订单回调', 313, 0, 'order:callback', 50, 1, '订单回调权限', NULL, NULL, 0, NOW(), NOW()),

-- 2. 商户管理相关权限补全
('商户产品管理', 300, 0, 'merchant:product_management', 45, 1, '商户产品管理权限', NULL, NULL, 0, NOW(), NOW()),
('商户对接资料', 300, 0, 'merchant:api_docs', 40, 1, '查看商户对接资料权限', NULL, NULL, 0, NOW(), NOW()),
('商户测试', 300, 0, 'merchant:test', 35, 1, '商户测试权限', NULL, NULL, 0, NOW(), NOW()),

-- 3. 供应商管理相关权限补全
('供应商测试', 200, 0, 'supplier:test', 40, 1, '供应商测试权限', NULL, NULL, 0, NOW(), NOW()),
('供应商配置', 200, 0, 'supplier:config', 35, 1, '供应商配置权限', NULL, NULL, 0, NOW(), NOW()),

-- 4. 产品管理相关权限补全
('产品池管理', 220, 0, 'product:pool', 45, 1, '产品池管理权限', NULL, NULL, 0, NOW(), NOW()),
('产品分配', 220, 0, 'product:assignment', 40, 1, '产品分配权限', NULL, NULL, 0, NOW(), NOW()),
('产品测试', 220, 0, 'product:test', 35, 1, '产品测试权限', NULL, NULL, 0, NOW(), NOW()),

-- 5. 通道管理相关权限补全
('通道测试', 210, 0, 'channel:test', 45, 1, '通道测试权限', NULL, NULL, 0, NOW(), NOW()),
('通道配置', 210, 0, 'channel:config', 40, 1, '通道配置权限', NULL, NULL, 0, NOW(), NOW()),

-- 6. 机器人管理相关权限补全
('机器人监控', 229, 0, 'robot:monitor', 60, 1, '机器人监控权限', NULL, NULL, 0, NOW(), NOW()),
('机器人配置', 229, 0, 'robot:config', 55, 1, '机器人配置权限', NULL, NULL, 0, NOW(), NOW()),

-- 7. 投诉管理权限
('投诉管理', 0, 1, 'complaints:home', 60, 1, '投诉管理模块', '/complaints/home', 'el-icon-warning', 1, NOW(), NOW()),
('投诉列表', 400, 0, 'complaints:list', 70, 1, '查看投诉列表权限', NULL, NULL, 0, NOW(), NOW()),
('投诉详情', 400, 0, 'complaints:detail', 65, 1, '查看投诉详情权限', NULL, NULL, 0, NOW(), NOW()),
('投诉处理', 400, 0, 'complaints:handle', 60, 1, '处理投诉权限', NULL, NULL, 0, NOW(), NOW()),
('投诉回复', 400, 0, 'complaints:reply', 55, 1, '回复投诉权限', NULL, NULL, 0, NOW(), NOW()),

-- 8. 退款管理权限
('退款管理', 0, 1, 'refund:home', 50, 1, '退款管理模块', '/refund/home', 'el-icon-money', 1, NOW(), NOW()),
('退款列表', 401, 0, 'refund:list', 70, 1, '查看退款列表权限', NULL, NULL, 0, NOW(), NOW()),
('退款详情', 401, 0, 'refund:detail', 65, 1, '查看退款详情权限', NULL, NULL, 0, NOW(), NOW()),
('退款处理', 401, 0, 'refund:handle', 60, 1, '处理退款权限', NULL, NULL, 0, NOW(), NOW()),
('退款审核', 401, 0, 'refund:audit', 55, 1, '审核退款权限', NULL, NULL, 0, NOW(), NOW()),

-- 9. 商户订单管理权限
('商户订单', 0, 1, 'merchant_orders:home', 40, 1, '商户订单管理模块', '/merchant-orders/home', 'el-icon-shopping-cart', 1, NOW(), NOW()),
('商户订单列表', 402, 0, 'merchant_orders:list', 70, 1, '查看商户订单列表权限', NULL, NULL, 0, NOW(), NOW()),
('商户订单详情', 402, 0, 'merchant_orders:detail', 65, 1, '查看商户订单详情权限', NULL, NULL, 0, NOW(), NOW()),
('商户订单添加', 402, 0, 'merchant_orders:add', 60, 1, '添加商户订单权限', NULL, NULL, 0, NOW(), NOW()),
('商户订单编辑', 402, 0, 'merchant_orders:edit', 55, 1, '编辑商户订单权限', NULL, NULL, 0, NOW(), NOW()),
('商户订单日志', 402, 0, 'merchant_orders:log', 50, 1, '查看商户订单日志权限', NULL, NULL, 0, NOW(), NOW()),

-- 10. 支付实体管理权限
('支付实体', 0, 1, 'payment_entities:home', 30, 1, '支付实体管理模块', '/payment-entities/home', 'el-icon-credit-card', 1, NOW(), NOW()),
('支付实体列表', 403, 0, 'payment_entities:list', 70, 1, '查看支付实体列表权限', NULL, NULL, 0, NOW(), NOW()),
('支付实体详情', 403, 0, 'payment_entities:detail', 65, 1, '查看支付实体详情权限', NULL, NULL, 0, NOW(), NOW()),
('支付实体编辑', 403, 0, 'payment_entities:edit', 60, 1, '编辑支付实体权限', NULL, NULL, 0, NOW(), NOW()),
('支付实体测试', 403, 0, 'payment_entities:test', 55, 1, '支付实体测试权限', NULL, NULL, 0, NOW(), NOW()),

-- 11. 支付方式管理权限
('支付方式', 0, 1, 'method:home', 20, 1, '支付方式管理模块', '/method/home', 'el-icon-payment', 1, NOW(), NOW()),
('支付方式列表', 404, 0, 'method:list', 70, 1, '查看支付方式列表权限', NULL, NULL, 0, NOW(), NOW()),
('支付方式编辑', 404, 0, 'method:edit', 60, 1, '编辑支付方式权限', NULL, NULL, 0, NOW(), NOW()),
('支付方式添加', 404, 0, 'method:add', 65, 1, '添加支付方式权限', NULL, NULL, 0, NOW(), NOW()),
('支付方式删除', 404, 0, 'method:delete', 55, 1, '删除支付方式权限', NULL, NULL, 0, NOW(), NOW()),

-- 12. 租户管理权限
('租户管理', 0, 1, 'tenant:home', 10, 1, '租户管理模块', '/tenant/home', 'el-icon-office-building', 1, NOW(), NOW()),
('租户列表', 405, 0, 'tenant:list', 70, 1, '查看租户列表权限', NULL, NULL, 0, NOW(), NOW()),
('租户编辑', 405, 0, 'tenant:edit', 60, 1, '编辑租户权限', NULL, NULL, 0, NOW(), NOW()),
('租户添加', 405, 0, 'tenant:add', 65, 1, '添加租户权限', NULL, NULL, 0, NOW(), NOW()),
('租户删除', 405, 0, 'tenant:delete', 55, 1, '删除租户权限', NULL, NULL, 0, NOW(), NOW()),

-- 13. 商户计费管理权限
('商户计费', 0, 1, 'merchant_billing:home', 15, 1, '商户计费管理模块', '/merchant_billing/home', 'el-icon-coin', 1, NOW(), NOW()),
('计费列表', 406, 0, 'merchant_billing:list', 70, 1, '查看计费列表权限', NULL, NULL, 0, NOW(), NOW()),
('计费详情', 406, 0, 'merchant_billing:detail', 65, 1, '查看计费详情权限', NULL, NULL, 0, NOW(), NOW()),
('计费添加', 406, 0, 'merchant_billing:add', 60, 1, '添加计费权限', NULL, NULL, 0, NOW(), NOW()),
('计费退款', 406, 0, 'merchant_billing:refund', 55, 1, '计费退款权限', NULL, NULL, 0, NOW(), NOW()),

-- 14. 资金收集代理权限
('资金代理', 0, 1, 'fund_collection_agent:home', 5, 1, '资金收集代理模块', '/fund_collection_agent/home', 'el-icon-bank', 1, NOW(), NOW()),
('代理列表', 407, 0, 'fund_collection_agent:list', 70, 1, '查看代理列表权限', NULL, NULL, 0, NOW(), NOW()),
('代理编辑', 407, 0, 'fund_collection_agent:edit', 60, 1, '编辑代理权限', NULL, NULL, 0, NOW(), NOW()),
('代理添加', 407, 0, 'fund_collection_agent:add', 65, 1, '添加代理权限', NULL, NULL, 0, NOW(), NOW()),
('代理删除', 407, 0, 'fund_collection_agent:delete', 55, 1, '删除代理权限', NULL, NULL, 0, NOW(), NOW()),

-- 15. 系统设置权限补全
('个人资料', 76, 0, 'admin:profile', 90, 1, '个人资料管理权限', NULL, NULL, 0, NOW(), NOW()),
('系统设置', 76, 0, 'admin:system', 85, 1, '系统设置权限', NULL, NULL, 0, NOW(), NOW()),
('系统配置', 76, 0, 'system:config', 80, 1, '系统配置权限', NULL, NULL, 0, NOW(), NOW()),

-- 16. 测试页面权限（低优先级）
('测试页面', 0, 1, 'test:home', 1, 1, '测试页面模块', '/test', 'el-icon-experiment', 1, NOW(), NOW()),
('订单测试', 408, 0, 'test:order', 70, 1, '订单测试权限', NULL, NULL, 0, NOW(), NOW()),
('商户测试', 408, 0, 'test:merchant', 65, 1, '商户测试权限', NULL, NULL, 0, NOW(), NOW()),
('产品测试', 408, 0, 'test:product', 60, 1, '产品测试权限', NULL, NULL, 0, NOW(), NOW()),
('供应商测试', 408, 0, 'test:supplier', 55, 1, '供应商测试权限', NULL, NULL, 0, NOW(), NOW()),
('支付实体测试', 408, 0, 'test:payment_entities', 50, 1, '支付实体测试权限', NULL, NULL, 0, NOW(), NOW());

