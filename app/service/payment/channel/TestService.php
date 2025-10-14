<?php

namespace app\service\payment\channel;

use app\model\PaymentChannel;
use app\model\Supplier;
use app\exception\MyBusinessException;
use app\service\thirdparty_payment\TestParamGenerator;

/**
 * 支付通道测试服务
 */
class TestService
{
    public function __construct()
    {
        // 无需初始化PaymentManager，直接根据interface_code调用类
    }

    /**
     * 测试支付通道
     * @param int $channelId 通道ID
     * @param array $testParams 测试参数
     * @return array
     * @throws MyBusinessException
     */
    public function testChannel(int $channelId, array $testParams = []): array
    {
        // 获取通道信息
        $channel = PaymentChannel::with('supplier')->find($channelId);
        if (!$channel) {
            throw new MyBusinessException('支付通道不存在');
        }

        // 验证通道状态
        if ($channel->status !== 1) {
            throw new MyBusinessException('支付通道已禁用');
        }

        // 验证供应商状态
        if (!$channel->supplier || $channel->supplier->status !== 1) {
            throw new MyBusinessException('供应商已禁用');
        }

        // 检查通道是否有interface_code
        if (!$channel->interface_code) {
            throw new MyBusinessException('通道接口代码未配置');
        }

        // 验证测试参数
        $this->validateTestParams($testParams, $channel);

        

        try {
            // 根据通道的接口代码直接调用对应的服务类的静态方法
            $serviceClass = $this->getServiceClassByInterfaceCode($channel->interface_code);
            $config = $this->getServiceConfig($channel);

            // 调用支付服务的静态方法
            // TestService 本身就是测试，从配置文件读取测试参数
            $param = $config['test_param'] ?? $config['test_config'] ?? [];
            \support\Log::info('TestService 从配置文件读取测试参数', [
                'interface_code' => $channel->interface_code,
                'test_params' => $param
            ]);
            
            // 从通道获取产品编码，如果通道没有设置则使用默认值
            $productCode = $channel->product_code ?? '';
            $param['product_code'] = $productCode;
            
            // 合并参数：配置文件参数 > 传入的测试参数 > 基础参数
            $param = array_merge($param, $testParams);
            
            // 记录传递给支付服务的参数
            \support\Log::info('TestService 传递给支付服务的参数', [
                'service_class' => $serviceClass,
                'param' => $param,
                'config_keys' => array_keys($config)
            ]);
            
            $result = $serviceClass::processPaymentStatic($param, $config);

            // 获取现有的 header 信息并合并调试信息
            $existingHeader = $result->getHeader();
            $debugInfo = [
                'service_class' => $serviceClass,
                'config_used' => $config,
                'final_request_params' => $param,
                'channel_basic_params' => $channel->basic_params ?? null,
                'config_source' => !empty($channel->basic_params) ? 'channel_basic_params' : 'config_file'
            ];
            
            // 合并现有 header 和调试信息
            $mergedHeader = array_merge($existingHeader, $debugInfo);
            $result->setDebugInfo($mergedHeader);

            // 获取支付结果数组
            $resultArray = $result->toArray();
            
            // 转换状态格式以匹配前端期望
            $resultArray['status'] = $result->isSuccess();
            
            // 如果测试成功，设置统一的二维码URL
            if ($result->isSuccess()) {
                // 使用统一的测试二维码URL
                $resultArray['qr_code'] = $this->getUnifiedTestQRCodeUrl();
            }

            // 调试：记录返回的数据结构
            \support\Log::info('TestService 返回数据', [
                'result_array' => $resultArray,
                'has_header' => isset($resultArray['header']),
                'header_content' => $resultArray['header'] ?? 'no header'
            ]);

            return $resultArray;

        } catch (\Exception $e) {
            echo $e->getFile().':'.$e->getLine().':'.$e->getMessage()."\n";
            
            // 构建最终请求参数（如果可能的话）
            $finalRequestParams = [];
            if (isset($param)) {
                $finalRequestParams = $param;
            }
            
            return [
                'success' => false,
                'message' => '支付服务调用失败: ' . $e->getMessage(),
                'data' => [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->channel_name,
                    'interface_code' => $channel->interface_code,
                    'supplier_name' => $channel->supplier->supplier_name ?? 'Unknown',
                    'error_details' => [
                        'error_class' => get_class($e),
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'error_trace' => $e->getTraceAsString()
                    ],
                    'test_time' => date('Y-m-d H:i:s'),
                    'header' => [
                        'service_class' => $serviceClass ?? 'Unknown',
                        'config_used' => $config ?? [],
                        'final_request_params' => $finalRequestParams,
                        'channel_basic_params' => $channel->basic_params ?? null,
                        'config_source' => !empty($channel->basic_params) ? 'channel_basic_params' : 'config_file'
                    ]
                ]
            ];
        }
    }




    



    /**
     * 根据接口代码获取服务类名
     * @param string $interfaceCode
     * @return string
     * @throws MyBusinessException
     */
    private function getServiceClassByInterfaceCode(string $interfaceCode): string
    {
        // 使用字符串拼接自动生成服务类名
        $serviceClass = "app\\service\\thirdparty_payment\\services\\{$interfaceCode}Service";
        
        // 检查类是否存在
        if (!class_exists($serviceClass)) {
            throw new MyBusinessException("支付服务类不存在: {$serviceClass}");
        }

        return $serviceClass;
    }

    /**
     * 获取服务配置
     * @param PaymentChannel $channel
     * @return array
     */
    private function getServiceConfig(PaymentChannel $channel): array
    {
        $baseConfig = [
            'channel_id' => $channel->id,
            'supplier_id' => $channel->supplier_id,
            'interface_code' => $channel->interface_code,
        ];

        // 从配置文件中读取渠道特定配置（包括 test_param）
        $channelConfig = $this->getChannelConfig($channel->interface_code);
        
        // 合并配置：基础配置 + 配置文件 + 通道基础参数
        $mergedConfig = array_merge($baseConfig, $channelConfig);
        
        // 如果有通道基础参数，也合并进去
        if (!empty($channel->basic_params) && is_array($channel->basic_params)) {
            echo "TestService 合并通道基础参数和配置文件\n";
            \support\Log::info('TestService 合并通道基础参数和配置文件', [
                'interface_code' => $channel->interface_code,
                'channel_id' => $channel->id,
                'basic_params' => $channel->basic_params,
                'has_test_param' => isset($channelConfig['test_param'])
            ]);
            $mergedConfig = array_merge($mergedConfig, $channel->basic_params);
        } else {
            echo "TestService 只使用配置文件，无通道基础参数\n";
        }
        
        // 记录配置加载过程
        \support\Log::info('TestService 配置加载', [
            'interface_code' => $channel->interface_code,
            'base_config' => $baseConfig,
            'channel_config' => $channelConfig,
            'has_test_param' => isset($channelConfig['test_param']),
            'has_test_config' => isset($channelConfig['test_config']),
            'merged_config_keys' => array_keys($mergedConfig)
        ]);

        return $mergedConfig;
    }
    
    /**
     * 获取渠道配置文件
     * @param string $interfaceCode
     * @return array
     */
    private function getChannelConfig(string $interfaceCode): array
    {
        // 直接使用 interface_code 作为配置文件名
        $configFileName = $interfaceCode . '.php';
        echo $configFileName;
        $configPath = __DIR__ . '/../../thirdparty_payment/config/' . $configFileName;
        
        if (!file_exists($configPath)) {
            echo "TestService 配置文件不存在";
            \support\Log::warning('TestService 配置文件不存在', [
                'interface_code' => $interfaceCode,
                'config_file' => $configFileName,
                'config_path' => $configPath
            ]);
            return [];
        }else{
            echo "TestService 配置文件不存在11";
        }
        
        $config = require $configPath;
        \support\Log::info('TestService 加载配置文件成功', [
            'interface_code' => $interfaceCode,
            'config_file' => $configFileName,
            'config_keys' => array_keys($config)
        ]);
        
        return $config;
    }







    /**
     * 获取统一的测试二维码URL
     * @return string
     */
    private function getUnifiedTestQRCodeUrl(): string
    {
        // 返回统一的测试二维码URL
        // 可以根据需要配置不同的测试URL
        return 'https://test-payment.example.com/qr/test-success';
    }

    /**
     * 验证测试参数
     * @param array $testParams
     * @param PaymentChannel $channel
     * @return void
     * @throws MyBusinessException
     */
    private function validateTestParams(array $testParams, PaymentChannel $channel): void
    {
        // 测试模式不进行金额验证，直接通过
        // 产品代码为选填项，不进行验证
    }

}
