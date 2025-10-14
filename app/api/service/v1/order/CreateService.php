<?php

namespace app\api\service\v1\order;

// 第三方库
use app\model\Product;
use Carbon\Carbon;

// 框架支持类
use support\Db;
use support\Log;
use support\Redis;
use support\Request;

// 应用常量
use app\common\constants\OrderConstants;
use app\common\constants\SystemConstants;
use app\common\config\OrderConfig;
use app\enums\OrderStatus;

// 应用异常
use app\exception\MyBusinessException;

// 应用模型
use app\model\Merchant;
use app\model\Order;
use app\model\PaymentChannel;

// 应用服务
use app\api\repository\v1\order\OrderRepository;
use app\api\validator\v1\order\CreateBusinessDataValidator;
use app\api\service\v1\order\EnterpriseStatusValidator;
use app\api\service\v1\order\IntelligentChannelSelector;
use app\api\service\v1\order\PaymentExecutor;
use app\api\service\v1\order\PollingPoolSelector;
use app\api\service\v1\order\TelegramAlertService;
use app\service\monitoring\SupplierResponseMonitor;
use app\service\thirdparty_payment\PaymentResult;
use app\service\thirdparty_payment\services\AlipayWebService;
use app\service\thirdparty_payment\services\GemPaymentService;

// 应用助手类
use app\common\helpers\CacheHelper;
use app\common\helpers\CacheKeys;
use app\common\helpers\MoneyHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\QueryCacheHelper;
use app\common\helpers\TraceIdHelper;

class CreateService
{
    protected OrderRepository $repository;
    protected CreateBusinessDataValidator $validator;
    protected PollingPoolSelector $pollingPoolSelector;
    protected TelegramAlertService $telegramAlertService;
    protected PaymentExecutor $paymentExecutor;
    protected EnterpriseStatusValidator $statusValidator;
    protected IntelligentChannelSelector $channelSelector;
    protected SupplierResponseMonitor $responseMonitor;

    public function __construct(
        OrderRepository $repository, 
        CreateBusinessDataValidator $validator,
        PollingPoolSelector $pollingPoolSelector,
        TelegramAlertService $telegramAlertService,
        PaymentExecutor $paymentExecutor,
        EnterpriseStatusValidator $statusValidator,
        IntelligentChannelSelector $channelSelector,
        SupplierResponseMonitor $responseMonitor
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->pollingPoolSelector = $pollingPoolSelector;
        $this->telegramAlertService = $telegramAlertService;
        $this->paymentExecutor = $paymentExecutor;
        $this->statusValidator = $statusValidator;
        $this->channelSelector = $channelSelector;
        $this->responseMonitor = $responseMonitor;
    }

    /**
     * 创建订单主流程
     * 
     * 业务流程说明：
     * 1. 参数验证和商户验证
     * 2. 幂等性检查（防重复提交）
     * 3. 分布式锁（防并发冲突）
     * 4. 产品验证和通道选择
     * 5. 订单创建和支付执行
     * 6. 响应格式化和缓存更新
     * 
     * 性能优化：
     * - 使用3级缓存提升查询性能
     * - 分布式锁防止并发冲突
     * - 批量查询减少数据库访问
     * 
     * 安全措施：
     * - 参数严格验证
     * - 签名验证防篡改
     * - 异常信息脱敏处理
     * 
     * @param array $data 订单请求数据
     * @return array 支付响应数据
     * @throws MyBusinessException 业务异常
     */
    public function createOrder(array $data): array
    {
        // 开始数据库事务
        Db::beginTransaction();
        
        // 初始化变量，避免在异常处理中未定义
        $merchant = null;
        $lockKey = null;
        $lockAcquired = false;
        
        try {
            // Debug模式：自动生成模拟参数
            if (config('app.debug', false)) {
                $data = $this->generateDebugParams($data);
            }

            $merchant = Request()->merchant;

            try {
                $this->statusValidator->validateMerchantStatus($merchant);
            } catch (MyBusinessException $e) {
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }

            $merchantContext = [
                'id'            => $merchant['id'],
                'merchant_name' => $merchant['merchant_name'],
                'merchant_key'  => $merchant['merchant_key']
            ];


            // 2. 使用分布式锁确保商户订单号唯一性
            $lockKey = "order_lock:{$merchant['id']}:{$data['merchant_order_no']}";
            $lockAcquired = $this->acquireDistributedLock($lockKey, OrderConfig::LOCK_TIMEOUT_SECONDS);
            if (!$lockAcquired) {
                Db::rollback();
                return [
                    'code' => 409,
                    'status' => false,
                    'message' => '订单正在处理中，请稍后重试',
                    'data' => []
                ];
            }

            // 2.1 验证商户订单号唯一性（双重检查）
            if ($this->repository->isMerchantOrderNoExistsWithCache($data['merchant_order_no'], $merchant['id'])) {
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => 409,
                    'status' => false,
                    'message' => '商户订单号已存在',
                    'data' => []
                ];
            }

            // 3. 获取产品信息并验证状态（使用3级缓存）
            // 通过产品代码获取产品ID
            try {
                $productId = $this->getProductIdByCode($data['product_code']);
            } catch (MyBusinessException $e) {
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }
            try {
                $product = MultiLevelCacheHelper::getOrderProduct(
                    CacheKeys::getProductInfo($productId),
                    function() use ($productId) {
                        $product = $this->statusValidator->validateProductStatus($productId);
                        
                        // 缓存空值结果，防止缓存穿透（仅在Redis正常时）
                        if (!$product) {
                            try {
                                $cacheKey = CacheKeys::getProductInfo($productId);
                                Redis::setex($cacheKey, OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL, 'NULL');
                                Log::info('产品不存在，已缓存空值', [
                                    'product_id' => $productId,
                                    'cache_key' => $cacheKey,
                                    'ttl' => OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL
                                ]);
                            } catch (\Throwable $redisException) {
                                // Redis异常时记录警告但不影响业务逻辑
                                Log::warning('Redis写入失败，跳过空值缓存', [
                                    'product_id' => $productId,
                                    'error' => $redisException->getMessage()
                                ]);
                            }
                        }
                        
                        return $product;
                    }
                );
                if (!$product) {
                    $this->releaseDistributedLock($lockKey);
                    Db::rollback();
                    return [
                        'code' => 400,
                        'status' => false,
                        'message' => '产品不存在',
                        'data' => []
                    ];
                }
                $productInfo = [
                    'id' => $product['id'],
                    'product_name' => $product['product_name'],
                    'external_code' => $product['external_code']
                ];
            } catch (MyBusinessException $e) {
                // 产品状态异常，发送告警
                $this->telegramAlertService->sendProductNotConfiguredAlert(
                    $merchant['id'],
                    $productId,
                    $data,
                    TraceIdHelper::get()
                );
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }

            // 4. 企业级通道验证和选择
            /**
             * 通道选择策略说明：
             * 1. 根据产品ID和订单金额获取所有可用支付通道
             * 2. 使用3级缓存优化性能：L1(内存) + L2(Redis) + L3(数据库)
             * 3. 支持多通道降级机制，确保支付成功率
             * 4. 通道选择策略：权重排序 + 实时状态检查
             */
            try {
                Log::info('开始企业级通道验证', [
                    'product_id' => $productId,
                    'order_amount_cents' => $data['order_amount_cents'],
                    'merchant_id' => $merchant['id'],
                    'trace_id' => TraceIdHelper::get()
                ]);

                // 使用3级缓存获取可用通道，提升查询性能
                $channels = MultiLevelCacheHelper::getOrderChannel(
                    CacheKeys::getAvailableChannels($productId, $data['order_amount_cents']),
                    fn () => $this->channelSelector->getAllAvailableChannels($productId, $data['order_amount_cents'])
                );
                
                // 实时验证通道状态，确保通道开关后立即生效
                $channels = $this->validateChannelsRealTimeStatus($channels, $productId);

            } catch (MyBusinessException $e) {
                Log::error('企业级通道验证失败', [
                    'product_id' => $productId,
                    'merchant_id' => $merchant['id'],
                    'error' => $e->getMessage(),
                    'trace_id' => TraceIdHelper::get()
                ]);

                // 发送告警
                $this->telegramAlertService->sendNoAvailablePoolAlert(
                    $merchantContext,
                    $productInfo,
                    $data,
                    TraceIdHelper::get()
                );
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }


            // 5. 业务数据验证（简化版，因为状态验证已在上面完成）
            try {
                $this->validator->validateBasicData($data, $merchant);
            } catch (MyBusinessException $e) {
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }

            // 6. 生成订单信息
            $platformOrderNo = $this->repository->generatePlatformOrderNo($merchant['id']);
            $traceId = TraceIdHelper::get();

            // 7. 验证商户产品关系状态
            try {
                $productMerchant = $this->statusValidator->validateProductMerchantStatus($merchant['id'], $productId);
            } catch (MyBusinessException $e) {
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }
            
            // 8. 查询商户费率
            $merchantRate = $this->repository->getMerchantRate($merchant['id'], $productId);

            // 9. 创建订单记录（使用第一个通道作为默认通道，支付时可能切换）
            if (empty($channels)) {
                $this->releaseDistributedLock($lockKey);
                Db::rollback();
                return [
                    'code' => 400,
                    'status' => false,
                    'message' => '没有可用的支付通道，请联系客服',
                    'data' => []
                ];
            }

            $primaryChannel = $channels[0]; // 使用第一个通道作为默认
            $primaryFee = $this->calculateFee($data['order_amount_cents'], $merchantRate);

            $orderData = [
                'order_no'             => $platformOrderNo,
                'merchant_order_no'    => $data['merchant_order_no'],
                'third_party_order_no' => '',
                'trace_id'             => $traceId,
                'merchant_id'          => $merchant['id'],
                'product_id'           => $productId,
                'channel_id'           => $primaryChannel['id'], // 使用默认通道
                'amount'               => $data['order_amount_cents'],
                'fee'                  => $primaryFee, // 使用默认通道费率
                'status'               => OrderStatus::PENDING,
                'payment_method'       => $primaryChannel['interface_code'], // 使用默认通道的接口代码（来自供应商）
                'notify_url'           => $data['notify_url'],
                'return_url'           => $data['return_url'] ?? '',
                'client_ip'            => $_SERVER['REMOTE_ADDR'] ?? '',
                'terminal_ip'          => $data['terminal_ip'], // 终端IP地址
                'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'subject'              => $data['subject'] ?? '订单支付',
                'body'                 => '',
                'extra_data'           => json_encode([
                    'available_channels' => $channels, // 保存所有可用通道信息
                    'selection_strategy' => 'enterprise_validation',
                    'default_channel_id' => $primaryChannel['id'] // 记录默认通道ID
                ]),
                'third_party_response' => null,
                'notify_count'         => 0,
                'notify_status'        => 0,
                'expire_time'          => Carbon::now()->addMinutes(OrderConfig::DEFAULT_EXPIRE_MINUTES), // 30分钟过期
                'paid_time'            => null
            ];

            $order = $this->repository->createOrder($orderData);

            // 9.1 订单创建成功后，缓存订单号存在状态
            $this->repository->cacheOrderCreated($data['merchant_order_no'], $merchant['id']);
            

            // 8. 执行支付（带降级机制）
            $paymentRequest = array_merge($data, [
                'merchant_id'      => $merchant['id'],
                'order_amount'     => MoneyHelper::convertToYuan($data['order_amount_cents']),
                'order_no' => $orderData['order_no']
            ]);

            try {
                $paymentResponse = $this->paymentExecutor->executeWithFallback(
                    $channels,
                    $paymentRequest,
                    $merchantContext,
                    $productInfo,
                    $traceId
                );
            } catch (MyBusinessException $e) {
                // 支付失败，更新订单状态为失败
                $this->repository->updateOrder($order->id, [
                    'status' => OrderStatus::FAILED,
                    'third_party_response' => json_encode(['error' => $e->getMessage()])
                ]);
                
                // 订单创建失败，清理缓存
                $this->repository->clearOrderCache($data['merchant_order_no'], $merchant['id']);
                
                // 提交事务（确保失败状态被保存）
                Db::commit();
                $this->releaseDistributedLock($lockKey);
                
                // 直接返回失败响应，不抛出异常避免重复处理
                return [
                    'code' => 400,
                    'status' => false,
                    'message' => '订单创建失败，请联系客服',
                    'data' => []
                ];
            }

            // 9. 更新订单状态和通道信息
            if ($paymentResponse->isSuccess()) {
                // 获取实际使用的通道信息
                $actualChannel = $paymentResponse->getUsedChannel();
                $updateData = [
                    'third_party_order_no'  => $paymentResponse->getTransactionId(),
                    'status'                => OrderStatus::PAYING,
                    'third_party_response'  => json_encode($paymentResponse->getRawResponse())
                ];
                if ($actualChannel['id'] !== $primaryChannel['id']) {
                    $actualFee = $this->calculateFee($data['order_amount_cents'], $merchantRate);
                    $updateData['channel_id'] = $actualChannel['id'];
                    $updateData['payment_method'] = $actualChannel['interface_code']; // 来自供应商的接口代码
                    $updateData['fee'] = $actualFee;
                }
                $this->repository->updateOrder($order->id, $updateData);
            }

            // 提交事务
            Db::commit();
            
            
            // 释放分布式锁
            $this->releaseDistributedLock($lockKey);

            // 10. 返回支付信息
            return $this->formatPaymentResponse($order, $paymentResponse, $data, $merchant);

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            
            // 订单创建失败，清理缓存
            if (isset($data['merchant_order_no']) && isset($merchant['id'])) {
                $this->repository->clearOrderCache($data['merchant_order_no'], $merchant['id']);
            }
            
            // 释放分布式锁（如果已获取）
            if (isset($lockKey) && isset($lockAcquired) && $lockAcquired) {
                $this->releaseDistributedLock($lockKey);
            }
            
            // 记录异常详情
            Log::error('订单创建异常', [
                'merchant_id' => $merchant['id'] ?? null,
                'merchant_order_no' => $data['merchant_order_no'] ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace_id' => TraceIdHelper::get()
            ]);
            
            // 根据异常类型返回用户友好的错误信息
            if ($e instanceof MyBusinessException) {
                // 业务异常直接返回错误响应，不抛出异常
                return [
                    'code' => $e->getCode() ?: 400,
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ];
            }
            
            // 系统异常返回通用错误信息
            return [
                'code' => 500,
                'status' => false,
                'message' => '订单创建失败，请稍后重试',
                'data' => []
            ];
        }
    }


    /**
     * 计算手续费
     * 
     * 计算逻辑说明：
     * 1. 费率以整数形式存储（如10%存储为10）
     * 2. 转换为小数：costRate / 100
     * 3. 计算手续费：amount * rate
     * 4. 四舍五入到整数（分）
     * 
     * @param int $amount 订单金额（分）
     * @param int $costRate 费率（整数形式，如10%存储为10）
     * @return int 手续费（分）
     */
    private function calculateFee(int $amount, int $costRate): int
    {
        if ($costRate <= 0) {
            return 0;
        }
        
        // 费率从整数转换为小数：costRate / 100
        $rate = $costRate / 100;
        $fee = (int) round($amount * $rate);
        
        return $fee;
    }

    /**
     * 处理支付
     * @param Order $order
     * @param PaymentChannel $channel
     * @param array $data
     * @return PaymentResult
     */
    private function processPayment(Order $order, PaymentChannel $channel): PaymentResult
    {
        try {
            // 根据通道类型选择支付服务
            $paymentService = $this->getPaymentService($channel->interface_code, $channel);
            
            // 构建支付参数
            $paymentParams = $this->buildPaymentParams($order, $channel);
            
            // 调用支付接口
            return $paymentService->processPayment($paymentParams);
            
        } catch (\Exception $e) {
            return PaymentResult::failed('支付处理失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取支付服务实例
     * @param string $interfaceCode
     * @param PaymentChannel $channel 通道信息
     * @return object
     * @throws MyBusinessException
     */
    private function getPaymentService(string $interfaceCode, PaymentChannel $channel): object
    {
        $serviceClass = "app\\service\\thirdparty_payment\\services\\{$interfaceCode}Service";
        
        if (!class_exists($serviceClass)) {
            throw new MyBusinessException("不支持的支付通道: {$interfaceCode}");
        }
        
        // 从通道基础参数获取配置
        $config = $channel->basic_params ?? [];
        
        return new $serviceClass($config);
    }

    /**
     * 通过产品代码获取产品ID
     * @param string $productCode
     * @return int
     * @throws MyBusinessException
     */
    private function getProductIdByCode(string $productCode): int
    {
        $product = QueryCacheHelper::getCacheOrQuery(
            CacheKeys::getProductCodeQuery($productCode),
            fn() => Product::where('external_code', $productCode)
                ->where('status', 1)
                ->first()
                ?->toArray(),
            600 // 10分钟缓存
        );
            
        if (!$product || empty($product)) {
            throw new MyBusinessException('产品代码不存在或已删除');
        }
        
        return $product['id'];
    }

    /**
     * 构建支付参数
     * @param Order $order
     * @param PaymentChannel $channel
     * @return array
     */
    private function buildPaymentParams(Order $order, PaymentChannel $channel): array
    {
        $baseParams = [
            'merchant_id'  => $order->merchant_id,
            'order_id'     => $order->merchant_order_no,
            'order_amount' => MoneyHelper::convertToYuan($order->amount),
            'notify_url'   => $order->notify_url,
            'return_url'   => $order->return_url,
            'order_title'  => $order->subject,
            'order_body'   => $order->body,
            'timestamp'    => time()
        ];

        // 统一使用标准参数格式，添加产品编码
        $baseParams['product_code'] = $channel->product_code ?? '';
        
        return $baseParams;
    }

    /**
     * 格式化支付响应
     * @param Order $order
     * @param PaymentResult $paymentResponse
     * @param array $orderRequest
     * @param array $merchant
     * @return array
     */
    private function formatPaymentResponse(Order $order, PaymentResult $paymentResponse, array $orderRequest, array $merchant): array
    {
        // 只保留必要的字段
        $response = [
            'order_no' => $order->order_no,
            'trace_id' => $order->trace_id
        ];
        
        // 根据支付结果添加payment_url
        if ($paymentResponse->isSuccess()) {
            $response['payment_url'] = $paymentResponse->getPaymentUrl();
        }

        // 返回完整的响应格式
        return [
            'code' => 200,
            'status' => true,
            'message' => '订单创建成功',
            'data' => $response
        ];
    }

    /**
     * 生成Debug模式下的模拟参数
     * @param array $data
     * @return array
     */
    private function generateDebugParams(array $data): array
    {
        $debugParams = [
            'merchant_key'        => $data['merchant_key'] ?? OrderConfig::DEBUG_MERCHANT_PREFIX . time(),
            'merchant_order_no'   => $data['merchant_order_no'] ?? OrderConfig::DEBUG_ORDER_PREFIX . date('YmdHis') . '_' . mt_rand(1000, 9999),
            'order_amount'        => $data['order_amount'] ?? '99.50',
            'notify_url'          => $data['notify_url'] ?? 'https://debug.example.com/notify',
            'sign'                => $data['sign'] ?? OrderConfig::DEBUG_SIGNATURE_PREFIX . time(),
            'return_url'          => $data['return_url'] ?? 'https://debug.example.com/return',
            'order_title'         => 'Debug测试订单',
            'order_body'          => '这是一个Debug模式下的测试订单',
            'timestamp'           => $data['timestamp'] ?? time()
        ];

        // 合并用户提供的参数
        return array_merge($debugParams, $data);
    }

    /**
     * 获取分布式锁
     * 
     * 分布式锁机制说明：
     * 1. 使用Redis SET命令的NX和EX参数实现原子性操作
     * 2. NX：只在键不存在时设置
     * 3. EX：设置过期时间，防止死锁
     * 4. 锁的键名格式：order_lock:{merchant_id}:{merchant_order_no}
     * 
     * 使用场景：
     * - 防止同一商户订单号并发创建
     * - 确保订单号唯一性检查的原子性
     * 
     * @param string $lockKey 锁的键名
     * @param int $expireTime 过期时间（秒）
     * @return bool 是否成功获取锁
     */
    private function acquireDistributedLock(string $lockKey, int $expireTime = 30): bool
    {
        try {
            $result = Redis::set($lockKey, 1, 'EX', $expireTime, 'NX');
            if ($result) {
                Log::debug('成功获取分布式锁', [
                    'lock_key' => $lockKey,
                    'expire_time' => $expireTime,
                    'merchant_id' => explode(':', $lockKey)[1] ?? null
                ]);
                return true;
            }
            
            Log::warning('获取分布式锁失败，锁已被占用', [
                'lock_key' => $lockKey,
                'expire_time' => $expireTime,
                'merchant_id' => explode(':', $lockKey)[1] ?? null
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('获取分布式锁异常', [
                'lock_key' => $lockKey,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'merchant_id' => explode(':', $lockKey)[1] ?? null
            ]);
            return false;
        }
    }

    /**
     * 释放分布式锁
     * @param string $lockKey 锁的键名
     * @return bool 是否成功释放锁
     */
    private function releaseDistributedLock(string $lockKey): bool
    {
        try {
            $result = Redis::del($lockKey);
            Log::info('释放分布式锁', [
                'lock_key' => $lockKey,
                'result' => $result
            ]);
            return $result > 0;
        } catch (\Exception $e) {
            Log::error('释放分布式锁异常', [
                'lock_key' => $lockKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 实时验证通道状态，确保通道开关后立即生效
     * @param array $channels 从缓存获取的通道列表
     * @param int $productId 产品ID
     * @return array 验证后的可用通道列表
     * @throws MyBusinessException
     */
    private function validateChannelsRealTimeStatus(array $channels, int $productId): array
    {
        if (empty($channels)) {
            return $channels;
        }

        Log::info('开始实时验证通道状态', [
            'product_id' => $productId,
            'cached_channels_count' => count($channels),
            'channel_ids' => array_column($channels, 'id')
        ]);

        $validChannels = [];
        $invalidChannels = [];

        foreach ($channels as $channel) {
            try {
                // 实时验证通道状态
                $validatedChannel = $this->statusValidator->validateCompleteChannelStatus($productId, $channel['id']);
                $validChannels[] = $validatedChannel;
                
                Log::debug('通道实时验证通过', [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['name'] ?? 'unknown'
                ]);
                
            } catch (MyBusinessException $e) {
                $invalidChannels[] = [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                Log::warning('通道实时验证失败', [
                    'product_id' => $productId,
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('通道实时验证完成', [
            'product_id' => $productId,
            'original_count' => count($channels),
            'valid_count' => count($validChannels),
            'invalid_count' => count($invalidChannels),
            'invalid_channels' => $invalidChannels
        ]);

        // 如果所有通道都无效，抛出异常
        if (empty($validChannels)) {
            throw new MyBusinessException('没有可用的支付通道，所有通道状态验证失败');
        }

        return $validChannels;
    }

}

