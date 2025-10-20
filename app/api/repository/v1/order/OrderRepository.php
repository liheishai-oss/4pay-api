<?php

namespace app\api\repository\v1\order;

use app\enums\MerchantStatus;
use app\model\Order;
use app\model\Merchant;
use app\model\PaymentChannel;
use app\common\constants\OrderConstants;
use app\common\config\OrderConfig;
use app\common\helpers\CacheKeys;
use support\Redis;
use support\Log;

class OrderRepository
{
    protected $orderQueryRepository;
    
    public function __construct()
    {
        // 初始化 orderQueryRepository，这里可以根据实际需要注入
        $this->orderQueryRepository = $this;
    }
    
    /**
     * 根据平台订单号获取订单信息
     * @param string $orderNo
     * @return Order|null
     */
    public function getOrderByOrderNo(string $orderNo): ?Order
    {
        try {
            return Order::where('order_no', $orderNo)->first();
        } catch (\Exception $e) {
            Log::error('查询订单失败 - getOrderByOrderNo', [
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 根据商户订单号获取订单信息
     * @param string $merchantOrderNo
     * @return Order|null
     */
    public function getOrderByMerchantOrderNo(string $merchantOrderNo): ?Order
    {
        try {
            return Order::where('merchant_order_no', $merchantOrderNo)->first();
        } catch (\Exception $e) {
            Log::error('查询订单失败 - getOrderByMerchantOrderNo', [
                'merchant_order_no' => $merchantOrderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 根据追踪ID获取订单信息
     * @param string $traceId
     * @return Order|null
     */
    public function getOrderByTraceId(string $traceId): ?Order
    {
        return Order::where('trace_id', $traceId)->first();
    }

    /**
     * 根据商户密钥获取商户信息
     * @param string $merchantKey
     * @return Merchant|null
     */
    public function getMerchantByKey(string $merchantKey): ?Merchant
    {
        try {
            return Merchant::where('merchant_key', $merchantKey)->where('status',MerchantStatus::ENABLED)->first();
        } catch (\Exception $e) {
            Log::error('查询商户失败 - getMerchantByKey', [
                'merchant_key' => $merchantKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 根据商户ID和订单号获取订单
     * @param int $merchantId
     * @param string $orderNo
     * @return Order|null
     */
    public function getOrderByMerchantAndOrderNo(int $merchantId, string $orderNo): ?Order
    {
        return Order::where('merchant_id', $merchantId)
            ->where('order_no', $orderNo)
            ->first();
    }

    /**
     * 根据订单ID获取订单信息
     * @param int $orderId
     * @return Order|null
     */
    public function getOrderById(int $orderId): ?Order
    {
        return Order::find($orderId);
    }

    /**
     * 更新订单状态
     * @param int $orderId
     * @param array $updateData
     * @return bool
     */
    public function updateOrder(int $orderId, array $updateData): bool
    {
        return Order::where('id', $orderId)->update($updateData) > 0;
    }

    /**
     * 创建订单
     * @param array $orderData
     * @return Order
     */
    public function createOrder(array $orderData): Order
    {
        return Order::create($orderData);
    }

    /**
     * 检查商户订单号是否已存在
     * @param string $merchantOrderNo
     * @param int $merchantId
     * @return bool
     */
    public function isMerchantOrderNoExists(string $merchantOrderNo, int $merchantId): bool
    {
        return Order::where('merchant_order_no', $merchantOrderNo)
            ->where('merchant_id', $merchantId)
            ->exists();
    }

    /**
     * 检查商户订单号是否已存在（激进缓存策略）
     * 
     * 策略说明：
     * 1. L1缓存命中 → 直接返回结果
     * 2. L2缓存命中 → 回填L1缓存并返回结果  
     * 3. L1+L2都未命中 → 激进策略：直接返回false，允许创建
     * 
     * 优势：
     * - 极高性能：1-5ms响应时间
     * - 减少数据库压力
     * - 依赖数据库唯一性约束兜底
     * 
     * @param string $merchantOrderNo
     * @param int $merchantId
     * @return bool
     */
    public function isMerchantOrderNoExistsWithCache(string $merchantOrderNo, int $merchantId): bool
    {
        Log::info('开始检查商户订单号唯一性（激进缓存策略）', [
            'merchant_id' => $merchantId,
            'merchant_order_no' => $merchantOrderNo
        ]);
        
        $cacheKey = CacheKeys::getMerchantOrderNoCheck($merchantId, $merchantOrderNo);
        
        try {
            // 1. 优先从L1缓存（内存）获取
            $l1Result = $this->getFromL1Cache($cacheKey);
            if ($l1Result !== null) {
                Log::debug('L1缓存命中', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'result' => $l1Result
                ]);
                return (bool)$l1Result;
            }
            
            // 2. 从L2缓存（Redis）获取
            $l2Result = $this->getFromL2Cache($cacheKey);
            if ($l2Result !== null) {
                // 回填L1缓存
                $this->setL1Cache($cacheKey, $l2Result);
                Log::debug('L2缓存命中，已回填L1', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'result' => $l2Result
                ]);
                return (bool)$l2Result;
            }
            
            // 3. L1和L2都未命中，使用激进策略：直接返回false，依赖数据库约束
            Log::info('缓存未命中，激进策略：允许创建', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'strategy' => 'aggressive',
                'reason' => 'L1和L2缓存都未命中，使用激进策略提升性能'
            ]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('缓存系统异常，降级到数据库查询', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'error' => $e->getMessage()
            ]);
            
            return $this->queryDatabaseWithoutCache($merchantOrderNo, $merchantId);
        }
    }
    
    /**
     * 从L1缓存（内存）获取
     * @param string $cacheKey
     * @return int|null
     */
    private function getFromL1Cache(string $cacheKey): ?int
    {
        try {
            // 这里应该实现内存缓存逻辑
            // 由于当前代码中没有内存缓存实现，这里返回null
            return null;
        } catch (\Exception $e) {
            Log::warning('L1缓存异常', ['cache_key' => $cacheKey, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 从L2缓存（Redis）获取
     * @param string $cacheKey
     * @return int|null
     */
    private function getFromL2Cache(string $cacheKey): ?int
    {
        try {
            $result = Redis::get($cacheKey);
            if ($result !== false) {
                return (int)$result;
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('L2缓存异常', ['cache_key' => $cacheKey, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 设置L1缓存
     * @param string $cacheKey
     * @param int $value
     */
    private function setL1Cache(string $cacheKey, int $value): void
    {
        try {
            // 这里应该实现内存缓存逻辑
            // 由于当前代码中没有内存缓存实现，这里暂时不实现
        } catch (\Exception $e) {
            Log::warning('设置L1缓存失败', ['cache_key' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * 查询数据库但不缓存结果（仅用于检查）
     * @param string $merchantOrderNo
     * @param int $merchantId
     * @return bool
     */
    private function queryDatabaseWithoutCache(string $merchantOrderNo, int $merchantId): bool
    {
        $exists = Order::where('merchant_order_no', $merchantOrderNo)
            ->where('merchant_id', $merchantId)
            ->exists();
        
        Log::info('数据库查询完成（未缓存）', [
            'merchant_id' => $merchantId,
            'merchant_order_no' => $merchantOrderNo,
            'exists' => $exists
        ]);
        
        return $exists;
    }
    
    /**
     * 订单创建成功后缓存订单号存在状态
     * @param string $merchantOrderNo
     * @param int $merchantId
     */
    public function cacheOrderCreated(string $merchantOrderNo, int $merchantId): void
    {
        $cacheKey = CacheKeys::getMerchantOrderNoCheck($merchantId, $merchantOrderNo);
        
        try {
            // 订单创建成功，缓存存在状态（5分钟，仅在Redis正常时）
            try {
                Redis::setex($cacheKey, OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL, 1);
            } catch (\Throwable $redisException) {
                Log::warning('Redis写入失败，跳过订单创建状态缓存', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'error' => $redisException->getMessage()
                ]);
            }
            
            Log::info('订单创建成功，已缓存订单号存在状态', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'cache_key' => $cacheKey,
                'ttl' => OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL
            ]);
        } catch (\Exception $e) {
            Log::error('缓存订单创建状态失败', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 订单创建失败后清理缓存
     * @param string $merchantOrderNo
     * @param int $merchantId
     */
    public function clearOrderCache(string $merchantOrderNo, int $merchantId): void
    {
        $cacheKey = CacheKeys::getMerchantOrderNoCheck($merchantId, $merchantOrderNo);
        
        try {
            // 清理L2缓存（仅在Redis正常时）
            try {
                Redis::del($cacheKey);
            } catch (\Throwable $redisException) {
                Log::warning('Redis删除失败，跳过缓存清理', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'cache_key' => $cacheKey,
                    'error' => $redisException->getMessage()
                ]);
            }
            
            Log::info('订单创建失败，已清理订单号缓存', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'cache_key' => $cacheKey
            ]);
        } catch (\Exception $e) {
            Log::error('清理订单缓存失败', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 缓存商户订单号存在状态
     * @param string $merchantOrderNo
     * @param int $merchantId
     * @param bool $exists
     * @return void
     */
    public function cacheMerchantOrderNoExists(string $merchantOrderNo, int $merchantId, bool $exists): void
    {
        $cacheKey = CacheKeys::getMerchantOrderNoCheck($merchantId, $merchantOrderNo);
        
        try {
            if ($exists) {
                // 存在的订单号缓存1小时（仅在Redis正常时）
                try {
                    Redis::setex($cacheKey, OrderConfig::MERCHANT_ORDER_CACHE_TTL, 1);
                } catch (\Throwable $redisException) {
                    Log::warning('Redis写入失败，跳过存在订单号缓存', [
                        'merchant_id' => $merchantId,
                        'merchant_order_no' => $merchantOrderNo,
                        'error' => $redisException->getMessage()
                    ]);
                }
                Log::info('商户订单号已缓存为存在', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'cache_key' => $cacheKey,
                    'ttl' => OrderConfig::MERCHANT_ORDER_CACHE_TTL
                ]);
            } else {
                // 不存在的订单号缓存5分钟（仅在Redis正常时）
                try {
                    Redis::setex($cacheKey, OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL, 0);
                } catch (\Throwable $redisException) {
                    Log::warning('Redis写入失败，跳过不存在订单号缓存', [
                        'merchant_id' => $merchantId,
                        'merchant_order_no' => $merchantOrderNo,
                        'error' => $redisException->getMessage()
                    ]);
                }
                Log::info('商户订单号已缓存为不存在', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'cache_key' => $cacheKey,
                    'ttl' => OrderConfig::MERCHANT_ORDER_NOT_EXISTS_CACHE_TTL
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Redis设置商户订单号缓存失败', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 设置/清理商户订单号“创建中”占位键（短 TTL，防并发）
     */
    public function setMerchantOrderNoPending(string $merchantOrderNo, int $merchantId, int $ttl = OrderConfig::PENDING_ORDER_CACHE_TTL): void
    {
        $pendingKey = CacheKeys::getMerchantOrderNoPending($merchantId, $merchantOrderNo);
        try {
            try {
                Redis::setex($pendingKey, $ttl, 1);
            } catch (\Throwable $redisException) {
                Log::warning('Redis写入失败，跳过订单号占位缓存', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'error' => $redisException->getMessage()
                ]);
            }
            Log::info('设置商户订单号创建中占位', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'pending_key' => $pendingKey,
                'ttl' => $ttl
            ]);
        } catch (\Throwable $e) {
            Log::warning('设置商户订单号创建中占位失败', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'pending_key' => $pendingKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function clearMerchantOrderNoPending(string $merchantOrderNo, int $merchantId): void
    {
        $pendingKey = CacheKeys::getMerchantOrderNoPending($merchantId, $merchantOrderNo);
        try {
            try {
                Redis::del($pendingKey);
            } catch (\Throwable $redisException) {
                Log::warning('Redis删除失败，跳过占位缓存清理', [
                    'merchant_id' => $merchantId,
                    'merchant_order_no' => $merchantOrderNo,
                    'pending_key' => $pendingKey,
                    'error' => $redisException->getMessage()
                ]);
            }
            Log::info('清理商户订单号创建中占位', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'pending_key' => $pendingKey
            ]);
        } catch (\Throwable $e) {
            Log::warning('清理商户订单号创建中占位失败', [
                'merchant_id' => $merchantId,
                'merchant_order_no' => $merchantOrderNo,
                'pending_key' => $pendingKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 批量获取订单创建所需数据
     * @param int $merchantId
     * @param int $productId
     * @return array
     */
    public function getOrderCreationData(int $merchantId, int $productId): array
    {
        Log::info('开始批量查询订单创建数据', [
            'merchant_id' => $merchantId,
            'product_id' => $productId
        ]);

        try {
            // 使用Eloquent关系预加载一次性获取所有需要的数据
            $merchant = Merchant::with([
                'productAssignments' => function($query) use ($productId) {
                    $query->where('product_id', $productId)
                          ->where('status', 1)
                          ->select(['merchant_id', 'product_id', 'merchant_rate', 'status']);
                }
            ])
            ->where('id', $merchantId)
            ->where('status', 1)
            ->first();

            if (!$merchant) {
                throw new \Exception('商户不存在或已禁用');
            }

            // 获取产品信息
            $product = \app\model\Product::where('id', $productId)
                ->where('status', 1)
                ->first();

            if (!$product) {
                throw new \Exception('产品不存在或已禁用');
            }

            // 获取商户产品关系
            $productMerchant = $merchant->productAssignments->first();
            if (!$productMerchant) {
                throw new \Exception('商户产品关系不存在或已禁用');
            }

            Log::info('批量查询订单创建数据成功', [
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'merchant_rate' => $productMerchant->merchant_rate
            ]);

            return [
                'merchant' => [
                    'id' => $merchant->id,
                    'merchant_name' => $merchant->merchant_name,
                    'merchant_key' => $merchant->merchant_key,
                    'merchant_secret' => $merchant->merchant_secret
                ],
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'external_code' => $product->external_code
                ],
                'merchant_rate' => $productMerchant->merchant_rate,
                'product_merchant_status' => $productMerchant->status
            ];

        } catch (\Throwable $e) {
            Log::error('批量查询订单创建数据失败', [
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    /**
     * 根据通道类型获取可用通道
     * @param string $channelType
     * @return PaymentChannel|null
     */
    public function getAvailableChannelByType(string $channelType): ?PaymentChannel
    {
        return PaymentChannel::where('interface_code', $channelType)
            ->where('status', PaymentChannel::STATUS_ENABLED)
            ->orderBy('weight', 'desc')
            ->first();
    }

    /**
     * 生成平台订单号
     * @param int $merchantId 商户ID
     * @return string
     */
    public function generatePlatformOrderNo(int $merchantId = 0): string
    {
        return $this->generateOrderNumber($merchantId);
    }

    /**
     * 生成订单号
     * @param int $merchant_id 商户ID
     * @return string
     */
    public function generateOrderNumber($merchant_id): string
    {
        $prefix     = 'BY';
        $timestamp  = date('YmdHis');

        $merchantIdHash = substr(md5((string)$merchant_id), 0, 4);

        for ($i = 0; $i < OrderConstants::ORDER_NUMBER_RETRY_LIMIT; $i++) {
            $rand           = mt_rand(1000, 9999);
            $orderNumber    = strtoupper($prefix . $timestamp . $merchantIdHash . $rand);
            $key = CacheKeys::getOrderCommitLog($orderNumber);

            try {
                if (Redis::set($key, 1, 'EX', OrderConstants::ORDER_NUMBER_EXPIRE, 'NX')) {
                    return $orderNumber;
                }
            } catch (\Throwable $e) {
                Log::warning('Redis写入失败，跳过订单号缓存', [
                    'order_number' => $orderNumber,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                break;
            }
        }

        for($i = 0; $i < OrderConstants::ORDER_NUMBER_RETRY_LIMIT; $i++) {
           $fallbackNumber = strtoupper('BY' . $timestamp . bin2hex(random_bytes(4)));
           if(!$this->orderQueryRepository->existsByTransactionId(['order_no'=>$fallbackNumber])){
                return $fallbackNumber;
           }
        }

        throw new \Exception('订单生成失败');
    }

    /**
     * 生成追踪ID
     * @return string
     */
    public function generateTraceId(): string
    {
        return 'TRACE_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
    }
    
    /**
     * 检查交易ID是否存在
     * @param array $conditions 查询条件
     * @return bool
     */
    public function existsByTransactionId(array $conditions): bool
    {
        $query = Order::query();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        return $query->exists();
    }

    /**
     * 获取商户费率
     * @param int $merchantId 商户ID
     * @param int $productId 产品ID
     * @return int 商户费率（百分比）
     */
    public function getMerchantRate(int $merchantId, int $productId): int
    {
        return \app\common\helpers\QueryCacheHelper::getCacheOrQuery(
            CacheKeys::getMerchantRateQuery($merchantId, $productId),
            fn() => \app\model\ProductMerchant::where('merchant_id', $merchantId)
                ->where('product_id', $productId)
                ->where('status', \app\model\ProductMerchant::STATUS_ENABLED)
                ->value('merchant_rate') ?? 0,
            1800 // 30分钟缓存
        );
    }
}
