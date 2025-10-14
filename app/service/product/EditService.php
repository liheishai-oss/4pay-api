<?php

namespace app\service\product;

use app\exception\MyBusinessException;
use app\model\Product;
use app\common\helpers\ProductCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use app\common\helpers\MultiLevelCacheHelper;
use app\common\helpers\CacheKeys;

class EditService
{
    /**
     * 更新产品
     * @param int $id
     * @param array $data
     * @return Product
     * @throws MyBusinessException
     */
    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::find($id);

        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        // 检查产品名称是否已存在（排除自己）
        if (isset($data['product_name']) && Product::where('product_name', $data['product_name'])->where('id', '!=', $id)->exists()) {
            throw new MyBusinessException('产品名称已存在');
        }

        // 对接编号不允许编辑，由系统自动生成

        try {
            // 只更新有值的字段
            foreach ($data as $key => $value) {
                if (in_array($key, $product->getFillable()) && $value !== null) {
                    $product->$key = $value;
                }
            }

            $product->save();

            // 清除产品相关缓存（包括产品代码缓存）
            ProductCacheHelper::clearProductAllCache($product->external_code);
            
            // 清除可用通道列表缓存（产品变化可能影响通道选择）
            ChannelCacheHelper::clearAvailableChannelsCache($product->id);

            // 清理订单处理专用缓存（产品信息 - 状态变化敏感）
            $productCacheKey = CacheKeys::getProductInfo($product->id);
            MultiLevelCacheHelper::clearOrderProduct($productCacheKey);
            
            // 清理相关通道缓存（通道信息 - 通道状态敏感）
            MultiLevelCacheHelper::clearOrderChannelByPattern('*available_channels:' . $product->id . '*');

            return $product;
        } catch (\Exception $e) {
            throw new MyBusinessException('更新产品失败：' . $e->getMessage());
        }
    }
}

