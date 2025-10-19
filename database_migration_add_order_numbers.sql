-- 添加订单号字段到追踪表
-- 为订单生命周期追踪表添加订单号字段
ALTER TABLE order_lifecycle_traces 
ADD COLUMN order_no VARCHAR(50) COMMENT '平台订单号' AFTER order_id,
ADD COLUMN merchant_order_no VARCHAR(100) COMMENT '商户订单号' AFTER order_no;

-- 为订单查询追踪表添加订单号字段
ALTER TABLE order_query_traces 
ADD COLUMN order_no VARCHAR(50) COMMENT '平台订单号' AFTER order_id,
ADD COLUMN merchant_order_no VARCHAR(100) COMMENT '商户订单号' AFTER order_no;

-- 添加订单号相关索引
ALTER TABLE order_lifecycle_traces 
ADD INDEX idx_order_no (order_no),
ADD INDEX idx_merchant_order_no (merchant_order_no);

ALTER TABLE order_query_traces 
ADD INDEX idx_order_no (order_no),
ADD INDEX idx_merchant_order_no (merchant_order_no);

-- 添加复合索引
ALTER TABLE order_lifecycle_traces 
ADD INDEX idx_order_no_trace (order_no, trace_id),
ADD INDEX idx_merchant_order_no_trace (merchant_order_no, trace_id);

ALTER TABLE order_query_traces 
ADD INDEX idx_order_no_trace (order_no, trace_id),
ADD INDEX idx_merchant_order_no_trace (merchant_order_no, trace_id);
