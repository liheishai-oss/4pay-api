# 订单创建追踪测试说明

## 概述
本目录包含了用于测试订单创建完整日志追踪流程的测试脚本。

## 测试脚本

### 1. simple_curl_test.sh - 简单测试
使用您提供的参数进行单次测试：

```bash
./simple_curl_test.sh
```

**测试参数:**
- 商户密钥: MCH_68F0E79CA6E42_20251016
- 订单金额: 1元
- 产品代码: 8416
- 调试模式: 启用

### 2. test_trace_scenarios.sh - 完整场景测试
包含多个测试场景的完整测试：

```bash
./test_trace_scenarios.sh
```

**测试场景:**
1. 正常订单创建 (1元)
2. 大金额订单创建 (100元)
3. 错误参数测试
4. 订单查询测试
5. 余额查询测试

## 追踪功能验证

### 1. 数据库验证
测试完成后，检查数据库中的追踪记录：

```sql
-- 查看生命周期追踪
SELECT * FROM order_lifecycle_traces 
WHERE created_at >= CURDATE() 
ORDER BY created_at DESC LIMIT 10;

-- 查看查询追踪
SELECT * FROM order_query_traces 
WHERE created_at >= CURDATE() 
ORDER BY created_at DESC LIMIT 10;
```

### 2. 日志文件验证
查看应用日志中的追踪信息：

```bash
# 查看追踪日志
tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i trace

# 查看订单创建日志
tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i "订单创建"

# 查看中间件日志
tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i "TraceMiddleware"
```

### 3. 前端界面验证
访问前端追踪界面：
- URL: `/pages/trace/search.vue`
- 搜索关键词: 订单号或trace_id

### 4. API接口验证
使用API接口查看追踪数据：

```bash
# 搜索追踪
curl "http://127.0.0.1:8787/api/v1/admin/trace/search?keyword=ORDER_20250101_001"

# 获取追踪详情
curl "http://127.0.0.1:8787/api/v1/admin/trace/lifecycle/{trace_id}"
```

## 预期结果

### 数据库记录
每个请求都应该在以下表中生成记录：
- `order_lifecycle_traces` - 订单生命周期追踪
- `order_query_traces` - 订单查询追踪

### 日志记录
日志文件中应该包含：
- 请求开始/结束记录
- 中间件自动记录
- 业务逻辑追踪记录
- 错误处理记录

### 追踪链路
完整的追踪链路应该包括：
1. **订单创建流程**: 创建 → 验证 → 支付 → 回调 → 完成
2. **订单查询流程**: 查询请求 → 数据库查询 → 响应格式化
3. **错误处理流程**: 错误捕获 → 状态更新 → 链路记录

## 故障排除

### 1. 数据库连接问题
确保数据库表已创建：
```sql
-- 执行数据库迁移
source database_migration_create_trace_logs.sql;
```

### 2. 中间件未生效
检查中间件配置：
```php
// config/middleware.php
'' => [
    app\middleware\TraceMiddleware::class,
    // 其他中间件...
]
```

### 3. 追踪服务异常
检查追踪服务是否正常：
```php
// 测试追踪服务
$traceService = new \app\service\TraceService();
$traceService->logLifecycleStep('test_trace', 1, 1, 'test_step', 'success', []);
```

### 4. 日志权限问题
确保日志目录可写：
```bash
chmod -R 755 runtime/logs/
```

## 测试结果分析

### 成功指标
- ✅ 数据库中有追踪记录
- ✅ 日志文件中有追踪信息
- ✅ 前端界面可以搜索到追踪数据
- ✅ API接口返回完整追踪信息

### 性能指标
- 请求响应时间 < 2秒
- 数据库写入延迟 < 100ms
- 日志写入延迟 < 50ms

### 数据完整性
- 每个请求都有对应的追踪记录
- 追踪数据包含完整的业务信息
- 错误情况下的追踪记录完整

## 注意事项

1. **测试环境**: 确保在测试环境中运行，避免影响生产数据
2. **数据库备份**: 测试前建议备份数据库
3. **日志清理**: 测试后可以清理测试日志
4. **权限检查**: 确保有足够的数据库和文件系统权限

## 联系支持

如果遇到问题，请检查：
1. 数据库连接是否正常
2. 中间件配置是否正确
3. 追踪服务是否可用
4. 日志目录权限是否正确


