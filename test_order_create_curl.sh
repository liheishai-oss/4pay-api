#!/bin/bash

# 订单创建测试脚本
# 测试完整的日志追踪流程

echo "=== 订单创建流程追踪测试 ==="
echo "测试URL: http://127.0.0.1:8787/api/v1/order/create"
echo "测试时间: $(date)"
echo ""

# 设置测试参数
API_URL="http://127.0.0.1:8787/api/v1/order/create"
MERCHANT_KEY="MCH_68F0E79CA6E42_20251016"
NONCE=$(date +%s)
MERCHANT_ORDER_NO="ORDER_$(date +%Y%m%d_%H%M%S)_$$"
ORDER_AMOUNT="1"
PRODUCT_CODE="8416"
NOTIFY_URL="http://127.0.0.1/notify"
RETURN_URL="https://example.com/return"
IS_FORM="2"
TERMINAL_IP="127.0.0.1"
PAYER_ID="TEST_USER_001"
ORDER_TITLE="正常测试订单"
ORDER_BODY="这是一个测试订单"
DEBUG="1"
SIGN="test_sign"

echo "测试参数:"
echo "  商户密钥: $MERCHANT_KEY"
echo "  随机数: $NONCE"
echo "  商户订单号: $MERCHANT_ORDER_NO"
echo "  订单金额: $ORDER_AMOUNT"
echo "  产品代码: $PRODUCT_CODE"
echo "  通知地址: $NOTIFY_URL"
echo "  返回地址: $RETURN_URL"
echo "  终端IP: $TERMINAL_IP"
echo "  支付者ID: $PAYER_ID"
echo "  订单标题: $ORDER_TITLE"
echo "  订单描述: $ORDER_BODY"
echo "  调试模式: $DEBUG"
echo "  签名: $SIGN"
echo ""

# 构建JSON请求体
JSON_DATA=$(cat <<EOF
{
    "merchant_key": "$MERCHANT_KEY",
    "merchant_order_no": "$MERCHANT_ORDER_NO",
    "order_amount": "$ORDER_AMOUNT",
    "product_code": $PRODUCT_CODE,
    "notify_url": "$NOTIFY_URL",
    "return_url": "$RETURN_URL",
    "is_form": $IS_FORM,
    "terminal_ip": "$TERMINAL_IP",
    "payer_id": "$PAYER_ID",
    "order_title": "$ORDER_TITLE",
    "order_body": "$ORDER_BODY",
    "debug": $DEBUG,
    "sign": "$SIGN"
}
EOF
)

echo "发送请求..."
echo ""

# 执行curl请求
curl -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "User-Agent: TraceTest/1.0" \
  -d "$JSON_DATA" \
  -w "\n\n=== 请求统计 ===\n" \
  -w "HTTP状态码: %{http_code}\n" \
  -w "总耗时: %{time_total}s\n" \
  -w "连接耗时: %{time_connect}s\n" \
  -w "首字节耗时: %{time_starttransfer}s\n" \
  -w "上传大小: %{size_upload} bytes\n" \
  -w "下载大小: %{size_download} bytes\n" \
  -w "平均速度: %{speed_download} bytes/s\n" \
  -w "==================\n" \
  -v

echo ""
echo "=== 测试完成 ==="
echo "请检查以下内容:"
echo "1. 数据库追踪表: order_lifecycle_traces"
echo "2. 现有日志文件: runtime/logs/"
echo "3. 前端追踪界面: /pages/trace/search.vue"
echo "4. API追踪接口: /api/v1/admin/trace/search"
echo ""
echo "数据库查询命令:"
echo "SELECT * FROM order_lifecycle_traces WHERE trace_id LIKE '%$(date +%Y%m%d)%' ORDER BY created_at DESC LIMIT 10;"
echo ""
echo "日志文件位置:"
echo "tail -f runtime/logs/$(date +%Y-%m-%d).log | grep -i trace"
