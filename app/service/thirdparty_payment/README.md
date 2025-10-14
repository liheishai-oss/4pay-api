# 第三方支付服务架构

## 概述

这是一个企业级的第三方支付服务架构，采用工厂模式、观察者模式和策略模式，提供统一的支付服务入口和可扩展的架构设计。

## 架构特点

### 1. 统一入口
- 通过 `PaymentManager` 提供统一的支付服务入口
- 支持动态创建不同的支付服务实例
- 统一的错误处理和结果封装

### 2. 观察者模式
- 支持多个观察者监听支付事件
- 支付成功、失败、处理中等事件通知
- 可扩展的通知机制（日志、邮件、短信等）

### 3. 可扩展性
- 新增支付服务只需实现 `PaymentServiceInterface` 接口
- 在工厂中注册新服务类型即可使用
- 支持自定义观察者

### 4. 规范化
- 遵循企业级目录结构和命名规范
- 完整的异常处理机制
- 统一的日志记录

## 目录结构

```
app/service/thirdparty_payment/
├── interfaces/                 # 接口定义
│   ├── PaymentServiceInterface.php
│   ├── PaymentObserverInterface.php
│   └── PaymentFactoryInterface.php
├── services/                   # 具体支付服务实现
│   └── AlipayWebService.php
├── observers/                  # 观察者实现
│   ├── PaymentLogObserver.php
│   └── PaymentNotificationObserver.php
├── factories/                  # 工厂类
│   └── PaymentServiceFactory.php
├── exceptions/                 # 异常类
│   └── PaymentException.php
├── enums/                      # 枚举类
│   └── PaymentServiceType.php
├── AbstractPaymentService.php  # 抽象基类
├── PaymentResult.php           # 支付结果类
├── PaymentObserverManager.php  # 观察者管理器
├── PaymentManager.php          # 统一支付管理器
├── PaymentServiceExample.php   # 使用示例
└── README.md                   # 文档
```

## 核心组件

### 1. PaymentManager（统一支付管理器）
- 提供统一的支付服务入口
- 管理观察者和服务实例
- 处理支付、查询、回调、退款等操作

### 2. PaymentServiceFactory（支付服务工厂）
- 动态创建支付服务实例
- 支持服务类型注册
- 提供服务类型验证

### 3. PaymentObserverManager（观察者管理器）
- 管理所有支付观察者
- 支持事件过滤
- 提供观察者通知机制

### 4. PaymentResult（支付结果类）
- 统一封装支付操作结果
- 提供状态判断方法
- 支持数据转换

## 使用示例

### 基本使用

```php
use app\service\thirdparty_payment\PaymentManager;
use app\service\thirdparty_payment\observers\PaymentLogObserver;

// 创建支付管理器
$paymentManager = new PaymentManager();

// 添加观察者
$paymentManager->addObserver(new PaymentLogObserver());

// 处理支付
$result = $paymentManager->processPayment('alipay_web', [
    'out_trade_no' => 'ORDER_123456',
    'total_amount' => '100.00',
    'subject' => '测试订单'
], [
    'app_id' => 'your_app_id',
    'private_key' => 'your_private_key'
]);

if ($result->isSuccess()) {
    echo "支付成功: " . $result->getMessage();
} else {
    echo "支付失败: " . $result->getMessage();
}
```

### 添加自定义观察者

```php
use app\service\thirdparty_payment\interfaces\PaymentObserverInterface;
use app\service\thirdparty_payment\PaymentResult;

class CustomObserver implements PaymentObserverInterface
{
    public function onPaymentSuccess(PaymentResult $result): void
    {
        // 处理支付成功逻辑
    }

    public function onPaymentFailed(PaymentResult $result): void
    {
        // 处理支付失败逻辑
    }

    // ... 实现其他方法

    public function getObserverName(): string
    {
        return 'CustomObserver';
    }
}

// 注册观察者
$paymentManager->addObserver(new CustomObserver());
```

### 添加自定义支付服务

```php
use app\service\thirdparty_payment\AbstractPaymentService;
use app\service\thirdparty_payment\PaymentResult;

class CustomPaymentService extends AbstractPaymentService
{
    public function processPayment(array $params): PaymentResult
    {
        // 实现支付逻辑
        return PaymentResult::success('支付成功');
    }

    // ... 实现其他必需方法
}

// 注册服务
$paymentManager->registerService('custom_payment', CustomPaymentService::class);
```

## 支持的服务类型

- 支付宝：网页支付、APP支付、手机网站支付、扫码支付
- 微信支付：JSAPI支付、APP支付、H5支付、扫码支付、刷卡支付
- 银联支付：网页支付、APP支付、手机支付
- 其他：PayPal、Stripe、Square等

## 扩展指南

### 1. 添加新的支付服务
1. 创建服务类，继承 `AbstractPaymentService`
2. 实现 `PaymentServiceInterface` 接口
3. 在工厂中注册服务类型

### 2. 添加新的观察者
1. 创建观察者类，实现 `PaymentObserverInterface` 接口
2. 在 `PaymentManager` 中注册观察者

### 3. 添加新的异常类型
1. 继承 `PaymentException` 基类
2. 定义具体的异常类型和错误代码

## 配置说明

每个支付服务都需要相应的配置参数，例如：

```php
$config = [
    'app_id' => 'your_app_id',
    'private_key' => 'your_private_key',
    'public_key' => 'public_key',
    'gateway_url' => 'https://api.example.com',
    'notify_url' => 'https://your-domain.com/notify',
    'return_url' => 'https://your-domain.com/return'
];
```

## 注意事项

1. 所有支付服务都应该是无状态的
2. 观察者通知失败不应影响主流程
3. 敏感信息（如密钥）应通过配置文件管理
4. 建议使用依赖注入容器管理服务实例
5. 生产环境应启用详细的日志记录

## 最佳实践

1. 使用工厂模式创建服务实例
2. 通过观察者模式解耦业务逻辑
3. 统一的异常处理和错误码
4. 完整的单元测试覆盖
5. 定期更新第三方SDK版本


