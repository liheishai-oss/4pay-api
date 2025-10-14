<?php

namespace app\service\product;

use app\exception\MyBusinessException;
use app\model\Product;
use app\common\helpers\ProductCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class DestroyService
{
    /**
     * 删除产品（单个或批量）
     * @param array $ids
     * @return bool
     * @throws MyBusinessException
     */
    public function deleteProducts(array $ids): bool
    {
        if (empty($ids)) {
            throw new MyBusinessException('请选择要删除的数据');
        }

        $products = Product::whereIn('id', $ids)->get();

        if ($products->isEmpty()) {
            throw new MyBusinessException('产品不存在');
        }

        try {
            Db::beginTransaction();

            // 删除产品
            Product::whereIn('id', $ids)->delete();

            // 清除被删除产品的缓存（包括产品代码缓存）
            foreach ($products as $product) {
                ProductCacheHelper::clearProductAllCache($product->external_code);
                
                // 清除可用通道列表缓存
                ChannelCacheHelper::clearAvailableChannelsCache($product->id);
            }

            Db::commit();

            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            $msg = $e->getMessage();
            // 外键约束：被订单引用，无法删除
            if (str_contains($msg, 'SQLSTATE[23000]') && (str_contains($msg, '1451') || str_contains($msg, 'foreign key constraint'))) {
                throw new MyBusinessException('删除失败：该产品已被订单引用，无法删除，请先处理关联订单或改为禁用');
            }
            throw new MyBusinessException('删除产品失败：' . $msg);
        }
    }

    /**
     * 批量删除产品
     * @param array $ids
     * @return int
     * @throws MyBusinessException
     */
    public function batchDeleteProducts(array $ids): int
    {
        if (empty($ids)) {
            throw new MyBusinessException('请选择要删除的数据');
        }

        $products = Product::whereIn('id', $ids)->get();

        if ($products->isEmpty()) {
            throw new MyBusinessException('产品不存在');
        }

        try {
            Db::beginTransaction();

            // 删除产品
            $deletedCount = Product::whereIn('id', $ids)->delete();

            // 清除被删除产品的缓存（包括产品代码缓存）
            foreach ($products as $product) {
                ProductCacheHelper::clearProductAllCache($product->external_code);
                
                // 清除可用通道列表缓存
                ChannelCacheHelper::clearAvailableChannelsCache($product->id);
            }

            Db::commit();

            return $deletedCount;
        } catch (\Exception $e) {
            Db::rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'SQLSTATE[23000]') && (str_contains($msg, '1451') || str_contains($msg, 'foreign key constraint'))) {
                throw new MyBusinessException('批量删除失败：存在被订单引用的产品，无法删除。请先处理关联订单或改为禁用');
            }
            throw new MyBusinessException('批量删除产品失败：' . $msg);
        }
    }

    /**
     * 删除单个产品（保持向后兼容）
     * @param int $id
     * @return bool
     * @throws MyBusinessException
     */
    public function deleteProduct(int $id): bool
    {
        return $this->deleteProducts([$id]);
    }
}

