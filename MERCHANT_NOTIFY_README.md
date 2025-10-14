# 高并发商户通知系统

## 系统架构

### 核心组件
1. **MerchantNotificationService** - 高并发通知服务
2. **MerchantNotifyQueueCommand** - 队列处理器
3. **MerchantNotifyController** - 监控管理接口
4. **Redis队列** - 异步任务队列

### 技术特性
- ✅ **高并发处理** - 支持50个并发请求
- ✅ **异步队列** - 非阻塞式通知处理
- ✅ **智能重试** - 3次重试，递增延迟
- ✅ **分布式锁** - 防止重复通知
- ✅ **批量处理** - 批量通知提升效率
- ✅ **商户隔离** - 防止慢商户阻塞整个系统
- ✅ **熔断机制** - 自动熔断问题商户
- ✅ **延迟队列** - 熔断商户延迟重试
- ✅ **机器人告警** - 自动推送告警到Telegram机器人
- ✅ **监控管理** - 完整的监控和管理接口
- ✅ **日志记录** - 详细的通知日志

## 部署步骤

### 1. 启动队列处理器

```bash
# 方式1：直接运行
php start_merchant_notify_queue.php

# 方式2：后台运行
nohup php start_merchant_notify_queue.php > /dev/null 2>&1 &

# 方式3：使用supervisor管理
# 创建supervisor配置文件
```

### 2. Supervisor配置示例

```ini
[program:merchant-notify-queue]
command=php /path/to/your/project/start_merchant_notify_queue.php
directory=/path/to/your/project
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/merchant-notify-queue.log
```

### 3. 系统服务配置

```bash
# 创建systemd服务文件
sudo vim /etc/systemd/system/merchant-notify-queue.service
```

```ini
[Unit]
Description=Merchant Notify Queue Processor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php start_merchant_notify_queue.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
# 启用服务
sudo systemctl enable merchant-notify-queue
sudo systemctl start merchant-notify-queue
```

## 监控接口

### 1. 获取队列状态
```http
GET /admin/merchant-notify/queue-status
```

响应示例：
```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "pending_count": 10,
        "retry_count": 5,
        "pending_orders": [...],
        "retry_orders": [...]
    }
}
```

### 2. 获取通知日志
```http
GET /admin/merchant-notify/logs?page=1&limit=20&order_no=BY20251010&status=1
```

### 3. 手动重试通知
```http
POST /admin/merchant-notify/retry
Content-Type: application/json

{
    "order_id": 123
}
```

### 4. 获取通知统计
```http
GET /admin/merchant-notify/stats?start_time=2025-01-01&end_time=2025-01-31
```

### 5. 清理过期日志
```http
POST /admin/merchant-notify/cleanup
Content-Type: application/json

{
    "days": 30
}
```

### 6. 获取商户状态
```http
GET /admin/merchant-notify/merchant-status?notify_url=https://example.com/notify
```

响应示例：
```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "merchant_key": "abc123",
        "notify_url": "https://example.com/notify",
        "failure_count": 3,
        "avg_response_time": 5.234,
        "is_circuit_breaker_open": false,
        "is_slow_merchant": true,
        "status": "slow"
    }
}
```

### 7. 重置商户熔断器
```http
POST /admin/merchant-notify/reset-circuit-breaker
Content-Type: application/json

{
    "notify_url": "https://example.com/notify"
}
```

### 8. 获取所有商户状态
```http
GET /admin/merchant-notify/all-merchant-status?page=1&limit=20
```

### 9. 手动发送商户告警
```http
POST /admin/merchant-notify/send-alert
Content-Type: application/json

{
    "notify_url": "https://example.com/notify",
    "alert_type": "slow_merchant"
}
```

支持告警类型：
- `slow_merchant` - 慢商户告警
- `circuit_breaker` - 熔断器告警

## 防阻塞机制

### 1. 商户隔离
- **问题商户识别**：自动识别响应时间超过3秒的慢商户
- **失败次数统计**：记录每个商户的连续失败次数
- **响应时间监控**：记录最近100次请求的平均响应时间

### 2. 熔断机制
- **触发条件**：商户连续失败5次自动开启熔断器
- **熔断时间**：熔断器开启5分钟，期间跳过该商户的通知
- **自动恢复**：熔断期结束后自动重置，允许重新尝试

### 3. 延迟队列
- **熔断商户**：被熔断的商户通知会进入延迟队列
- **延迟重试**：延迟1分钟后重新尝试通知
- **避免阻塞**：确保慢商户不会影响其他正常商户

### 4. 状态监控
- **实时状态**：normal（正常）、slow（慢商户）、circuit_breaker（熔断）
- **详细指标**：失败次数、平均响应时间、熔断状态
- **手动干预**：支持手动重置熔断器

### 5. 机器人告警
- **熔断器告警**：商户熔断器开启时立即推送告警
- **慢商户告警**：响应时间超过阈值时推送告警（1小时内不重复）
- **告警内容**：包含商户标识、失败次数、响应时间、建议措施
- **告警级别**：熔断器告警为关键告警，慢商户告警为普通告警

### 6. 工作流程
```
商户通知请求
    ↓
检查熔断状态
    ↓
熔断器开启？ → 是 → 进入延迟队列（1分钟后重试）
    ↓ 否
发送通知请求
    ↓
记录响应时间
    ↓
成功？ → 是 → 重置失败计数
    ↓ 否
增加失败计数
    ↓
失败次数≥5？ → 是 → 开启熔断器
    ↓ 否
正常重试
```

## 机器人告警配置

### 1. Telegram配置
确保在 `config/telegram.php` 中正确配置：

```php
return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', 'your_bot_token'),
    'alert_chat_ids' => [
        'admin' => env('TELEGRAM_ADMIN_CHAT_ID', '-your_chat_id'),
        'monitor' => env('TELEGRAM_MONITOR_CHAT_ID', '-your_chat_id'),
    ],
    'alert_types' => [
        'merchant_circuit_breaker' => true,
        'slow_merchant' => true,
    ],
    'critical_alerts' => [
        'merchant_circuit_breaker' => true,   // 熔断器告警为关键告警
        'slow_merchant' => false,             // 慢商户告警为普通告警
    ],
];
```

### 2. 告警消息示例

**熔断器告警**：
```
🚨 商户通知熔断器告警

时间: 2025-01-10 15:30:45
商户标识: abc123def456
失败次数: 5
熔断时长: 5分钟
状态: 熔断器已开启

影响: 该商户的通知将被暂停5分钟，避免影响其他商户的正常通知。

建议: 请检查商户服务器状态和网络连接。
```

**慢商户告警**：
```
⚠️ 慢商户告警

时间: 2025-01-10 15:30:45
商户标识: abc123def456
平均响应时间: 5.234秒
阈值: 3秒
状态: 响应过慢

影响: 该商户响应时间超过阈值，可能影响通知效率。

建议: 请检查商户服务器性能和网络状况。
```

## 性能优化

### 1. Redis配置优化
```redis
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
tcp-keepalive 60
timeout 300
```

### 2. PHP配置优化
```ini
; php.ini
memory_limit = 512M
max_execution_time = 0
max_input_time = -1
```

### 3. 并发控制
```php
// 在MerchantNotificationService中调整
private $maxConcurrent = 100; // 增加并发数
private $timeout = 5; // 减少超时时间
```

## 故障排查

### 1. 检查队列状态
```bash
# 检查Redis队列
redis-cli
> LLEN merchant_notify_pending_queue
> ZCARD merchant_notify_retry_queue
```

### 2. 查看日志
```bash
# 查看应用日志
tail -f runtime/logs/webman.log

# 查看队列处理器日志
tail -f /var/log/merchant-notify-queue.log
```

### 3. 手动处理队列
```php
// 手动处理重试队列
$command = new MerchantNotifyQueueCommand();
$command->processRetryQueue();
```

## 监控指标

### 关键指标
- **队列长度** - 待处理通知数量
- **成功率** - 通知成功比例
- **平均响应时间** - 商户接口响应时间
- **重试率** - 需要重试的通知比例
- **错误率** - 通知失败比例

### 告警设置
- 队列长度 > 1000
- 成功率 < 95%
- 平均响应时间 > 10秒
- 错误率 > 5%

## 扩展功能

### 1. 支持多种通知方式
- HTTP回调
- WebSocket推送
- 消息队列（RabbitMQ/Kafka）
- 邮件通知
- 短信通知

### 2. 智能路由
- 根据商户配置选择通知方式
- 负载均衡多个通知地址
- 故障转移机制

### 3. 数据分析
- 通知成功率分析
- 商户响应时间分析
- 失败原因分析
- 趋势预测

## 注意事项

1. **Redis依赖** - 确保Redis服务正常运行
2. **网络稳定** - 确保与商户服务器的网络连接稳定
3. **资源监控** - 监控CPU、内存、网络使用情况
4. **日志管理** - 定期清理过期日志，避免磁盘空间不足
5. **安全考虑** - 通知数据包含敏感信息，确保传输安全

## 版本更新

### v1.0.0
- 基础高并发通知功能
- Redis队列支持
- 重试机制
- 监控接口

### 计划功能
- WebSocket实时通知
- 消息队列集成
- 智能路由
- 数据分析面板
