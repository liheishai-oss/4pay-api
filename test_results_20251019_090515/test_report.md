# 订单创建追踪测试报告

## 测试时间
2025年10月19日 星期日 09时05分28秒 CST

## 测试场景
1. **正常订单创建** - 金额1元，产品代码8416
2. **大金额订单创建** - 金额100元，产品代码8416  
3. **错误参数测试** - 缺少必填参数
4. **订单查询测试** - 查询创建的订单
5. **余额查询测试** - 查询商户余额

## 测试结果文件
- order_create_1_response.json - 正常订单创建响应
- order_create_2_response.json - 大金额订单创建响应
- order_create_3_response.json - 错误参数测试响应
- order_query_response.json - 订单查询响应
- balance_query_response.json - 余额查询响应

## 追踪数据检查
请检查以下内容以验证追踪功能:

### 1. 数据库追踪表
```sql
-- 查看生命周期追踪
SELECT * FROM order_lifecycle_traces 
WHERE created_at >= '2025-10-19' 
ORDER BY created_at DESC LIMIT 20;

-- 查看查询追踪
SELECT * FROM order_query_traces 
WHERE created_at >= '2025-10-19' 
ORDER BY created_at DESC LIMIT 20;
```

### 2. 日志文件
```bash
# 查看今日日志
tail -f runtime/logs/2025-10-19.log | grep -i trace

# 查看订单创建日志
tail -f runtime/logs/2025-10-19.log | grep -i "订单创建"
```

### 3. 前端界面
- 访问: /pages/trace/search.vue
- 搜索关键词: ORDER_20251019_090515_001 或 ORDER_20251019_090518_002

### 4. API接口
- 搜索接口: /api/v1/admin/trace/search?keyword=ORDER_20251019_090515_001
- 详情接口: /api/v1/admin/trace/lifecycle/{trace_id}

## 预期结果
1. 每个请求都应该在数据库中生成追踪记录
2. 日志文件中应该包含详细的追踪信息
3. 前端界面应该能够搜索和查看完整的链路
4. API接口应该返回完整的追踪数据

## 注意事项
- 确保数据库表已创建: order_lifecycle_traces, order_query_traces
- 确保中间件已启用: TraceMiddleware
- 确保追踪服务正常运行: TraceService
