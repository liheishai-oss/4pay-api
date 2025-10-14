<?php

namespace app\api\service\v1\order;

use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\services\HaitunService;
use app\service\thirdparty_payment\PaymentServiceAdapter;
use app\exception\MyBusinessException;
use app\service\monitoring\SupplierResponseMonitor;
use support\Log;

class PaymentExecutor
{
    private $telegramAlertService;
    private $responseMonitor;

    public function __construct(TelegramAlertService $telegramAlertService, SupplierResponseMonitor $responseMonitor = null)
    {
        $this->telegramAlertService = $telegramAlertService;
        $this->responseMonitor = $responseMonitor ?: new SupplierResponseMonitor($telegramAlertService);
    }

    /**
     * 执行支付并实现通道降级
     * @param array $channels 通道列表（已按权重排序）
     * @param array $orderData 订单数据
     * @param array $merchantInfo 商户信息
     * @param array $productInfo 产品信息
     * @return PaymentResult
     * @throws MyBusinessException
     */
    public function executeWithFallback(array $channels, array $orderData, array $merchantInfo, array $productInfo, ?string $traceId = null): PaymentResult
    {
        $failedChannels = [];

        Log::info('开始执行支付流程', [
            'merchant_id' => $merchantInfo['id'],
            'product_id' => $productInfo['id'],
            'order_no' => $orderData['order_no'],
            'available_channels' => count($channels),
            'channel_list' => array_map(function($ch) {
                return ['id' => $ch['id'], 'name' => $ch['name'], 'weight' => $ch['weight']];
            }, $channels),
            'trace_id' => $traceId
        ]);
        foreach ($channels as $channel) {
            $startTime = microtime(true);
            $supplierName = $channel['supplier_name'] ?? '未知供应商';
            $channelName = $channel['name'] ?? '未知通道';

            try {
                Log::info('尝试支付通道', [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channelName,
                    'supplier_name' => $supplierName,
                    'channel_weight' => $channel['weight'] ?? 0,
                    'attempt_number' => count($failedChannels) + 1,
                    'total_channels' => count($channels)
                ]);

                $result = $this->executePayment($channel, $orderData);

                $responseTime = microtime(true) - $startTime;

                // 记录支付结果详情
                Log::debug('支付通道执行结果', [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channelName,
                    'supplier_name' => $supplierName,
                    'result_status' => $result->getStatus(),
                    'result_message' => $result->getMessage(),
                    'is_success' => $result->isSuccess(),
                    'is_failed' => $result->isFailed(),
                    'response_time' => round($responseTime, 3)
                ]);

                // 使用适配器统一返回格式（对所有结果都进行标准化）
                $normalizedResult = PaymentServiceAdapter::normalizePaymentResult(
                    $result, 
                    $supplierName
                );
                
                // 设置使用的通道信息
                $normalizedResult->setUsedChannel($channel);

                if ($normalizedResult->isSuccess()) {
                    // 监控响应时间
                    $this->responseMonitor->monitorResponseTime(
                        $supplierName,
                        $channelName,
                        $channel['id'],
                        $responseTime,
                        $orderData['merchant_order_no'] ?? '',
                        ['order_amount' => $orderData['order_amount'] ?? ''],
                        $traceId
                    );
                    
                    Log::info('支付通道执行成功', [
                        'channel_id' => $channel['id'],
                        'channel_name' => $channelName,
                        'supplier_name' => $supplierName,
                        'response_time' => round($responseTime, 3),
                        'transaction_id' => $normalizedResult->getTransactionId(),
                        'payment_url' => $normalizedResult->getPaymentUrl(),
                        'trace_id' => $traceId
                    ]);
                    return $normalizedResult; // 成功，直接返回
                }

                // 提取请求地址和HTTP状态码
                $requestUrl = '';
                $httpStatus = $normalizedResult->getHttpStatus();
                $rawResponse = $normalizedResult->getRawResponse();
                if (isset($rawResponse['_header']['request_url'])) {
                    $requestUrl = $rawResponse['_header']['request_url'];
                }

                // 监控非正常响应
                if ($httpStatus === 404) {
                    // 404错误特殊处理
                    $this->responseMonitor->monitorAbnormalResponse(
                        $supplierName,
                        $channelName,
                        $channel['id'],
                        'http_404',
                        $normalizedResult->getMessage(),
                        $httpStatus,
                        $orderData['merchant_order_no'] ?? '',
                        [
                            'order_amount' => $orderData['order_amount'] ?? '', 
                            'response_time' => $responseTime,
                            'request_url' => $requestUrl
                        ],
                        $traceId
                    );
                } else {
                    // 其他支付失败正常处理
                    $this->responseMonitor->monitorAbnormalResponse(
                        $supplierName,
                        $channelName,
                        $channel['id'],
                        'payment_failed',
                        $normalizedResult->getMessage(),
                        $httpStatus,
                        $orderData['merchant_order_no'] ?? '',
                        [
                            'order_amount' => $orderData['order_amount'] ?? '', 
                            'response_time' => $responseTime,
                            'request_url' => $requestUrl
                        ],
                        $traceId
                    );
                }

                // 记录失败信息
                $failedChannels[] = [
                    'id' => $channel['id'],
                    'name' => $channelName,
                    'supplier_name' => $supplierName,
                    'error' => $normalizedResult->getMessage(),
                    'response_time' => $responseTime
                ];

                Log::warning('支付通道失败，尝试下一个通道', [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channelName,
                    'supplier_name' => $supplierName,
                    'error' => $normalizedResult->getMessage(),
                    'response_time' => $responseTime,
                    'failed_count' => count($failedChannels),
                    'remaining_channels' => count($channels) - count($failedChannels) - 1
                ]);

            } catch (\Exception $e) {
                $responseTime = microtime(true) - $startTime;
                
                // 提取HTTP状态码和请求地址
                $httpStatus = 0;
                $requestUrl = '';
                if ($e instanceof \app\service\thirdparty_payment\exceptions\PaymentException) {
                    $context = $e->getContext();
                    $httpStatus = $context['http_status'] ?? 0;
                    $requestUrl = $context['url'] ?? '';
                }

                // 根据异常类型和HTTP状态码进行分类
                $errorType = 'exception'; // 默认异常类型
                
                if ($e instanceof \app\service\thirdparty_payment\exceptions\PaymentException) {
                    $errorCode = $e->getErrorCode();
                    switch ($errorCode) {
                        case 'NETWORK_ERROR':
                            if ($httpStatus === 404) {
                                $errorType = 'http_404';
                            } elseif ($httpStatus >= 500) {
                                $errorType = 'server_error';
                            } elseif ($httpStatus >= 400) {
                                $errorType = 'client_error';
                            } else {
                                $errorType = 'network_error';
                            }
                            break;
                        case 'CONFIG_ERROR':
                            $errorType = 'config_error';
                            break;
                        case 'INVALID_PARAMS':
                            $errorType = 'invalid_params';
                            break;
                        case 'BUSINESS_ERROR':
                            $errorType = 'business_error';
                            break;
                        case 'SERVICE_NOT_FOUND':
                            $errorType = 'service_not_found';
                            break;
                        default:
                            $errorType = 'payment_exception';
                    }
                } else {
                    // 非PaymentException的其他异常
                    if ($httpStatus === 404) {
                        $errorType = 'http_404';
                    } elseif ($httpStatus >= 500) {
                        $errorType = 'server_error';
                    } elseif ($httpStatus >= 400) {
                        $errorType = 'client_error';
                    } else {
                        $errorType = 'general_exception';
                    }
                }

                // 记录异常响应（不立即发送告警，等所有通道都失败后再发送）
                $this->responseMonitor->logAbnormalResponse(
                    $supplierName,
                    $channelName,
                    $channel['id'],
                    $errorType,
                    $e->getMessage(),
                    $httpStatus,
                    $orderData['merchant_order_no'] ?? ''
                );

                $failedChannels[] = [
                    'id' => $channel['id'],
                    'name' => $channelName,
                    'supplier_name' => $supplierName,
                    'error' => $e->getMessage()
                ];

                Log::error('支付通道执行异常，尝试下一个通道', [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channelName,
                    'supplier_name' => $supplierName,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'response_time' => round($responseTime, 3),
                    'http_status' => $httpStatus,
                    'request_url' => $requestUrl,
                    'failed_count' => count($failedChannels),
                    'remaining_channels' => count($channels) - count($failedChannels) - 1,
                    'trace_id' => $traceId
                ]);
            }

        }

        // 所有通道都失败，发送告警
        Log::error('所有支付通道执行失败', [
            'merchant_id' => $merchantInfo['id'],
            'product_id' => $productInfo['id'],
            'order_no' => $orderData['merchant_order_no'],
            'failed_channels_count' => count($failedChannels),
            'failed_channels' => $failedChannels,
            'trace_id' => $traceId
        ]);

        $this->telegramAlertService->sendAllChannelsFailedAlert(
            $merchantInfo,
            $productInfo,
            $orderData,
            $failedChannels,
            $traceId
        );
        
        // 发送供应商非正常响应告警（只有在所有通道都失败的情况下）
        $this->responseMonitor->handleAllChannelsFailedAbnormalResponse(
            $failedChannels,
            $orderData['merchant_order_no'] ?? '',
            [
                'merchant_id' => $merchantInfo['id'],
                'merchant_name' => $merchantInfo['merchant_name'],
                'product_id' => $productInfo['id'],
                'product_name' => $productInfo['product_name'],
                'order_amount' => $orderData['order_amount'] ?? 0
            ],
            $traceId
        );

        // 构建详细的错误信息
        $errorMessages = [];
        foreach ($failedChannels as $failedChannel) {
            $errorMessages[] = "{$failedChannel['name']}: {$failedChannel['error']}";
        }
        $detailedError = implode('; ', $errorMessages);
        
        throw new MyBusinessException("所有支付通道都失败了: {$detailedError}");
    }

    /**
     * 执行单个通道的支付
     * @param array $channel
     * @param array $orderData
     * @return PaymentResult
     * @throws MyBusinessException
     */
    private function executePayment(array $channel, array $orderData): PaymentResult
    {
        try {
            // 根据通道接口代码选择支付服务
            $paymentService = $this->getPaymentService($channel['interface_code'], $channel); // 传递通道信息
            
            // 构建支付参数
            $paymentParams = $this->buildPaymentParams($channel, $orderData);
            
            // 调用支付接口
            return $paymentService->processPayment($paymentParams);
            
        } catch (MyBusinessException $e) {
            // 业务异常（如支付服务类不存在）直接返回失败结果
            return PaymentResult::failed('支付服务错误: ' . $e->getMessage());
        } catch (\Exception $e) {
            return PaymentResult::failed('支付处理失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取支付服务实例
     * @param string $interfaceCode
     * @param array $channel 通道信息
     * @return object
     * @throws MyBusinessException
     */
    private function getPaymentService(string $interfaceCode, array $channel = []): object
    {
        $serviceClass = "app\\service\\thirdparty_payment\\services\\{$interfaceCode}Service";
        
        \support\Log::info('尝试加载支付服务', [
            'interface_code' => $interfaceCode,
            'service_class' => $serviceClass,
            'class_exists' => class_exists($serviceClass)
        ]);
        
        if (!class_exists($serviceClass)) {
            \support\Log::error('支付服务类不存在', [
                'interface_code' => $interfaceCode,
                'service_class' => $serviceClass
            ]);
            throw new MyBusinessException("不支持的支付通道: {$interfaceCode} (类: {$serviceClass})");
        }
        
        // 加载配置
        $config = $this->getChannelConfig($interfaceCode, $channel);
        
        \support\Log::info('支付服务配置', [
            'interface_code' => $interfaceCode,
            'config' => $config
        ]);
        
        return new $serviceClass($config);
    }
    
    /**
     * 获取通道配置
     * @param string $interfaceCode
     * @param array $channel 通道信息，包含基础参数
     * @return array
     */
    private function getChannelConfig(string $interfaceCode, array $channel = []): array
    {
        // 优先从通道基础参数获取配置
        if (!empty($channel['basic_params']) && is_array($channel['basic_params'])) {
            \support\Log::info('从通道基础参数获取配置', [
                'interface_code' => $interfaceCode,
                'channel_id' => $channel['id'] ?? 'unknown',
                'basic_params' => $channel['basic_params']
            ]);
            return $channel['basic_params'];
        }
        
        // 回退到配置文件
        $configFileName = $interfaceCode . '.php';
        $configPath = __DIR__ . '/../../../../service/thirdparty_payment/config/' . $configFileName;
        
        if (!file_exists($configPath)) {
            \support\Log::warning('配置文件不存在', [
                'interface_code' => $interfaceCode,
                'config_file' => $configFileName,
                'config_path' => $configPath
            ]);
            return [];
        }
        
        $config = require $configPath;
        
        \support\Log::info('从配置文件加载配置', [
            'interface_code' => $interfaceCode,
            'config_file' => $configFileName,
            'config_keys' => array_keys($config)
        ]);
        
        return $config;
    }

    /**
     * 构建支付参数
     * @param array $channel
     * @param array $orderData
     * @return array
     */
    private function buildPaymentParams(array $channel, array $orderData): array
    {
        $baseParams = [
            'merchant_id'  => $orderData['merchant_id'],
            'order_no'     => $orderData['order_no'],
            'order_amount' => $orderData['order_amount'],
            'notify_url'   => $orderData['notify_url'],
            'return_url'   => $orderData['return_url'] ?? '',
            'subject'      => '订单支付',
            'body'         => '',
            'timestamp'    => time(),
            'client_ip'    => $orderData['terminal_ip'] ?? '' // 将terminal_ip作为client_ip传递给支付服务
        ];

        // 统一使用标准参数格式，产品编码从通道获取
        $baseParams['product_code'] = $channel['product_code'] ?? '';
        
        // 添加调试日志
        \support\Log::info('PaymentExecutor 构建支付参数', [
            'channel_data' => $channel,
            'product_code_from_channel' => $channel['product_code'] ?? 'not_set',
            'final_product_code' => $baseParams['product_code'],
            'client_ip' => $baseParams['client_ip']
        ]);
        
        return $baseParams;
    }
}
