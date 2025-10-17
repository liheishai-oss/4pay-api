## 客户端 API 对接文档

本文档面向商户侧（客户端）对接，包含下单、查询、回调规范、签名说明与错误码示例。

### 环境与基础信息
- 基础路径：`/api/v1`
- 编码：UTF-8，`application/json`
- 时间格式：`YYYY-MM-DD HH:mm:ss`
- 金额单位：元

### 鉴权
商户服务端接口通过签名（sign）与密钥进行校验，详见“签名规则”。

---

## 一、创建订单
POST `/api/v1/order/create`

请求参数（JSON）：

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| merchant_key | string | 是 | 商户唯一标识 | MCH_68F0E79CA6E42_20251016 |
| nonce | string | 是 | 随机不重复字符串 | 978-0-461-13992-1 |
| merchant_order_no | string | 是 | 商户订单号 | 978-0-461-13992-1 |
| order_amount | string | 是 | 订单金额 | 1.00 |
| product_code | string | 是 | 产品编码 | "8416" |
| notify_url | string | 是 | 异步通知地址（支付成功回调） | http://127.0.0.1/notify |
| return_url | string | 否 | 同步跳转地址 | https://example.com/return |
| is_form | int | 否 | 返回类型：1=form 跳转，2=json 支付链接（默认2） | 2 |
| terminal_ip | string | 是 | 终端IP地址 | 127.0.0.1 |
| payer_id | string | 否 | 终端会员编号 | TEST_USER_001 |
| order_title | string | 否 | 订单标题 | 正常测试订单 |
| order_body | string | 否 | 订单描述 | 这是一个测试订单 |
| sign | string | 是 | 签名（SignatureHelper 规则） | 9f1c...

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

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| order_no | string | 否 | 平台订单号（与 merchant_order_no 二选一） | BY20251016204701C4CA1207 |
| merchant_order_no | string | 否 | 商户订单号（与 order_no 二选一） | M202510160001 |
| sign | string | 是 | 签名，见签名规则 | 9f1c... |

返回示例：
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "order_no": "BY20251016204701C4CA1207",
    "merchant_order_no": "978-0-461-13992-1",
    "third_party_order_no": "P732025101620470175221",
    "amount": "1.00",
    "status": 3,
    "status_text": "支付成功",
    "paid_time": "2025-10-16 20:49:52"
  }
}
```

---

## 三、异步回调（服务端通知）
当订单支付成功后，系统会向商户的 `notify_url` 以 `POST` 发送 JSON：

| 字段 | 类型 | 说明 | 示例 |
| - | - | - | - |
| order_no | string | 平台订单号 | BY20251016204701C4CA1207 |
| merchant_order_no | string | 商户订单号 | M202510160001 |
| third_party_order_no | string | 三方平台订单号 | P732025101620470175221 |
| amount | string | 金额 | 1.00 |
| status | int | 订单状态：3=支付成功 | 3 |
| status_text | string | 状态文本 | 支付成功 |
| paid_time | string | 支付时间（`YYYY-MM-DD HH:mm:ss`） | 2025-10-16 12:49:52 |
| timestamp | int | 时间戳（秒） | 1760622065 |
| sign | string | 回调签名 | 9f1c... |

商户接收后需返回纯文本：`success`（大小写均可）。
- 返回非 `success`、5xx、超时等会被视为失败，系统带有重试与监控补偿。

注意：根据策略，只有 `status=3(支付成功)` 会发送回调；已关闭等状态不回调。

---

## 四、签名规则（基于 SignatureHelper）
签名与验签遵循 `app/common/helpers/SignatureHelper.php`：

规则摘要：
1. 排除字段：`sign`、`client_ip`、`entities_id` 不参与签名。
2. 字段选择：如未指定参与字段列表，默认取请求参数中（去空值后）的全部字段键集合。
3. 排序：对参与的字段名进行字典序排序；
4. 拼接：将参与字段的“值”按排序结果顺序直接拼接为一个字符串（仅值拼接，不包含键名与连接符）；
5. 计算：`md5( hash_hmac('sha256', stringToSign, secretKey) )` 得到签名字符串。

伪代码：
```php
function sign(array $params, string $secretKey, array $signFields = [], string $algo = 'sha256'): string {
    // 自动选择字段
    if (empty($signFields)) {
        ksort($params);
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $signFields[] = $k;
        }
    }
    // 排序并拼接值
    sort($signFields, SORT_STRING);
    $stringToSign = '';
    foreach ($signFields as $field) {
        $stringToSign .= (string)($params[$field] ?? '');
    }
    return md5(hash_hmac($algo, $stringToSign, $secretKey));
}
```

---

## 五、常见问题
1. 未收到回调？
   - 确认 `notify_url` 可公网访问，返回 `success`；
   - 检查商户服务器防火墙/证书；
   - 系统具备回调监控与重试，稍后会补偿发送。

2. 查询状态与回调状态不一致？
   - 以查询结果为准；回调失败会重试，短时可能不同步。

3. 回调未收到？
   - 请确认回调地址可公网访问并返回 `success`；系统具备重试与监控补偿能力。

---

## 六、联调建议
- 先在测试环境完成签名联调；
- 使用小额订单验证 创建/查询/回调 全链路；
- 回调端打印并校验签名，返回 `success`；
- 确认异常重试策略符合预期。


---

## 七、商户信息查询
GET `/api/v1/merchant/info`

请求参数（Query）：

| 字段 | 类型 | 必填 | 说明 | 示例 |
| - | - | - | - | - |
| merchant_key | string | 是 | 商户唯一标识 | MCH_68F0E79CA6E42_20251016 |
| nonce | string | 是 | 随机不重复字符串 | 978-0-461-13992-1 |
| sign | string | 是 | 签名（SignatureHelper 规则） | 9f1c...

成功响应示例：
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "merchant_name": "演示商户",
    "merchant_key": "MCH_68F0E79CA6E42_20251016",
    "status": 1,
    "balance": "1000.00"
  }
}
```

curl 示例：
```bash
curl -G "http://127.0.0.1:8787/api/v1/merchant/info" \
  --data-urlencode "merchant_key=MCH_68F0E79CA6E42_20251016" \
  --data-urlencode "nonce=978-0-461-13992-1" \
  --data-urlencode "sign=替换为签名"
```


