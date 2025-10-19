-- 创建订单生命周期追踪表
CREATE TABLE order_lifecycle_traces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trace_id VARCHAR(36) NOT NULL COMMENT '追踪ID',
    order_id BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
    merchant_id BIGINT UNSIGNED COMMENT '商户ID',
    step_name VARCHAR(100) NOT NULL COMMENT '步骤名称',
    step_status ENUM('success', 'failed', 'pending') NOT NULL COMMENT '步骤状态',
    step_data JSON COMMENT '步骤数据',
    duration_ms INT UNSIGNED DEFAULT 0 COMMENT '步骤耗时(毫秒)',
    parent_step_id BIGINT UNSIGNED NULL COMMENT '父步骤ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    -- 索引
    INDEX idx_trace_id (trace_id),
    INDEX idx_order_id (order_id),
    INDEX idx_merchant_id (merchant_id),
    INDEX idx_step_name (step_name),
    INDEX idx_step_status (step_status),
    INDEX idx_created_at (created_at),
    
    -- 复合索引
    INDEX idx_trace_step (trace_id, step_name),
    INDEX idx_order_trace (order_id, trace_id),
    INDEX idx_merchant_trace (merchant_id, trace_id),
    INDEX idx_created_trace (created_at, trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单生命周期追踪表';

-- 创建订单查询追踪表
CREATE TABLE order_query_traces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trace_id VARCHAR(36) NOT NULL COMMENT '追踪ID',
    order_id BIGINT UNSIGNED COMMENT '订单ID',
    merchant_id BIGINT UNSIGNED COMMENT '商户ID',
    query_type VARCHAR(50) NOT NULL COMMENT '查询类型',
    step_name VARCHAR(100) NOT NULL COMMENT '步骤名称',
    step_status ENUM('success', 'failed', 'pending') NOT NULL COMMENT '步骤状态',
    step_data JSON COMMENT '步骤数据',
    duration_ms INT UNSIGNED DEFAULT 0 COMMENT '步骤耗时(毫秒)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    -- 索引
    INDEX idx_trace_id (trace_id),
    INDEX idx_order_id (order_id),
    INDEX idx_merchant_id (merchant_id),
    INDEX idx_query_type (query_type),
    INDEX idx_step_name (step_name),
    INDEX idx_step_status (step_status),
    INDEX idx_created_at (created_at),
    
    -- 复合索引
    INDEX idx_trace_step (trace_id, step_name),
    INDEX idx_order_trace (order_id, trace_id),
    INDEX idx_merchant_trace (merchant_id, trace_id),
    INDEX idx_query_trace (query_type, trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单查询追踪表';

