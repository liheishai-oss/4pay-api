<?php

namespace app\service\product;

use app\exception\MyBusinessException;
use app\model\Product;
use app\common\helpers\ProductCacheHelper;

class StoreService
{
    /**
     * 创建产品
     * @param array $data
     * @return Product
     * @throws MyBusinessException
     */
    public function createProduct(array $data): Product
    {
        // 检查产品名称是否已存在
        if (Product::where('product_name', $data['product_name'])->exists()) {
            throw new MyBusinessException('产品名称已存在');
        }

        // 自动生成对接编号
        $data['external_code'] = $this->generateUniqueExternalCode();

        // 设置默认值
        $data['status'] = $data['status'] ?? 1;
        $data['sort'] = $data['sort'] ?? 0;
        $data['default_rate_bp'] = $data['default_rate_bp'] ?? 0;
        $data['today_success_rate_bp'] = $data['today_success_rate_bp'] ?? 0;
        $data['bound_enabled_channel_count'] = 0;

        try {
            $product = Product::create($data);
            
            // 清除所有产品缓存（新创建产品时清除所有缓存以确保数据一致性）
            ProductCacheHelper::clearAllProductCache();
            
            return $product;
        } catch (\Exception $e) {
            throw new MyBusinessException('创建产品失败：' . $e->getMessage());
        }
    }

    /**
     * 生成唯一的对接编号
     * @return string
     * @throws MyBusinessException
     */
    private function generateUniqueExternalCode(): string
    {
        $maxAttempts = 10; // 最大尝试次数
        $attempt = 0;

        do {
            $externalCode = $this->generateExternalCode();
            $attempt++;

            // 检查是否已存在
            if (!Product::where('external_code', $externalCode)->exists()) {
                return $externalCode;
            }

            if ($attempt >= $maxAttempts) {
                throw new MyBusinessException('生成唯一对接编号失败，请重试');
            }
        } while (true);
    }

    /**
     * 生成对接编号
     * @return string
     */
    private function generateExternalCode(): string
    {
        // 生成4位数字
        return str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
