## 百亿四方 API 对接文档

本文档面向商户侧（客户端）对接，包含下单、查询、回调规范、签名说明与错误码示例。


### 环境与基础信息
- 编码：UTF-8，`application/json`
- 时间格式：`YYYY-MM-DD HH:mm:ss`
- 金额单位：元

### 鉴权
商户服务端接口通过签名（sign）与密钥进行校验，详见"签名规则"。

### 订单状态码
| 状态码 | 说明 | 备注 |
|--------|------|------|
| 1 | 待支付 | 订单已创建，等待用户支付 |
| 2 | 支付中 | 用户正在支付流程中 |
| 3 | 支付成功 | 支付完成，订单成功 |
| 4 | 支付失败 | 支付失败或被拒绝 |
| 5 | 已退款 | 订单已退款 |
| 6 | 已关闭 | 订单已关闭或取消 |

---

## 一、创建订单
POST `/api/v1/order/create`

请求参数（JSON）：

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| merchant_key | string | 是 | 商户唯一标识 | MCH_68F0E79CA6E42_20251016 |
| merchant_order_no | string | 是 | 商户订单号 | 978-0-461-13992-1 |
| order_amount | string | 是 | 订单金额 | 1.00 |
| product_code | string | 是 | 产品编码 | "8416" |
| notify_url | string | 是 | 异步通知地址（支付成功回调） | http://127.0.0.1/notify |
| return_url | string | 否 | 同步跳转地址 | https://example.com/return |
| terminal_ip | string | 是 | 终端IP地址 | 127.0.0.1 |
| extra_data | string | 否 | 扩展数据，JSON格式 | {"custom_field": "value"} |
| sign | string | 是 | 签名（SignatureHelper 规则） | 9f1c...

返回示例：
```json
{
  "code": 200,
  "status": true,
  "message": "订单创建成功",
  "data": {
    "order_no": "BY20251019103004C4CA9643",
    "trace_id": "3d26a574-3daf-4372-9154-5db34b38faf2",
    "payment_url": "http://127.0.0.1/v1/alipay/order/payment?require_id=P732025101910300526126"
  }
}
```

返回字段说明：

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| code | int | HTTP状态码 | 200 |
| status | boolean | 请求是否成功 | true |
| message | string | 返回消息 | "订单创建成功" |
| data.order_no | string | 平台订单号 | "BY20251019103004C4CA9643" |
| data.trace_id | string | 追踪ID | "3d26a574-3daf-4372-9154-5db34b38faf2" |
| data.payment_url | string | 支付链接 | "http://127.0.0.1/v1/alipay/order/payment?require_id=..." |

---

## 二、订单查询
POST `/api/v1/order/query`

请求参数（JSON）：

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| merchant_key | string | 是 | 商户唯一标识 | MCH_68F0E79CA6E42_20251016 |
| order_no | string | 否 | 平台订单号（与 merchant_order_no 二选一，同时提供时以 order_no 为准） | BY20251016204701C4CA1207 |
| merchant_order_no | string | 否 | 商户订单号（与 order_no 二选一，同时提供时以 order_no 为准） | M202510160001 |
| timestamp | int | 是 | 时间戳（秒），5分钟内有效 | 1760622065 |
| sign | string | 是 | 签名，见签名规则 | 9f1c...

返回示例：
```json
{
  "code": 200,
  "status": true,
  "message": "查询成功",
  "data": {
    "merchant_key": "MCH_68F0E79CA6E42_20251016",
    "order_no": "BY20251019103004C4CA9643",
    "merchant_order_no": "DEMO_20251019103004_6039",
    "third_party_order_no": "",
    "trace_id": "5c4ec5f0-f018-497e-a8c8-31778a3fca89",
    "status": 2,
    "amount": "1.00",
    "subject": "订单支付",
    "created_at": "2025-10-19 10:30:04",
    "paid_time": null,
    "extra_data": "{\"user_id\": \"12345\", \"source\": \"mobile_app\"}"
  }
}
```

返回字段说明：

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| code | int | HTTP状态码 | 200 |
| status | boolean | 请求是否成功 | true |
| message | string | 返回消息 | "查询成功" |
| data.merchant_key | string | 商户唯一标识 | "MCH_68F0E79CA6E42_20251016" |
| data.order_no | string | 平台订单号 | "BY20251019103004C4CA9643" |
| data.merchant_order_no | string | 商户订单号 | "DEMO_20251019103004_6039" |
| data.third_party_order_no | string | 第三方订单号 | "" |
| data.trace_id | string | 追踪ID | "5c4ec5f0-f018-497e-a8c8-31778a3fca89" |
| data.status | int | 订单状态 | 1=待支付, 2=支付中, 3=支付成功, 4=支付失败, 5=已退款, 6=已关闭 |
| data.amount | string | 订单金额 | "1.00" |
| data.subject | string | 订单标题 | "订单支付" |
| data.created_at | string | 创建时间 | "2025-10-19 10:30:04" |
| data.paid_time | string\|null | 支付时间 | "2025-10-19 10:30:04" 或 null |
| data.extra_data | string | 扩展数据（JSON格式） | "{\"user_id\":\"12345\"}" |

---

## 三、商户余额查询
POST `/api/v1/merchant/balance`

请求参数（JSON）：

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| merchant_key | string | 是 | 商户唯一标识 | MCH_68F0E79CA6E42_20251016 |
| timestamp | int | 是 | 时间戳（秒），5分钟内有效 | 1760622065 |
| sign | string | 是 | 签名，见签名规则 | 9f1c...

返回示例：
```json
{
  "code": 200,
  "status": true,
  "message": "查询成功",
  "data": {
    "merchant_key": "MCH_68F0E79CA6E42_20251016",
    "balance": "0.00",
    "trace_id": "c1fed143-67f4-4611-8ef4-1d2039e94eed"
  }
}
```

返回字段说明：

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| code | int | HTTP状态码 | 200 |
| status | boolean | 请求是否成功 | true |
| message | string | 返回消息 | "查询成功" |
| data.merchant_key | string | 商户唯一标识 | "MCH_68F0E79CA6E42_20251016" |
| data.balance | string | 账户余额（元） | "0.00" |
| data.trace_id | string | 追踪ID | "c1fed143-67f4-4611-8ef4-1d2039e94eed" |

---

## 四、异步回调（服务端通知）
当订单支付成功后，系统会向商户的 `notify_url` 以 `POST` 发送 JSON：

| 字段 | 类型 | 说明 | 示例 |
| - | - | - | - |
| order_no | string | 平台订单号 | BY20251016204701C4CA1207 |
| merchant_order_no | string | 商户订单号 | M202510160001 |
| amount | string | 金额（元，保留2位小数） | 1.00 |
| status | int | 订单状态：1=待支付, 2=支付中, 3=支付成功, 4=支付失败, 5=已退款, 6=已关闭 | 3 |
| status_text | string | 状态文本描述 | 支付成功 |
| paid_time | string | 支付时间（`YYYY-MM-DD HH:mm:ss`） | 2025-10-16 12:49:52 |
| created_at | string | 订单创建时间（`YYYY-MM-DD HH:mm:ss`） | 2025-10-16 12:45:30 |
| extra_data | string | 扩展数据（JSON格式） | {"user_id": "12345", "source": "mobile_app"} |
| timestamp | int | 时间戳（秒） | 1760622065 |
| sign | string | 回调签名 | 9f1c... |

商户接收后需返回纯文本：`success`（大小写均可）。
- 返回非 `success`、5xx、超时等会被视为失败，系统带有重试与监控补偿。

注意：根据策略，只有 `status=3(支付成功)` 会发送回调；已关闭等状态不回调。

---

## 五、签名规则

规则摘要：
1. 字段选择：如未指定参与字段列表，默认取请求参数中（去空值后）的全部字段键集合。
2. 排序：对参与的字段名进行字典序排序；
3. 拼接：将参与字段的"值"按排序结果顺序直接拼接为一个字符串（仅值拼接，不包含键名与连接符）；
4. 计算：`md5(stringToSign + secretKey)` 得到签名字符串。

### 不同接口的签名字段

**订单创建**：使用所有字段（除排除字段外）
- 字段：`merchant_key`, `merchant_order_no`, `order_amount`, `product_code`, `notify_url`, `return_url`, `terminal_ip`, `extra_data`

**订单查询**：使用特定字段（二选一）
- 字段：`merchant_key`, `timestamp`, `order_no` 或 `merchant_order_no`（二选一）

**余额查询**：使用所有字段（除排除字段外）
- 字段：`merchant_key`, `timestamp`

伪代码：
```php
private function generateSign(array $data): string
{
    ksort($data);
    $signString = '';
    foreach ($data as $key => $value) {
        if ($value !== '' && $value !== null) {
            $signString .= (string)$value;
        }
    }
    return md5($signString . $this->merchantSecret);
}
```

---

## 六、常见问题
1. 未收到回调？
   - 确认 `notify_url` 可公网访问，返回 `success`；
   - 检查商户服务器防火墙/证书；
   - 系统具备回调监控与重试，稍后会补偿发送。

2. 查询状态与回调状态不一致？
   - 以查询结果为准；回调失败会重试，短时可能不同步。

3. 回调未收到？
   - 请确认回调地址可公网访问并返回 `success`；系统具备重试与监控补偿能力。

---

## 七、联调建议
- 先在测试环境完成签名联调；
- 使用小额订单验证 创建/查询/回调 全链路；
- 回调端打印并校验签名，返回 `success`；
- 确认异常重试策略符合预期。
- 确认异常重试策略符合预期。