-- 创建订单流转日志表
CREATE TABLE IF NOT EXISTS `fourth_party_payment_order_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `order_id` bigint(20) unsigned NOT NULL COMMENT '订单ID',
  `order_no` varchar(64) NOT NULL COMMENT '订单号',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态',
  `action` varchar(32) NOT NULL COMMENT '操作动作',
  `description` varchar(255) NOT NULL COMMENT '描述',
  `operator_type` varchar(16) NOT NULL DEFAULT 'system' COMMENT '操作者类型',
  `operator_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '操作者ID',
  `operator_name` varchar(64) NOT NULL DEFAULT '' COMMENT '操作者名称',
  `ip_address` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP地址',
  `user_agent` varchar(500) NOT NULL DEFAULT '' COMMENT '用户代理',
  `extra_data` json DEFAULT NULL COMMENT '扩展数据',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_operator` (`operator_type`, `operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单流转日志表';


