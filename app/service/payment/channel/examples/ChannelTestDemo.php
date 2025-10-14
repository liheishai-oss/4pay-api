<?php

namespace app\service\payment\channel\examples;

/**
 * 支付通道测试演示
 * 展示如何根据interface_code直接调用对应的服务类
 */
class ChannelTestDemo
{
    /**
     * 测试通道对接是否通畅
     * @param array $channelData 通道数据
     * @return array
     */
    public function testChannelConnection(array $channelData): array
    {
        $interfaceCode = $channelData['interface_code'] ?? '';
        $channelId = $channelData['id'] ?? 0;
        $productCode = $channelData['product_code'] ?? '222';
        $paymentAmount = $channelData['payment_amount'] ?? '0.01';

        try {
            // 构建测试参数
            $testParams = $this->buildTestParams($channelData, $paymentAmount, $productCode);
            
            // 使用与TestService一致的类名映射
            $serviceClass = $this->getServiceClassByInterfaceCode($interfaceCode);
            
            // 检查类是否存在
            if (!class_exists($serviceClass)) {
                throw new \Exception("支付服务类不存在: {$serviceClass}");
            }
            
            // 构建服务配置
            $config = $this->getServiceConfig($channelData);
            
            // 调用服务的静态方法进行测试
            $result = $serviceClass::processPaymentStatic($testParams, $config);
            
            return [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => [
                    'channel_id' => $channelId,
                    'interface_code' => $interfaceCode,
                    'service_class' => $serviceClass,
                    'test_params' => $testParams,
                    'config' => $config,
                    'result' => $result->toArray(),
                    'test_time' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '测试失败: ' . $e->getMessage(),
                'data' => [
                    'channel_id' => $channelId,
                    'interface_code' => $interfaceCode,
                    'error_class' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'test_time' => date('Y-m-d H:i:s')
                ]
            ];
        }
    }
    
    /**
     * 根据接口代码获取服务类名（使用字符串拼接）
     * @param string $interfaceCode
     * @return string
     * @throws \Exception
     */
    private function getServiceClassByInterfaceCode(string $interfaceCode): string
    {
        // 使用字符串拼接自动生成服务类名
        $serviceClass = "app\\service\\thirdparty_payment\\services\\{$interfaceCode}Service";
        
        // 检查类是否存在
        if (!class_exists($serviceClass)) {
            throw new \Exception("支付服务类不存在: {$serviceClass}");
        }

        return $serviceClass;
    }
    
    /**
     * 获取服务配置（与TestService保持一致）
     * @param array $channelData
     * @return array
     */
    private function getServiceConfig(array $channelData): array
    {
        $interfaceCode = $channelData['interface_code'] ?? '';
        $baseConfig = [
            'channel_id' => $channelData['id'] ?? 0,
            'supplier_id' => $channelData['supplier_id'] ?? 0,
            'interface_code' => $interfaceCode,
        ];

        // 根据接口代码添加固定配置
        switch ($interfaceCode) {
            case 'GemPayment':
                return array_merge($baseConfig, [
                    'merchant_id' => '10000',
                    'sign_key' => '56043f470017a1d49a4ed703c98d8417',
                    'login_url' => 'http://gem.richman.plus/start/#/login/vip',
                    'order_url' => 'http://gem.richman.plus/api/newOrder',
                    'query_url' => 'http://gem.richman.plus/api/queryOrder',
                    'query_v2_url' => 'http://gem.richman.plus/api/queryOrderV2',
                    'callback_ips' => ['127.0.0.1', '::1']
                ]);
                
            case 'AlipayWeb':
                return array_merge($baseConfig, [
                    'app_id' => '2021000000000000',
                    'private_key' => 'test_private_key',
                    'public_key' => 'test_public_key',
                    'gateway_url' => 'https://openapi.alipay.com/gateway.do',
                    'sign_type' => 'RSA2',
                    'charset' => 'UTF-8',
                    'format' => 'JSON',
                    'version' => '1.0'
                ]);
                
            default:
                return $baseConfig;
        }
    }



    /**
     * 构建测试参数
     * @param array $channelData
     * @param string $paymentAmount
     * @param string $productCode
     * @return array
     */
    private function buildTestParams(array $channelData, string $paymentAmount, string $productCode): array
    {
        $interfaceCode = $channelData['interface_code'] ?? '';
        $timestamp = time();
        $orderNo = 'TEST_' . $interfaceCode . '_' . $timestamp;
        
        // 基础测试参数
        $baseParams = [
            'out_trade_no' => $orderNo,
            'order_id' => $orderNo,
            'total_amount' => (float)$paymentAmount,
            'order_amount' => (float)$paymentAmount,
            'subject' => '通道连接测试 - ' . $interfaceCode,
            'body' => '测试通道连接是否正常',
            'notify_url' => 'https://your-domain.com/notify/test',
            'return_url' => 'https://your-domain.com/return/test',
            'timestamp' => $timestamp
        ];
        
        // 获取渠道类型映射
        $channelTypeMap = $this->getChannelTypeMap();
        $channelType = $channelTypeMap[$interfaceCode] ?? strtolower($interfaceCode);
        
        // 从配置文件中读取渠道特定参数
        $configParams = $this->getChannelConfigParams($channelType);
        
        if (!empty($configParams)) {
            // 处理动态参数
            $configParams = $this->processDynamicConfigParams($configParams, $orderNo, $timestamp);
            
            // 合并配置参数到基础参数
            $baseParams = array_merge($baseParams, $configParams);
        }
        
        // 添加一些动态计算的参数
        $baseParams = $this->addDynamicParams($baseParams, $interfaceCode, $productCode, $paymentAmount);
        
        return $baseParams;
    }
    
    /**
     * 获取渠道类型映射
     * @return array
     */
    private function getChannelTypeMap(): array
    {
        return [
            'GemPayment' => 'gem_payment',
            'AlipayWeb' => 'alipay_web',
            'WechatJsapi' => 'wechat_pay',
            'WechatNative' => 'wechat_pay',
            'UnionPay' => 'union_pay'
        ];
    }
    
    /**
     * 从配置文件获取渠道参数
     * @param string $channelType
     * @return array
     */
    private function getChannelConfigParams(string $channelType): array
    {
        $configPath = __DIR__ . '/../../thirdparty_payment/config/test_config.php';
        
        if (!file_exists($configPath)) {
            return [];
        }
        
        $config = require $configPath;
        
        return $config[$channelType] ?? [];
    }
    
    /**
     * 处理动态配置参数
     * @param array $configParams
     * @param string $orderNo
     * @param int $timestamp
     * @return array
     */
    private function processDynamicConfigParams(array $configParams, string $orderNo, int $timestamp): array
    {
        foreach ($configParams as $key => $value) {
            if (is_string($value)) {
                // 替换时间戳占位符
                if (strpos($value, '{timestamp}') !== false) {
                    $configParams[$key] = str_replace('{timestamp}', $timestamp, $value);
                }
                
                // 替换当前时间戳占位符
                if (strpos($value, '{current_timestamp}') !== false) {
                    $configParams[$key] = str_replace('{current_timestamp}', $timestamp, $value);
                }
                
                // 替换订单号占位符
                if (strpos($value, '{order_no}') !== false) {
                    $configParams[$key] = str_replace('{order_no}', $orderNo, $value);
                }
            }
        }
        
        return $configParams;
    }
    
    /**
     * 添加动态计算的参数
     * @param array $baseParams
     * @param string $interfaceCode
     * @param string $productCode
     * @param string $paymentAmount
     * @return array
     */
    private function addDynamicParams(array $baseParams, string $interfaceCode, string $productCode, string $paymentAmount): array
    {
        switch ($interfaceCode) {
            case 'GemPayment':
                $baseParams['channel_type'] = $this->getPaymentMethodByProductCode($productCode);
                break;
                
            case 'WechatJsapi':
                $baseParams['openid'] = 'test_openid';
                break;
                
            case 'UnionPay':
                $baseParams['txnAmt'] = (int)((float)$paymentAmount * 100); // 转换为分
                $baseParams['txnTime'] = date('YmdHis');
                break;
        }
        
        return $baseParams;
    }
    
    /**
     * 根据产品代码获取支付方式
     * @param string $productCode
     * @return string
     */
    private function getPaymentMethodByProductCode(string $productCode): string
    {
        $mapping = [
            '111' => 'alipay',
            '222' => 'wechat',
            '333' => 'bank',
            '444' => 'unionpay'
        ];
        
        return $mapping[$productCode] ?? 'alipay';
    }


}
