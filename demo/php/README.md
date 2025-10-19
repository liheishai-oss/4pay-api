# 4Pay API PHP 对接演示

本目录包含了4Pay API的完整PHP对接演示代码。

## 文件说明

- `4pay_demo.php` - 基础演示脚本，展示API的基本使用方法
- `FourPaySDK.php` - 完整的SDK类，提供封装好的API调用方法
- `example.php` - 使用SDK的示例代码
- `config.php` - 配置文件，包含API地址、商户信息等
- `README.md` - 本说明文档

## 功能特性

### 基础功能
- ✅ 订单创建
- ✅ 订单查询
- ✅ 余额查询
- ✅ 签名生成和验证
- ✅ 回调验证

### 高级功能
- ✅ 数据验证
- ✅ 错误处理
- ✅ 配置管理
- ✅ 产品列表
- ✅ 金额格式化

## 快速开始

### 1. 配置商户信息

编辑 `config.php` 文件，设置正确的商户信息：

```php
'merchant' => [
    'key' => 'MCH_68F0E79CA6E42_20251016',        // 商户Key
    'secret' => 'your_merchant_secret',           // 商户密钥
],
```

### 2. 运行基础演示

```bash
php 4pay_demo.php
```

### 3. 运行SDK示例

```bash
php example.php
```

## 使用方法

### 基础使用

```php
require_once 'FourPaySDK.php';

$sdk = new FourPaySDK();

// 创建订单
$orderData = [
    'merchant_order_no' => 'ORDER_20251019_001',
    'order_amount' => '10.00',
    'product_code' => '8416',
    'order_title' => '测试订单',
    'order_body' => '订单描述'
];

$result = $sdk->createOrder($orderData);
```

### 查询订单

```php
$result = $sdk->queryOrder('BY20251019091434C4CA8582');
```

### 查询余额

```php
$result = $sdk->queryBalance();
```

### 验证回调

```php
$isValid = $sdk->verifyCallback($callbackData);
```

## API接口说明

### 订单创建

**请求参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| merchant_key | string | 是 | 商户唯一标识 |
| merchant_order_no | string | 是 | 商户订单号 |
| order_amount | string | 是 | 订单金额 |
| product_code | string | 是 | 产品编码 |
| notify_url | string | 是 | 异步通知地址 |
| return_url | string | 否 | 同步跳转地址 |
| terminal_ip | string | 是 | 终端IP地址 |
| payer_id | string | 否 | 支付用户ID |
| order_title | string | 否 | 订单标题 |
| order_body | string | 否 | 订单描述 |
| extra_data | string | 否 | 扩展数据 |
| sign | string | 是 | 签名 |

**响应示例：**

```json
{
    "code": 200,
    "status": true,
    "message": "订单创建成功",
    "data": {
        "order_no": "BY20251019091434C4CA8582",
        "trace_id": "050a69a8-56f5-4f08-81fd-654865c78ea7",
        "payment_url": "https://mclient.alipay.lu/v1/alipay/order/payment?require_id=P732025101909165165147"
    }
}
```

### 订单查询

**请求参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| merchant_key | string | 是 | 商户唯一标识 |
| order_no | string | 是 | 平台订单号 |
| timestamp | int | 是 | 时间戳（秒），5分钟内有效 |
| sign | string | 是 | 签名 |

### 余额查询

**请求参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| merchant_key | string | 是 | 商户唯一标识 |
| timestamp | int | 是 | 时间戳（秒），5分钟内有效 |
| sign | string | 是 | 签名 |

## 签名算法

1. 移除 `sign` 字段
2. 按键名排序
3. 构建签名字符串：`key1=value1&key2=value2&...&key=merchant_secret`
4. 生成MD5签名并转大写

## 错误处理

SDK会自动处理以下错误：

- CURL请求错误
- JSON解析错误
- 网络超时
- 数据验证错误

## 注意事项

1. **商户密钥安全**：请妥善保管商户密钥，不要提交到代码仓库
3. **签名验证**：所有请求都必须包含正确的签名
4. **回调验证**：接收回调时必须验证签名
5. **金额格式**：金额必须使用两位小数格式，如 "10.00"

## 技术支持

如有问题，请联系技术支持团队。
