## 客户端 API 对接文档

本文档面向商户侧（客户端）对接，包含下单、查询、回调规范、签名说明与错误码示例。

### 环境与基础信息
- 基础路径：`/api/v1`
- 编码：UTF-8，`application/json`
- 时间格式：`YYYY-MM-DD HH:mm:ss`（或 ISO8601）
- 金额单位：分（整数）

### 鉴权
- 管理后台接口受登录与权限控制；
- 商户服务端下单、查询接口通常使用签名（sign）与密钥校验；

如有 Token/JWT 要求，请按实际下发的密钥与示例实现。

---

## 一、创建订单
POST `/api/v1/order/create`

请求参数（JSON）：
```json
{
  "order_no": "自定义系统内唯一订单号，可选",
  "merchant_order_no": "商户侧订单号（可选）",
  "amount": 100,
  "notify_url": "https://merchant.example.com/notify",
  "return_url": "https://merchant.example.com/return",
  "product_code": "10001",
  "channel_id": 11,
  "extra": {"remark": "可选扩展"},
  "timestamp": 1760622000,
  "sign": "签名" 
}
```

参数说明：
- amount：金额，单位分（必填）
- notify_url：异步通知地址（强烈建议提供，便于支付成功后回调）
- return_url：同步跳转地址（可选）
- product_code / channel_id：产品或通道识别（依据分配）
- timestamp：时间戳，单位秒
- sign：签名，见“签名规则”

返回示例：
```json
{
  "code": 200,
  "message": "请求成功",
  "data": {
    "order_no": "BY20251016204701C4CA1207",
    "pay_url": "https://pay.example.com/xxx",
    "status": 1
  }
}
```

状态说明：
- 1 待支付 / 2 支付中 / 3 支付成功 / 6 已关闭

---

## 二、订单查询
GET `/api/v1/order/query`

请求参数（Query）：
```
order_no=BY20251016204701C4CA1207&merchant_order_no=xxx&timestamp=1760622000&sign=签名
```

返回示例：
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "order_no": "BY20251016204701C4CA1207",
    "merchant_order_no": "978-0-461-13992-1",
    "third_party_order_no": "P732025101620470175221",
    "amount": 100,
    "status": 3,
    "status_text": "支付成功",
    "paid_time": "2025-10-16 20:49:52"
  }
}
```

---

## 三、异步回调（服务端通知）
当订单支付成功后，系统会向商户的 `notify_url` 以 `POST` 发送 JSON：

```json
{
  "order_no": "BY20251016204701C4CA1207",
  "merchant_order_no": "978-0-461-13992-1",
  "third_party_order_no": "P732025101620470175221",
  "amount": 100,
  "status": 3,
  "status_text": "支付成功",
  "paid_time": "2025-10-16T12:49:52.000000Z",
  "timestamp": 1760622065,
  "callback_data": {},
  "sign": "签名"
}
```

商户接收后需返回纯文本：`success`（大小写均可）。
- 返回非 `success`、5xx、超时等会被视为失败，系统带有重试与监控补偿。

注意：根据策略，只有 `status=3(支付成功)` 会发送回调；已关闭等状态不回调。

---

## 四、签名规则
示例规则（实际以分配的规则为准）：
1. 参与字段：去除空值后的业务字段 + `timestamp`，不含 `sign` 本身。
2. 字典序升序拼接为 `key1=value1&key2=value2...`。
3. 末尾拼接密钥：`&key=YOUR_SECRET`。
4. 执行 `md5`，并转小写/大写（按约定）。

伪代码：
```php
function sign(array $data, string $secret): string {
    unset($data['sign']);
    $data = array_filter($data, fn($v) => $v !== '' && $v !== null);
    ksort($data);
    $query = http_build_query($data);
    return md5($query . '&key=' . $secret);
}
```

---

## 五、错误码示例
```json
{"code":400, "message":"参数错误"}
{"code":401, "message":"未授权/请登录"}
{"code":403, "message":"无权限"}
{"code":404, "message":"资源不存在"}
{"code":429, "message":"请求过于频繁"}
{"code":500, "message":"服务器内部错误"}
```

---

## 六、常见问题
1. 未收到回调？
   - 确认 `notify_url` 可公网访问，返回 `success`；
   - 检查商户服务器防火墙/证书；
   - 系统具备回调监控与重试，稍后会补偿发送。

2. 查询状态与回调状态不一致？
   - 以查询结果为准；回调失败会重试，短时可能不同步。

3. 超时订单未关闭？
   - 系统有强制超时（默认30分钟，可配置）与供应商失败判定；超过阈值自动关闭。

---

## 七、联调建议
- 先在测试环境完成签名联调；
- 使用小额订单验证 创建/查询/回调 全链路；
- 回调端打印并校验签名，返回 `success`；
- 确认异常重试策略符合预期。


