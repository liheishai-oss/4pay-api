# 支付服务自动发现指南

## 概述

支付服务注册系统采用自动发现机制，无需手动配置即可自动识别和注册支付服务类。系统会自动扫描 `services` 目录下的所有支付服务类并自动注册。

## 添加新的支付服务

### 自动发现机制

系统会自动扫描 `app\service\thirdparty_payment\services\` 目录下的所有PHP文件，并自动识别支付服务类。

**服务类命名规则：**
- 类名必须以 `Service` 结尾
- 必须实现 `PaymentServiceInterface` 接口
- 不能是抽象类

**服务类型映射规则：**
- `HaitunService` → `Haitun`
- `AlipayService` → `alipay`
- `WechatService` → `wechat`
- `UnionpayService` → `unionpay`
- `GemPaymentService` → `gem_payment`
- `ExampleService` → `example`

**映射逻辑：**
1. 移除类名末尾的 `Service` 后缀
2. 如果结果为空，则跳过该类
3. 将剩余部分转换为服务类型格式（驼峰转下划线）

### 手动指定服务类型

如果自动映射不符合需求，可以在服务类中实现 `getServiceType()` 方法：

```php
public function getServiceType(): string
{
    return 'custom_service_type';
}
```

### 运行时动态注册（可选）

```php
use app\service\thirdparty_payment\ServiceRegistry;

$registry = new ServiceRegistry();

// 注册单个服务
$registry->registerService('CustomPayment', 'app\service\thirdparty_payment\services\CustomPaymentService');

// 批量注册服务
$registry->registerServices([
    'Payment1' => 'app\service\thirdparty_payment\services\Payment1Service',
    'Payment2' => 'app\service\thirdparty_payment\services\Payment2Service',
]);
```

## 创建新的支付服务类

1. 创建服务类文件 `services/NewPaymentService.php`：

```php
<?php

namespace app\service\thirdparty_payment\services;

use app\service\thirdparty_payment\AbstractUnifiedPaymentService;
use app\service\thirdparty_payment\PaymentResult;

class NewPaymentService extends AbstractUnifiedPaymentService
{
    public function processPayment(array $params): PaymentResult
    {
        // 实现支付逻辑
    }

    public function queryPayment(string $orderNo): PaymentResult
    {
        // 实现查单逻辑
    }

    public function handleCallback(array $callbackData): PaymentResult
    {
        // 实现回调处理逻辑
    }

    public function refund(array $refundParams): PaymentResult
    {
        // 实现退款逻辑
    }

    public function getServiceName(): string
    {
        return '新支付服务';
    }

    public function getServiceType(): string
    {
        return 'new_payment';
    }
}
```

2. 在配置文件中注册服务：

```php
'NewPayment' => 'app\service\thirdparty_payment\services\NewPaymentService',
```

## 使用示例

```php
use app\service\thirdparty_payment\ServiceRegistry;

// 创建服务注册管理器
$registry = new ServiceRegistry();

// 加载所有配置的服务
$registry->loadAllServices();

// 创建支付管理器
$paymentManager = $registry->createPaymentManager();

// 使用支付服务
$result = $paymentManager->queryPayment('NewPayment', $orderNo, $config);
```

## 优势

1. **低耦合**：新增支付服务不需要修改核心代码
2. **灵活性**：支持多种注册方式
3. **可扩展**：支持运行时动态注册
4. **易维护**：配置文件清晰，便于管理
5. **向后兼容**：不影响现有功能

## 注意事项

1. 新的支付服务类必须实现 `PaymentServiceInterface` 接口
2. 服务类型名称应该唯一，避免冲突
3. 配置文件路径要正确，确保文件存在
4. 建议使用自定义配置文件，避免修改默认配置
