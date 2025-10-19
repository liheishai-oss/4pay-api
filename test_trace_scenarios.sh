#!/bin/bash

# 完整追踪测试场景脚本
# 测试不同场景下的日志追踪流程

echo "=== 订单创建追踪测试场景 ==="
echo "测试时间: $(date)"
echo ""

# 设置基础参数
API_BASE_URL="http://127.0.0.1:8787"
MERCHANT_KEY="MCH_68F0E79CA6E42_20251016"
TIMESTAMP=$(date +%s)

# 创建测试结果目录
TEST_RESULTS_DIR="test_results_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$TEST_RESULTS_DIR"

echo "测试结果将保存到: $TEST_RESULTS_DIR"
echo ""

# 场景1: 正常订单创建
echo "=== 场景1: 正常订单创建 ==="
echo "测试参数: 金额1元，产品代码8416"
echo ""

MERCHANT_ORDER_NO_1="ORDER_$(date +%Y%m%d_%H%M%S)_001"
JSON_DATA_1=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "merchant_order_no": "$MERCHANT_ORDER_NO_1",
    "order_amount": "1",
    "product_code": 8416,
    "notify_url": "http://127.0.0.1/notify",
    "return_url": "https://example.com/return",
    "is_form": 2,
    "terminal_ip": "127.0.0.1",
    "payer_id": "TEST_USER_001",
    "order_title": "正常测试订单",
    "order_body": "这是一个测试订单",
    "debug": 1,
    "sign": "test_sign"
}
EOF
)

echo "发送请求1..."
curl -X POST "$API_BASE_URL/api/v1/order/create" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$JSON_DATA_1" \
  -w "\n\n=== 请求1统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "==================\n" \
  -o "$TEST_RESULTS_DIR/order_create_1_response.json" \
  -s

echo ""
echo "等待3秒..."
sleep 3

# 场景2: 大金额订单创建
echo "=== 场景2: 大金额订单创建 ==="
echo "测试参数: 金额100元，产品代码8416"
echo ""

MERCHANT_ORDER_NO_2="ORDER_$(date +%Y%m%d_%H%M%S)_002"
JSON_DATA_2=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "merchant_order_no": "$MERCHANT_ORDER_NO_2",
    "order_amount": "100",
    "product_code": 8416,
    "notify_url": "http://127.0.0.1/notify",
    "return_url": "https://example.com/return",
    "is_form": 2,
    "terminal_ip": "127.0.0.1",
    "payer_id": "TEST_USER_002",
    "order_title": "大金额测试订单",
    "order_body": "这是一个大金额测试订单",
    "debug": 1,
    "sign": "test_sign"
}
EOF
)

echo "发送请求2..."
curl -X POST "$API_BASE_URL/api/v1/order/create" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$JSON_DATA_2" \
  -w "\n\n=== 请求2统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "==================\n" \
  -o "$TEST_RESULTS_DIR/order_create_2_response.json" \
  -s

echo ""
echo "等待3秒..."
sleep 3

# 场景3: 错误参数测试
echo "=== 场景3: 错误参数测试 ==="
echo "测试参数: 缺少必填参数"
echo ""

JSON_DATA_3=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "merchant_order_no": "ORDER_$(date +%Y%m%d_%H%M%S)_003",
    "order_amount": "1",
    "product_code": 8416,
    "debug": 1,
    "sign": "test_sign"
}
EOF
)

echo "发送请求3..."
curl -X POST "$API_BASE_URL/api/v1/order/create" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$JSON_DATA_3" \
  -w "\n\n=== 请求3统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "==================\n" \
  -o "$TEST_RESULTS_DIR/order_create_3_response.json" \
  -s

echo ""
echo "等待3秒..."
sleep 3

# 场景4: 订单查询测试
echo "=== 场景4: 订单查询测试 ==="
echo "查询刚才创建的订单"
echo ""

QUERY_DATA=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "order_no": "$MERCHANT_ORDER_NO_1",
    "debug": 1,
    "sign": "test_sign"
}
EOF
)

echo "发送查询请求..."
curl -X POST "$API_BASE_URL/api/v1/order/query" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$QUERY_DATA" \
  -w "\n\n=== 查询请求统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "==================\n" \
  -o "$TEST_RESULTS_DIR/order_query_response.json" \
  -s

echo ""
echo "等待3秒..."
sleep 3

# 场景5: 余额查询测试
echo "=== 场景5: 余额查询测试 ==="
echo "查询商户余额"
echo ""

BALANCE_DATA=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "debug": 1,
    "sign": "test_sign"
}
EOF
)

echo "发送余额查询请求..."
curl -X POST "$API_BASE_URL/api/v1/merchant/balance" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$BALANCE_DATA" \
  -w "\n\n=== 余额查询统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "==================\n" \
  -o "$TEST_RESULTS_DIR/balance_query_response.json" \
  -s

echo ""
echo "=== 测试完成 ==="
echo "测试结果保存在: $TEST_RESULTS_DIR"
echo ""

# 生成测试报告
echo "=== 生成测试报告 ==="
cat > "$TEST_RESULTS_DIR/test_report.md" <<EOF
# 订单创建追踪测试报告

## 测试时间
$(date)

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
\`\`\`sql
-- 查看生命周期追踪
SELECT * FROM order_lifecycle_traces 
WHERE created_at >= '$(date +%Y-%m-%d)' 
ORDER BY created_at DESC LIMIT 20;

-- 查看查询追踪
SELECT * FROM order_query_traces 
WHERE created_at >= '$(date +%Y-%m-%d)' 
ORDER BY created_at DESC LIMIT 20;
\`\`\`

### 2. 日志文件
\`\`\`bash
# 查看今日日志
tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i trace

# 查看订单创建日志
tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i "订单创建"
\`\`\`

### 3. 前端界面
- 访问: /pages/trace/search.vue
- 搜索关键词: $MERCHANT_ORDER_NO_1 或 $MERCHANT_ORDER_NO_2

### 4. API接口
- 搜索接口: /api/v1/admin/trace/search?keyword=$MERCHANT_ORDER_NO_1
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
EOF

echo "测试报告已生成: $TEST_RESULTS_DIR/test_report.md"
echo ""

# 显示数据库查询命令
echo "=== 数据库查询命令 ==="
echo "查看生命周期追踪:"
echo "SELECT * FROM order_lifecycle_traces WHERE created_at >= '$(date +%Y-%m-%d)' ORDER BY created_at DESC LIMIT 10;"
echo ""
echo "查看查询追踪:"
echo "SELECT * FROM order_query_traces WHERE created_at >= '$(date +%Y-%m-%d)' ORDER BY created_at DESC LIMIT 10;"
echo ""

# 显示日志查看命令
echo "=== 日志查看命令 ==="
echo "查看追踪日志:"
echo "tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i trace"
echo ""
echo "查看订单创建日志:"
echo "tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i '订单创建'"
echo ""

echo "=== 测试完成 ==="
echo "请检查上述内容以验证追踪功能是否正常工作"
