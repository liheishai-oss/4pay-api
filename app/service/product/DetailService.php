<?php

namespace app\service\product;

use app\exception\MyBusinessException;
use app\model\Product;

class DetailService
{
    /**
     * 获取产品详情
     * @param int $id
     * @return Product
     * @throws MyBusinessException
     */
    public function getProductDetail(int $id): Product
    {
        $product = Product::find($id);

        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        return $product;
    }
}

