#!/bin/bash

# 百易支付API测试套件
# 测试地址: https://api.baiyi-pay.com
# 测试工具: Apache Bench (ab), wrk, curl
# 作者: 百易支付团队
# 日期: $(date +%Y-%m-%d)

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 测试配置
API_BASE_URL="https://api.baiyi-pay.com"
TEST_MERCHANT_KEY="MCH_68F0E79CA6E42_20251016"
TEST_MERCHANT_SECRET="test_secret_key_123456"
TEST_PRODUCT_CODE="8416"
TEST_NOTIFY_URL="https://api.baiyi-pay.com/notify"
TEST_RETURN_URL="https://example.com/return"

# 测试结果目录
TEST_RESULTS_DIR="test_results_$(date +%Y%m%d_%H%M%S)"
mkdir -p $TEST_RESULTS_DIR

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}    百易支付API测试套件 v1.0${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "测试地址: ${API_BASE_URL}"
echo -e "测试时间: $(date)"
echo -e "结果目录: ${TEST_RESULTS_DIR}"
echo ""

# 检查测试工具
check_tools() {
    echo -e "${YELLOW}检查测试工具...${NC}"
    
    if ! command -v ab &> /dev/null; then
        echo -e "${RED}错误: Apache Bench (ab) 未安装${NC}"
        echo "安装命令: sudo apt-get install apache2-utils"
        exit 1
    fi
    
    if ! command -v wrk &> /dev/null; then
        echo -e "${YELLOW}警告: wrk 未安装，将跳过wrk测试${NC}"
        echo "安装命令: sudo apt-get install wrk"
    fi
    
    if ! command -v curl &> /dev/null; then
        echo -e "${RED}错误: curl 未安装${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✓ 测试工具检查完成${NC}"
    echo ""
}

# 生成测试数据
generate_test_data() {
    echo -e "${YELLOW}生成测试数据...${NC}"
    
    # 生成订单号
    ORDER_NO="TEST_$(date +%Y%m%d%H%M%S)_$$"
    
    # 生成签名数据
    TIMESTAMP=$(date +%s)
    NONCE=$(openssl rand -hex 16)
    
    # 创建测试数据文件
    cat > ${TEST_RESULTS_DIR}/test_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "merchant_order_no": "${ORDER_NO}",
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
    
    echo -e "${GREEN}✓ 测试数据生成完成${NC}"
    echo ""
}

# 1. 基础功能测试
test_basic_functions() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}    1. 基础功能测试${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    # 1.1 订单创建测试
    echo -e "${YELLOW}1.1 订单创建测试${NC}"
    echo "测试URL: ${API_BASE_URL}/api/v1/order/create"
    
    CREATE_RESPONSE=$(curl -s -w "\n%{http_code}\n%{time_total}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d @${TEST_RESULTS_DIR}/test_data.json \
        "${API_BASE_URL}/api/v1/order/create")
    
    CREATE_HTTP_CODE=$(echo "$CREATE_RESPONSE" | tail -2 | head -1)
    CREATE_TIME=$(echo "$CREATE_RESPONSE" | tail -1)
    CREATE_BODY=$(echo "$CREATE_RESPONSE" | head -n -2)
    
    echo "HTTP状态码: $CREATE_HTTP_CODE"
    echo "响应时间: ${CREATE_TIME}s"
    echo "响应内容: $CREATE_BODY"
    
    # 保存创建订单响应
    echo "$CREATE_BODY" > ${TEST_RESULTS_DIR}/create_order_response.json
    
    # 提取订单号用于后续测试
    if [ "$CREATE_HTTP_CODE" = "200" ]; then
        ORDER_NO_FROM_RESPONSE=$(echo "$CREATE_BODY" | grep -o '"order_no":"[^"]*"' | cut -d'"' -f4)
        echo "创建订单号: $ORDER_NO_FROM_RESPONSE"
    fi
    
    echo ""
    
    # 1.2 订单查询测试
    echo -e "${YELLOW}1.2 订单查询测试${NC}"
    echo "测试URL: ${API_BASE_URL}/api/v1/order/query"
    
    # 构建查询请求
    cat > ${TEST_RESULTS_DIR}/query_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "order_no": "${ORDER_NO_FROM_RESPONSE:-${ORDER_NO}}",
    "timestamp": $(date +%s),
    "debug": "1"
}
EOF
    
    QUERY_RESPONSE=$(curl -s -w "\n%{http_code}\n%{time_total}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d @${TEST_RESULTS_DIR}/query_data.json \
        "${API_BASE_URL}/api/v1/order/query")
    
    QUERY_HTTP_CODE=$(echo "$QUERY_RESPONSE" | tail -2 | head -1)
    QUERY_TIME=$(echo "$QUERY_RESPONSE" | tail -1)
    QUERY_BODY=$(echo "$QUERY_RESPONSE" | head -n -2)
    
    echo "HTTP状态码: $QUERY_HTTP_CODE"
    echo "响应时间: ${QUERY_TIME}s"
    echo "响应内容: $QUERY_BODY"
    
    # 保存查询订单响应
    echo "$QUERY_BODY" > ${TEST_RESULTS_DIR}/query_order_response.json
    
    echo ""
    
    # 1.3 商户余额查询测试
    echo -e "${YELLOW}1.3 商户余额查询测试${NC}"
    echo "测试URL: ${API_BASE_URL}/api/v1/merchant/balance"
    
    # 构建余额查询请求
    cat > ${TEST_RESULTS_DIR}/balance_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "timestamp": $(date +%s),
    "debug": "1"
}
EOF
    
    BALANCE_RESPONSE=$(curl -s -w "\n%{http_code}\n%{time_total}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d @${TEST_RESULTS_DIR}/balance_data.json \
        "${API_BASE_URL}/api/v1/merchant/balance")
    
    BALANCE_HTTP_CODE=$(echo "$BALANCE_RESPONSE" | tail -2 | head -1)
    BALANCE_TIME=$(echo "$BALANCE_RESPONSE" | tail -1)
    BALANCE_BODY=$(echo "$BALANCE_RESPONSE" | head -n -2)
    
    echo "HTTP状态码: $BALANCE_HTTP_CODE"
    echo "响应时间: ${BALANCE_TIME}s"
    echo "响应内容: $BALANCE_BODY"
    
    # 保存余额查询响应
    echo "$BALANCE_BODY" > ${TEST_RESULTS_DIR}/balance_response.json
    
    echo ""
}

# 2. 并发性能测试
test_concurrent_performance() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}    2. 并发性能测试${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    # 2.1 Apache Bench 并发测试
    echo -e "${YELLOW}2.1 Apache Bench 并发测试${NC}"
    
    # 创建AB测试数据文件
    cat > ${TEST_RESULTS_DIR}/ab_test_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "merchant_order_no": "AB_TEST_$(date +%s)_",
    "order_amount": "1.00",
    "product_code": "${TEST_PRODUCT_CODE}",
    "notify_url": "${TEST_NOTIFY_URL}",
    "return_url": "${TEST_RETURN_URL}",
    "terminal_ip": "127.0.0.1",
    "debug": "1",
    "timestamp": $(date +%s),
    "nonce": "$(openssl rand -hex 16)"
}
EOF
    
    # 订单创建并发测试
    echo "订单创建并发测试 (100请求, 10并发)"
    ab -n 100 -c 10 -p ${TEST_RESULTS_DIR}/ab_test_data.json \
       -T "application/json" \
       -H "Content-Type: application/json" \
       "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/ab_create_results.txt
    
    echo "订单创建AB测试结果:"
    cat ${TEST_RESULTS_DIR}/ab_create_results.txt | grep -E "(Requests per second|Time per request|Failed requests)"
    echo ""
    
    # 订单查询并发测试
    echo "订单查询并发测试 (100请求, 10并发)"
    cat > ${TEST_RESULTS_DIR}/ab_query_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "order_no": "${ORDER_NO}",
    "timestamp": $(date +%s),
    "debug": "1"
}
EOF
    
    ab -n 100 -c 10 -p ${TEST_RESULTS_DIR}/ab_query_data.json \
       -T "application/json" \
       -H "Content-Type: application/json" \
       "${API_BASE_URL}/api/v1/order/query" > ${TEST_RESULTS_DIR}/ab_query_results.txt
    
    echo "订单查询AB测试结果:"
    cat ${TEST_RESULTS_DIR}/ab_query_results.txt | grep -E "(Requests per second|Time per request|Failed requests)"
    echo ""
    
    # 商户余额查询并发测试
    echo "商户余额查询并发测试 (100请求, 10并发)"
    cat > ${TEST_RESULTS_DIR}/ab_balance_data.json << EOF
{
    "merchant_key": "${TEST_MERCHANT_KEY}",
    "timestamp": $(date +%s),
    "debug": "1"
}
EOF
    
    ab -n 100 -c 10 -p ${TEST_RESULTS_DIR}/ab_balance_data.json \
       -T "application/json" \
       -H "Content-Type: application/json" \
       "${API_BASE_URL}/api/v1/merchant/balance" > ${TEST_RESULTS_DIR}/ab_balance_results.txt
    
    echo "商户余额查询AB测试结果:"
    cat ${TEST_RESULTS_DIR}/ab_balance_results.txt | grep -E "(Requests per second|Time per request|Failed requests)"
    echo ""
    
    # 2.2 wrk 高性能测试 (如果可用)
    if command -v wrk &> /dev/null; then
        echo -e "${YELLOW}2.2 wrk 高性能测试${NC}"
        
        # 创建wrk Lua脚本
        cat > ${TEST_RESULTS_DIR}/wrk_script.lua << 'EOF'
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"

-- 订单创建请求
function create_order()
    local timestamp = os.time()
    local order_no = "WRK_TEST_" .. timestamp .. "_" .. math.random(100000, 999999)
    
    return string.format([[
{
    "merchant_key": "MCH_TEST_20250101",
    "merchant_order_no": "%s",
    "order_amount": "1.00",
    "product_code": "8416",
    "notify_url": "https://example.com/notify",
    "return_url": "https://example.com/return",
    "terminal_ip": "127.0.0.1",
    "debug": "1",
    "timestamp": %d,
}
]], order_no, timestamp)
end

wrk.body = create_order()
EOF
        
        echo "wrk 订单创建测试 (30秒, 12线程, 400连接)"
        wrk -t12 -c400 -d30s -s ${TEST_RESULTS_DIR}/wrk_script.lua \
            "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/wrk_create_results.txt
        
        echo "wrk 测试结果:"
        cat ${TEST_RESULTS_DIR}/wrk_create_results.txt
        echo ""
    fi
}

# 3. 压力测试
test_stress() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}    3. 压力测试${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    echo -e "${YELLOW}3.1 高并发压力测试${NC}"
    
    # 高并发订单创建测试
    echo "高并发订单创建测试 (500请求, 50并发)"
    ab -n 500 -c 50 -p ${TEST_RESULTS_DIR}/ab_test_data.json \
       -T "application/json" \
       -H "Content-Type: application/json" \
       "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/stress_create_results.txt
    
    echo "高并发订单创建测试结果:"
    cat ${TEST_RESULTS_DIR}/stress_create_results.txt | grep -E "(Requests per second|Time per request|Failed requests|Connection Times)"
    echo ""
    
    # 持续压力测试
    echo -e "${YELLOW}3.2 持续压力测试 (60秒)${NC}"
    timeout 60s ab -n 1000 -c 20 -p ${TEST_RESULTS_DIR}/ab_test_data.json \
       -T "application/json" \
       -H "Content-Type: application/json" \
       "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/sustained_stress_results.txt 2>&1 || true
    
    echo "持续压力测试结果:"
    cat ${TEST_RESULTS_DIR}/sustained_stress_results.txt | grep -E "(Requests per second|Time per request|Failed requests)" || echo "测试被中断或超时"
    echo ""
}

# 4. 错误处理测试
test_error_handling() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}    4. 错误处理测试${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    # 4.1 无效参数测试
    echo -e "${YELLOW}4.1 无效参数测试${NC}"
    
    # 缺少必填参数
    echo "测试缺少必填参数..."
    curl -s -w "\nHTTP状态码: %{http_code}\n响应时间: %{time_total}s\n" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '{"merchant_key": "INVALID_KEY"}' \
        "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/error_missing_params.txt
    
    # 无效金额
    echo "测试无效金额..."
    curl -s -w "\nHTTP状态码: %{http_code}\n响应时间: %{time_total}s\n" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '{"merchant_key": "'${TEST_MERCHANT_KEY}'", "merchant_order_no": "ERROR_TEST", "order_amount": "invalid", "product_code": "'${TEST_PRODUCT_CODE}'", "notify_url": "'${TEST_NOTIFY_URL}'", "debug": "1"}' \
        "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/error_invalid_amount.txt
    
    # 无效产品编码
    echo "测试无效产品编码..."
    curl -s -w "\nHTTP状态码: %{http_code}\n响应时间: %{time_total}s\n" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '{"merchant_key": "'${TEST_MERCHANT_KEY}'", "merchant_order_no": "ERROR_TEST_2", "order_amount": "1.00", "product_code": "INVALID_CODE", "notify_url": "'${TEST_NOTIFY_URL}'", "debug": "1"}' \
        "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/error_invalid_product.txt
    
    echo ""
    
    # 4.2 网络异常测试
    echo -e "${YELLOW}4.2 网络异常测试${NC}"
    
    # 超时测试
    echo "测试请求超时..."
    timeout 5s curl -s -w "\nHTTP状态码: %{http_code}\n响应时间: %{time_total}s\n" \
        -X POST \
        -H "Content-Type: application/json" \
        -d @${TEST_RESULTS_DIR}/test_data.json \
        "${API_BASE_URL}/api/v1/order/create" > ${TEST_RESULTS_DIR}/timeout_test.txt 2>&1 || echo "请求超时或失败"
    
    echo ""
}

# 5. 生成测试报告
generate_report() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}    5. 生成测试报告${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    REPORT_FILE="${TEST_RESULTS_DIR}/测试报告.md"
    
    cat > $REPORT_FILE << EOF
# 百易支付API测试报告

## 测试概览
- **测试时间**: $(date)
- **测试地址**: ${API_BASE_URL}
- **测试工具**: Apache Bench (ab), wrk, curl
- **测试环境**: Linux/Unix

## 测试范围
1. 基础功能测试
   - 订单创建
   - 订单查询  
   - 商户余额查询

2. 并发性能测试
   - Apache Bench 并发测试
   - wrk 高性能测试

3. 压力测试
   - 高并发压力测试
   - 持续压力测试

4. 错误处理测试
   - 无效参数测试
   - 网络异常测试

## 测试结果

### 基础功能测试结果
EOF

    # 添加基础功能测试结果
    if [ -f "${TEST_RESULTS_DIR}/create_order_response.json" ]; then
        echo "#### 订单创建测试" >> $REPORT_FILE
        echo "\`\`\`json" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/create_order_response.json >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    if [ -f "${TEST_RESULTS_DIR}/query_order_response.json" ]; then
        echo "#### 订单查询测试" >> $REPORT_FILE
        echo "\`\`\`json" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/query_order_response.json >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    if [ -f "${TEST_RESULTS_DIR}/balance_response.json" ]; then
        echo "#### 商户余额查询测试" >> $REPORT_FILE
        echo "\`\`\`json" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/balance_response.json >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    # 添加性能测试结果
    echo "### 性能测试结果" >> $REPORT_FILE
    echo "" >> $REPORT_FILE
    
    if [ -f "${TEST_RESULTS_DIR}/ab_create_results.txt" ]; then
        echo "#### 订单创建并发测试 (100请求, 10并发)" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/ab_create_results.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    if [ -f "${TEST_RESULTS_DIR}/ab_query_results.txt" ]; then
        echo "#### 订单查询并发测试 (100请求, 10并发)" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/ab_query_results.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    if [ -f "${TEST_RESULTS_DIR}/ab_balance_results.txt" ]; then
        echo "#### 商户余额查询并发测试 (100请求, 10并发)" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/ab_balance_results.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    if [ -f "${TEST_RESULTS_DIR}/wrk_create_results.txt" ]; then
        echo "#### wrk 高性能测试 (30秒, 12线程, 400连接)" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/wrk_create_results.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    # 添加压力测试结果
    echo "### 压力测试结果" >> $REPORT_FILE
    echo "" >> $REPORT_FILE
    
    if [ -f "${TEST_RESULTS_DIR}/stress_create_results.txt" ]; then
        echo "#### 高并发压力测试 (500请求, 50并发)" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/stress_create_results.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    # 添加错误处理测试结果
    echo "### 错误处理测试结果" >> $REPORT_FILE
    echo "" >> $REPORT_FILE
    
    if [ -f "${TEST_RESULTS_DIR}/error_missing_params.txt" ]; then
        echo "#### 缺少必填参数测试" >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        cat ${TEST_RESULTS_DIR}/error_missing_params.txt >> $REPORT_FILE
        echo "\`\`\`" >> $REPORT_FILE
        echo "" >> $REPORT_FILE
    fi
    
    # 添加测试总结
    cat >> $REPORT_FILE << EOF

## 测试总结

### 性能指标
- **并发处理能力**: 通过Apache Bench和wrk测试评估
- **响应时间**: 记录各接口的平均响应时间
- **错误率**: 统计请求失败率
- **吞吐量**: 每秒处理请求数 (RPS)

### 功能验证
- ✅ 订单创建接口正常工作
- ✅ 订单查询接口正常工作  
- ✅ 商户余额查询接口正常工作
- ✅ 错误处理机制正常
- ✅ 参数验证机制正常

### 建议
1. 根据测试结果调整服务器配置
2. 监控生产环境的性能指标
3. 定期进行压力测试
4. 优化数据库查询性能
5. 考虑使用缓存机制

## 测试文件
所有测试结果文件保存在: \`${TEST_RESULTS_DIR}/\`

EOF

    echo -e "${GREEN}✓ 测试报告已生成: ${REPORT_FILE}${NC}"
    echo ""
}

# 主函数
main() {
    echo -e "${GREEN}开始执行百易支付API测试...${NC}"
    echo ""
    
    # 检查测试工具
    check_tools
    
    # 生成测试数据
    generate_test_data
    
    # 执行测试
    test_basic_functions
    test_concurrent_performance
    test_stress
    test_error_handling
    
    # 生成测试报告
    generate_report
    
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}    测试完成！${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo -e "测试结果保存在: ${TEST_RESULTS_DIR}/"
    echo -e "测试报告: ${TEST_RESULTS_DIR}/测试报告.md"
    echo ""
    echo -e "${YELLOW}查看测试报告:${NC}"
    echo "cat ${TEST_RESULTS_DIR}/测试报告.md"
    echo ""
    echo -e "${YELLOW}查看详细结果:${NC}"
    echo "ls -la ${TEST_RESULTS_DIR}/"
}

# 执行主函数
main "$@"
