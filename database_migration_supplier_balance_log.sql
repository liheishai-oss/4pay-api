-- 供应商余额变动记录表
CREATE TABLE `fourth_party_payment_supplier_balance_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `supplier_id` bigint unsigned NOT NULL COMMENT '供应商ID',
  `operation_type` tinyint unsigned NOT NULL COMMENT '操作类型：1=预付，2=下发，3=订单扣款，4=退款，5=系统调整',
  `amount` bigint NOT NULL COMMENT '变动金额（分）',
  `balance_before` bigint NOT NULL COMMENT '变动前余额（分）',
  `balance_after` bigint NOT NULL COMMENT '变动后余额（分）',
  `operator_type` tinyint unsigned NOT NULL COMMENT '操作人类型：1=管理员，2=系统，3=订单',
  `operator_id` bigint unsigned DEFAULT NULL COMMENT '操作人ID（管理员ID或系统ID）',
  `operator_name` varchar(128) DEFAULT NULL COMMENT '操作人名称',
  `order_id` bigint unsigned DEFAULT NULL COMMENT '关联订单ID（如果是订单相关操作）',
  `order_no` varchar(64) DEFAULT NULL COMMENT '订单号（冗余字段，便于查询）',
  `remark` text COMMENT '备注信息',
  `telegram_message` text COMMENT 'Telegram原始消息（用于审计）',
  `ip_address` varchar(45) DEFAULT NULL COMMENT '操作IP地址',
  `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_operation_type` (`operation_type`),
  KEY `idx_operator_type` (`operator_type`),
  KEY `idx_operator_id` (`operator_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_supplier_created` (`supplier_id`, `created_at`),
  CONSTRAINT `fk_supplier_balance_log_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `fourth_party_payment_supplier` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_balance_log_order` FOREIGN KEY (`order_id`) REFERENCES `fourth_party_payment_order` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商余额变动记录表';

-- 操作类型枚举说明
-- 1: 预付 - 管理员手动增加预存款
-- 2: 下发 - 管理员手动扣除预存款  
-- 3: 订单扣款 - 订单支付时扣除预存款
-- 4: 退款 - 订单退款时返还预存款
-- 5: 系统调整 - 系统自动调整余额

-- 操作人类型枚举说明
-- 1: 管理员 - 通过Telegram或后台操作
-- 2: 系统 - 系统自动操作
-- 3: 订单 - 订单相关操作

