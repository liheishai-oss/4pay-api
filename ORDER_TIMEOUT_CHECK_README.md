# 订单超时检查功能

## 功能概述

订单超时检查功能用于自动检测和处理超时未支付的订单，防止订单长时间处于待支付或支付中状态，提高系统资源利用效率。

## 主要特性

- **自动检测**：每10秒检查一次超时订单
- **智能同步**：检查供应商订单状态，同步支付时间和交易号
- **批量处理**：支持批量关闭超时订单
- **配置灵活**：支持通过系统配置调整订单有效期
- **统计监控**：提供详细的统计信息和监控数据
- **手动操作**：支持手动执行检查和关闭操作

## 系统配置

### 订单有效期配置

在 `system_config` 表中添加以下配置：

```sql
INSERT INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`, `is_public`, `created_at`, `updated_at`) 
VALUES (
    'payment.order_validity_minutes', 
    '30', 
    'number', 
    '订单有效期（分钟），超过此时间订单将自动失败', 
    0, 
    NOW(), 
    NOW()
) ON DUPLICATE KEY UPDATE 
    `config_value` = VALUES(`config_value`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();
```

### 配置说明

- **配置键**：`payment.order_validity_minutes`
- **配置值**：订单有效期（分钟），默认30分钟
- **配置类型**：`number`
- **是否公开**：`0`（否）

## 部署和使用

### 1. 使用Webman进程管理（推荐）

```bash
# 启动Webman应用（包含订单超时检查进程）
php start.php

# 后台运行
php start.php -d

# 停止应用
php stop.php

# 重启应用
php restart.php
```

### 2. 独立运行（不推荐）

```bash
# 后台运行订单超时检查任务
nohup php start_order_timeout_check.php > order_timeout_check.log 2>&1 &

# 查看任务进程
ps aux | grep "start_order_timeout_check.php"

# 查看任务日志
tail -f order_timeout_check.log

# 停止任务
kill <进程ID>
```

### 3. 测试功能

```bash
# 运行测试脚本
php test_order_timeout_check.php
```

## API接口

### 1. 获取统计信息

```http
GET /admin/order-timeout/stats
```

**响应示例：**
```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "order_validity_minutes": 30,
        "timeout_time": "2025-10-11 08:30:00",
        "pending_timeout_count": 5,
        "processing_timeout_count": 2,
        "total_timeout_count": 7,
        "today_closed_count": 15,
        "last_check_time": "2025-10-11 09:00:00"
    }
}
```

### 2. 手动执行检查

```http
POST /admin/order-timeout/execute
```

**响应示例：**
```json
{
    "code": 200,
    "msg": "执行成功",
    "data": {
        "order_validity_minutes": 30,
        "timeout_time": "2025-10-11 08:30:00",
        "pending_timeout_count": 0,
        "processing_timeout_count": 0,
        "total_timeout_count": 0,
        "today_closed_count": 22,
        "last_check_time": "2025-10-11 09:05:00"
    }
}
```

### 3. 获取超时订单列表

```http
GET /admin/order-timeout/orders?page=1&limit=20
```

**响应示例：**
```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "list": [
            {
                "id": 123,
                "order_no": "BY20251011085147C9F01431",
                "merchant_order_no": "978-1-77970-454-2",
                "merchant_name": "测试商户",
                "merchant_account": "test_merchant",
                "amount": 10000,
                "status": 1,
                "status_text": "待支付",
                "created_at": "2025-10-11 08:00:00",
                "expire_time": "2025-10-11 08:30:00",
                "timeout_minutes": 35
            }
        ],
        "total": 1,
        "page": 1,
        "limit": 20,
        "timeout_time": "2025-10-11 08:30:00",
        "order_validity_minutes": 30
    }
}
```

### 4. 批量关闭超时订单

```http
POST /admin/order-timeout/close
Content-Type: application/json

{
    "order_ids": [123, 124, 125]
}
```

**响应示例：**
```json
{
    "code": 200,
    "msg": "成功关闭 3 个超时订单",
    "data": {
        "updated_count": 3,
        "order_ids": [123, 124, 125],
        "order_nos": ["BY20251011085147C9F01431", "BY20251011085147C9F01432", "BY20251011085147C9F01433"]
    }
}
```

## 订单状态说明

- **1**：待支付
- **2**：支付中
- **3**：支付成功
- **4**：支付失败
- **5**：已退款
- **6**：已关闭（超时关闭）

## 工作流程

1. **定时检查**：每10秒执行一次检查
2. **计算超时时间**：根据配置的订单有效期计算超时时间点
3. **查找超时订单**：查找创建时间早于超时时间点且状态为待支付或支付中的订单
4. **检查供应商状态**：在关闭前查询供应商订单状态
   - 如果供应商订单已支付成功，更新本地订单状态为成功
   - 使用供应商的支付时间和交易号更新本地订单
   - 如果供应商订单未支付，关闭本地订单
5. **批量关闭**：将超时订单状态更新为已关闭（状态6）
6. **记录日志**：记录操作日志和统计信息

## 监控和告警

### 日志监控

任务执行日志会记录在以下位置：
- 应用日志：`runtime/logs/system-*.log`
- 任务日志：`order_timeout_check.log`

### 关键指标

- **超时订单数量**：当前超时的订单数量
- **今日关闭数量**：今日已关闭的超时订单数量
- **今日同步数量**：今日从供应商同步的支付成功订单数量
- **检查频率**：每10秒执行一次
- **处理效率**：批量处理，支持事务回滚
- **智能同步**：自动同步供应商支付时间和交易号

## 注意事项

1. **数据安全**：使用数据库事务确保数据一致性
2. **性能优化**：批量处理，避免逐条更新
3. **错误处理**：完善的异常处理和日志记录
4. **配置灵活**：支持动态调整订单有效期
5. **监控完善**：提供详细的统计信息和监控数据
6. **智能同步**：自动同步供应商支付时间和交易号，确保数据准确性
7. **时间处理**：支持多种时间格式（时间戳、日期时间字符串）

## 故障排除

### 常见问题

1. **任务未启动**
   - 检查进程是否运行：`ps aux | grep "start_order_timeout_check.php"`
   - 查看启动日志：`cat order_timeout_check.log`

2. **配置未生效**
   - 检查系统配置表：`SELECT * FROM system_config WHERE config_key = 'payment.order_validity_minutes'`
   - 验证配置值是否正确

3. **订单未关闭**
   - 检查订单状态：确保订单状态为1或2
   - 检查创建时间：确保订单创建时间早于超时时间点
   - 查看执行日志：检查是否有错误信息

### 调试命令

```bash
# 测试API接口
php test_order_timeout_check.php

# 查看任务统计
curl -X GET "http://127.0.0.1:8787/admin/order-timeout/stats"

# 手动执行检查
curl -X POST "http://127.0.0.1:8787/admin/order-timeout/execute"
```
