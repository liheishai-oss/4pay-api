<?php

namespace app\service\product;

use app\exception\MyBusinessException;
use app\model\Product;
use app\common\helpers\ProductCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use app\common\helpers\QueryCacheHelper;
use app\common\helpers\CacheKeys;
use app\common\helpers\MultiLevelCacheHelper;
use support\Db;
use support\Log;
use support\Redis;

class StatusSwitchService
{
    /**
     * 切换产品状态
     * @param int $id
     * @param int $status
     * @return Product
     * @throws MyBusinessException
     */
    public function toggleStatus(int $id, int $status): Product
    {
        \support\Log::info('StatusSwitchService::toggleStatus 开始', ['product_id' => $id, 'status' => $status]);
        echo "StatusSwitchService::toggleStatus 开始 - Product ID: $id, Status: $status\n";
        $product = Product::find($id);
        
        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        // 验证状态值
        if (!in_array($status, [Product::STATUS_DISABLED, Product::STATUS_ENABLED])) {
            throw new MyBusinessException('无效的状态值');
        }

        try {
            Db::beginTransaction();

            $oldStatus = $product->status;
            $product->status = $status;
            $product->save();

            Db::commit();

            // 根据状态变化处理缓存（在事务外处理，避免影响数据库操作）
            try {
                Log::info('产品状态切换缓存处理开始', [
                    'product_id' => $product->id,
                    'old_status' => $oldStatus,
                    'new_status' => $status
                ]);
                
                if ($status == Product::STATUS_ENABLED) {
                    // 产品开启：预热缓存
                    Log::info('开始预热产品缓存', ['product_id' => $product->id]);
                    $this->warmupProductCache($product);
                } else {
                    // 产品关闭：清理缓存
                    echo "清理缓存";
                    \support\Log::info('开始清理产品缓存', ['product_id' => $product->id]);
                    $this->clearProductCache($product);
                }
                
                \support\Log::info('产品状态切换缓存处理完成', ['product_id' => $product->id]);
            } catch (\Exception $cacheException) {
                error_log('缓存处理异常: ' . $cacheException->getMessage() . ' - Trace: ' . $cacheException->getTraceAsString());
                \support\Log::error('缓存处理异常', [
                    'product_id' => $product->id,
                    'error' => $cacheException->getMessage()
                ]);
            }

            return $product;
        } catch (\Exception $e) {
            \support\Log::error('StatusSwitchService 异常', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Db::rollBack();
            throw new MyBusinessException('状态切换失败：' . $e->getMessage());
        }
    }
    
    /**
     * 预热产品缓存（产品开启时）
     * @param Product $product
     */
    private function warmupProductCache(Product $product): void
    {
        try {
            // 预热产品信息缓存
            $productCacheKey = CacheKeys::getProductInfo($product->id);
            $productData = $product->toArray();
            foreach ($productData as $field => $value) {
                Redis::hSet($productCacheKey, $field, $value);
            }
            Redis::expire($productCacheKey, 3600);
            
            // 预热产品代码查询缓存
            $codeCacheKey = CacheKeys::getProductCodeQuery($product->external_code);
            QueryCacheHelper::getCacheOrQuery($codeCacheKey, fn() => $product->toArray(), 3600);
            
            \support\Log::info('产品缓存预热完成', [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'external_code' => $product->external_code
            ]);
        } catch (\Exception $e) {
            \support\Log::error('产品缓存预热失败', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 清理产品缓存（产品关闭时）
     * @param Product $product
     */
    private function clearProductCache(Product $product): void
    {
        try {
            print_r($product);
            ProductCacheHelper::clearProductAllCache($product->external_code);

            // 清除可用通道列表缓存（产品状态变化可能影响通道选择）
            ChannelCacheHelper::clearAvailableChannelsCache($product->id);
            
            // 清理订单处理专用缓存（产品信息 - 状态变化敏感）
            $productCacheKey = CacheKeys::getProductInfo($product->id);
            MultiLevelCacheHelper::clearOrderProduct($productCacheKey);
            
            // 清理相关通道缓存（通道信息 - 通道状态敏感）
            MultiLevelCacheHelper::clearOrderChannelByPattern('*available_channels:' . $product->id . '*');
            
            \support\Log::info('产品缓存清理完成（包含3级缓存）', [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'external_code' => $product->external_code
            ]);
        } catch (\Exception $e) {
            \support\Log::error('产品缓存清理失败', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

