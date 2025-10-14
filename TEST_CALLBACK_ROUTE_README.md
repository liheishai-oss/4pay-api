# 测试回调路由使用说明

## 概述

已创建了一个完整的测试回调路由系统，用于测试商户回调功能。该系统支持签名验证，成功后返回"success"。

## 路由接口

### 1. 生成测试回调数据
- **URL**: `GET/POST /api/v1/callback/test/generate`
- **功能**: 生成带有正确签名的测试回调数据
- **参数**:
  - `order_no` (可选): 订单号，默认自动生成
  - `merchant_order_no` (可选): 商户订单号，默认自动生成
  - `amount` (可选): 金额，默认100.00
  - `status` (可选): 状态，默认success

**示例请求**:
```bash
curl -X GET "http://localhost:8787/api/v1/callback/test/generate"
```

**响应示例**:
```json
{
  "code": 200,
  "message": "生成成功",
  "data": {
    "callback_data": {
      "order_no": "TEST_20251012095749_5637",
      "merchant_order_no": "MERCHANT_20251012095749_9864",
      "amount": "100.00",
      "status": "success",
      "status_text": "支付成功",
      "paid_time": "2025-10-12 09:57:49",
      "created_at": "2025-10-12 09:57:49",
      "timestamp": 1758650944,
      "sign": "c90dd081501c33c50b626f49a873cdb5"
    },
    "secret_key": "test_secret_key_123456789",
    "curl_example": "curl -X POST 'http://localhost:8787/api/v1/callback/test/notify' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"order_no\":\"TEST_20251012095749_5637\",...}'"
  }
}
```

### 2. 测试回调通知接口
- **URL**: `POST /api/v1/callback/test/notify`
- **功能**: 接收回调数据，验证签名，成功后返回"success"
- **参数**: 完整的回调数据（包含sign字段）

**示例请求**:
```bash
curl -X POST "http://localhost:8787/api/v1/callback/test/notify" \
  -H "Content-Type: application/json" \
  -d '{
    "order_no": "TEST_20251012095749_5637",
    "merchant_order_no": "MERCHANT_20251012095749_9864",
    "amount": "100.00",
    "status": "success",
    "status_text": "支付成功",
    "paid_time": "2025-10-12 09:57:49",
    "created_at": "2025-10-12 09:57:49",
    "timestamp": 1758650944,
    "sign": "c90dd081501c33c50b626f49a873cdb5"
  }'
```

**成功响应**:
```
success
```

**失败响应**:
```
签名验证失败
```

### 3. 测试签名验证
- **URL**: `POST /api/v1/callback/test/signature`
- **功能**: 测试签名生成和验证功能
- **参数**: 回调数据（不包含sign字段）

### 4. 获取回调日志
- **URL**: `GET /api/v1/callback/test/logs`
- **功能**: 获取本地回调日志记录
- **参数**:
  - `date` (可选): 日期，格式 YYYY-MM-DD，默认今天
  - `type` (可选): 日志类型，all/received/response，默认all
  - `limit` (可选): 返回数量限制，默认100

**示例请求**:
```bash
curl -X POST "http://localhost:8787/api/v1/callback/test/signature" \
  -H "Content-Type: application/json" \
  -d '{
    "order_no": "TEST_123",
    "merchant_order_no": "MERCHANT_123",
    "amount": "100.00",
    "status": "success"
  }'
```

**示例请求**:
```bash
curl -X GET "http://localhost:8787/api/v1/callback/test/logs?limit=10&type=all"
```

**响应示例**:
```json
{
  "code": 200,
  "message": "签名测试完成",
  "data": {
    "input_data": {
      "order_no": "TEST_123",
      "merchant_order_no": "MERCHANT_123",
      "amount": "100.00",
      "status": "success"
    },
    "generated_signature": "9e2aa233fb1efa65a66e3c90c65bfa6e",
    "signature_valid": true,
    "secret_key": "test_secret_key_123456789"
  }
}
```

**响应示例**:
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "logs": [
      {
        "timestamp": "2025-10-12 10:09:34",
        "type": "callback_received",
        "data": {
          "timestamp": "2025-10-12 10:09:34",
          "action": "callback_received",
          "order_no": "TEST_20251012100934_1629",
          "merchant_order_no": "MERCHANT_20251012100934_8609",
          "amount": "100.00",
          "status": "success",
          "client_ip": "127.0.0.1",
          "user_agent": "curl/8.14.1",
          "sign": "bca69367ab4582a6b8e20366885fb9e3"
        }
      },
      {
        "timestamp": "2025-10-12 10:09:34",
        "type": "callback_response",
        "data": {
          "timestamp": "2025-10-12 10:09:34",
          "action": "callback_response",
          "order_no": "TEST_20251012100934_1629",
          "merchant_order_no": "MERCHANT_20251012100934_8609",
          "amount": "100.00",
          "status": "success",
          "signature_valid": true,
          "http_code": 200,
          "response_content": "success",
          "response_time": 0.001
        }
      }
    ],
    "total": 2,
    "date": "2025-10-12",
    "type": "all"
  }
}
```

## 签名算法

使用 `SignatureHelper` 进行签名验证，算法如下：

1. **排除字段**: 排除 `sign`、`client_ip`、`entities_id` 字段
2. **字段排序**: 按字段名进行字典序排序
3. **拼接字符串**: 将所有字段值按顺序拼接
4. **生成签名**: `md5(hash_hmac('sha256', $stringToSign, $secretKey))`

## 测试密钥

当前使用的测试密钥: `test_secret_key_123456789`

## 完整测试流程

### 1. 生成测试数据
```bash
curl -X GET "http://localhost:8787/api/v1/callback/test/generate"
```

### 2. 使用生成的数据进行回调测试
```bash
curl -X POST "http://localhost:8787/api/v1/callback/test/notify" \
  -H "Content-Type: application/json" \
  -d '{"order_no":"TEST_20251012095749_5637","merchant_order_no":"MERCHANT_20251012095749_9864","amount":"100.00","status":"success","status_text":"支付成功","paid_time":"2025-10-12 09:57:49","created_at":"2025-10-12 09:57:49","timestamp":1758650944,"sign":"c90dd081501c33c50b626f49a873cdb5"}'
```

### 3. 验证返回结果
期望返回: `success`

## 错误处理

- **缺少必要参数**: 返回400错误，提示缺少的字段
- **签名验证失败**: 返回400错误，提示"签名验证失败"
- **处理异常**: 返回400错误，显示具体异常信息

## 日志记录

所有请求都会记录到系统日志中，包括：
- 请求数据
- 签名验证结果
- 处理结果
- 异常信息

## 本地日志记录

系统会自动记录所有回调请求和响应到本地日志文件：

- **日志目录**: `runtime/logs/callback/`
- **日志文件**: 
  - `callback_received_YYYY-MM-DD.log` - 回调接收日志
  - `callback_response_YYYY-MM-DD.log` - 回调响应日志
- **日志格式**: `时间戳 | JSON数据`
- **记录内容**:
  - 回调接收：订单信息、客户端IP、User-Agent、原始数据
  - 回调响应：签名验证结果、HTTP状态码、响应内容、响应时间

## 注意事项

1. 该测试路由**无中间件限制**，仅用于测试目的
2. 使用固定的测试密钥，生产环境需要根据商户信息动态获取
3. 返回纯文本"success"，符合支付系统回调规范
4. 支持所有HTTP方法（GET、POST、OPTIONS）以支持跨域请求
5. **本地日志记录**：所有回调都会记录到本地文件，便于检测和调试

## 文件位置

- 控制器: `backend-api/app/api/controller/v1/callback/TestCallbackController.php`
- 路由配置: `backend-api/config/route.php` (第340-348行)
- 测试脚本: `test_callback_route.php`
