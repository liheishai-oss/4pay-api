# 4Pay API PHP Demo 使用说明

## 目录结构

```
demo/php/
├── 4pay_demo.php          # 基础演示脚本
├── FourPaySDK.php         # 完整SDK类
├── example.php            # SDK使用示例
├── config.php             # 配置文件
├── simple_test.php        # 简单测试脚本
├── docker_demo.php        # Docker环境演示
├── README.md              # 详细说明文档
└── USAGE.md              # 本使用说明
```

## 快速开始

### 1. 在Docker容器内运行（推荐）

```bash
# 进入Docker容器
docker exec -it php82 bash

# 运行基础演示
cd /www/4pay/4pay-api/demo/php
php docker_demo.php

# 运行简单测试
php simple_test.php
```

### 2. 在宿主机运行

```bash
# 运行基础演示（需要API服务运行在8787端口）
cd /Users/apple/dnmp/www/4pay/4pay-api/demo/php
php 4pay_demo.php

# 运行SDK示例
php example.php
```

## 功能演示

### 1. 余额查询
- 查询商户账户余额
- 显示可提现金额和冻结金额

### 2. 订单创建
- 创建支付订单
- 获取支付链接
- 记录追踪ID

### 3. 订单查询
- 查询订单状态
- 获取订单详情
- 验证订单信息

## 文件说明

### 4pay_demo.php
基础演示脚本，展示API的基本使用方法：
- 生成签名
- 发送HTTP请求
- 处理响应数据
- 错误处理

### FourPaySDK.php
完整的SDK类，提供封装好的API调用方法：
- 自动签名生成
- 数据验证
- 错误处理
- 回调验证

### example.php
使用SDK的示例代码：
- 创建SDK实例
- 调用API方法
- 处理响应数据
- 验证订单数据

### config.php
配置文件，包含：
- API基础URL
- 商户信息
- 产品配置
- 订单配置

### docker_demo.php
专门为Docker环境设计的演示脚本：
- 在容器内运行
- 完整的API调用流程
- 详细的请求和响应日志

## 运行环境

### 系统要求
- PHP 7.4+
- cURL扩展
- JSON扩展

### 网络要求
- 能够访问API服务（127.0.0.1:8787）
- 在Docker环境中运行

## 常见问题

### 1. 连接失败
```
CURL错误: Failed to connect to 127.0.0.1 port 8787
```
**解决方案**：确保API服务正在运行，或在Docker容器内运行demo。

### 2. 签名验证失败
```
查询失败: 参数格式错误
```
**解决方案**：在debug模式下，签名验证会被跳过，这是正常的。

### 3. 请求重复
```
请求重复
```

## 开发指南

### 1. 修改配置
编辑 `config.php` 文件，设置正确的商户信息：
```php
'merchant' => [
    'key' => 'YOUR_MERCHANT_KEY',
    'secret' => 'YOUR_MERCHANT_SECRET',
],
```

### 2. 添加新功能
在 `FourPaySDK.php` 中添加新的API方法：
```php
public function newApiMethod(array $data): array
{
    // 实现新功能
}
```

### 3. 自定义错误处理
重写SDK类的错误处理方法：
```php
private function handleError(Exception $e): void
{
    // 自定义错误处理逻辑
}
```

## 技术支持

如有问题，请检查：
1. API服务是否正常运行
2. 网络连接是否正常
3. 配置文件是否正确
4. PHP环境是否满足要求
