# Docker环境测试指南

## 🐳 环境准备

### 1. 确保Docker容器运行
```bash
# 查看容器状态
docker ps

# 如果容器未运行，启动容器
docker-compose up -d
```

### 2. 检查服务状态
```bash
# 检查API服务是否正常
curl http://localhost:8787/health

# 检查数据库连接
docker exec -it fourth-party-payment-backend-api-1 php -r "echo 'Database connection test';"
```

## 🧪 测试脚本

### 快速测试
```bash
# 进入项目目录
cd /Users/apple/dnmp/www/fourth-party-payment/backend-api

# 运行快速测试
./test_quick_curl.sh
```

### 完整测试
```bash
# 运行完整测试套件
./test_enterprise_validation_curl.sh
```

## 📊 测试用例

### 1. 正常订单创建
- **目的**: 验证企业级状态验证正常工作
- **参数**: 有效商户、产品、金额
- **预期**: 订单创建成功，使用默认通道

### 2. 边界值测试
- **最小金额**: 0.01元
- **最大金额**: 999.99元
- **预期**: 正确处理边界值

### 3. 错误情况测试
- **无效商户**: 不存在的merchant_key
- **无效产品**: 不存在的product_id
- **金额超限**: 超出允许范围
- **缺少参数**: 必填参数缺失
- **预期**: 返回相应错误信息

## 🔍 验证要点

### 1. 企业级状态验证
- ✅ 供应商状态验证
- ✅ 支付通道状态验证  
- ✅ 产品状态验证
- ✅ 产品通道关联状态验证

### 2. 通道选择逻辑
- ✅ 使用第一个通道作为默认通道
- ✅ 支付时支持通道降级切换
- ✅ 通道切换时更新订单信息

### 3. 数据库约束
- ✅ channel_id 不为空
- ✅ payment_method 不为空
- ✅ 所有必填字段都有值

## 📝 日志监控

### 查看实时日志
```bash
# 查看API服务日志
docker logs -f fourth-party-payment-backend-api-1

# 查看数据库日志
docker logs -f fourth-party-payment-mysql-1
```

### 关键日志信息
- `开始企业级通道验证` - 验证开始
- `企业级通道验证完成` - 验证完成
- `订单通道已切换` - 通道切换
- `订单使用默认通道` - 使用默认通道

## 🚨 常见问题

### 1. 数据库连接失败
```bash
# 检查数据库容器状态
docker ps | grep mysql

# 重启数据库容器
docker restart fourth-party-payment-mysql-1
```

### 2. API服务无响应
```bash
# 检查API容器状态
docker ps | grep api

# 重启API容器
docker restart fourth-party-payment-backend-api-1
```

### 3. 状态验证失败
- 检查数据库中是否有有效的测试数据
- 确保供应商、通道、产品状态都为启用状态
- 检查产品通道关联配置

## 📈 性能监控

### 响应时间
- 正常订单创建: < 2秒
- 错误情况处理: < 1秒

### 资源使用
```bash
# 查看容器资源使用
docker stats
```

## 🔧 调试技巧

### 1. 启用调试模式
在环境变量中设置:
```bash
APP_DEBUG=true
```

### 2. 查看详细错误
```bash
# 查看完整错误堆栈
docker logs fourth-party-payment-backend-api-1 | grep "Debug错误"
```

### 3. 数据库查询
```bash
# 进入数据库容器
docker exec -it fourth-party-payment-mysql-1 mysql -u root -p

# 查询订单数据
SELECT * FROM fourth_party_payment_order ORDER BY created_at DESC LIMIT 5;
```

## ✅ 测试检查清单

- [ ] Docker容器正常运行
- [ ] API服务响应正常
- [ ] 数据库连接正常
- [ ] 测试数据准备完整
- [ ] 企业级验证功能正常
- [ ] 通道选择逻辑正确
- [ ] 错误处理完善
- [ ] 日志记录完整

## 📞 支持

如果遇到问题，请检查：
1. 容器状态和日志
2. 数据库连接和数据
3. 网络连接
4. 配置文件设置
