<?php

namespace app\common\helpers;

use app\common\constants\SystemConstants;

/**
 * 缓存键管理类
 */
class CacheKeys
{
    /**
     * 获取订单提交日志缓存键
     * @param string $orderNumber 订单号
     * @return string
     */
    public static function getOrderCommitLog(string $orderNumber): string
    {
        return SystemConstants::CACHE_PREFIX . "order:commit:log:{$orderNumber}";
    }
    
    /**
     * 获取订单信息缓存键
     * @param string $orderNumber 订单号
     * @return string
     */
    public static function getOrderInfo(string $orderNumber): string
    {
        return SystemConstants::CACHE_PREFIX . "order:info:{$orderNumber}";
    }
    
    /**
     * 获取商户信息缓存键
     * @param string $merchantKey 商户密钥
     * @return string
     */
    public static function getMerchantInfo(string $merchantKey): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:{$merchantKey}";
    }
    
    /**
     * 获取产品信息缓存键
     * @param int $productId 产品ID
     * @return string
     */
    public static function getProductInfo(int $productId): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:product.{$productId}";
    }
    
    /**
     * 获取产品代码缓存键
     * @param string $productCode 产品代码
     * @return string
     */
    public static function getProductCodeInfo(string $productCode): string
    {
        return SystemConstants::CACHE_PREFIX . "product:code:{$productCode}";
    }
    
    /**
     * 获取通道信息缓存键
     * @param int $channelId 通道ID
     * @return string
     */
    public static function getChannelInfo(int $channelId): string
    {
        return SystemConstants::CACHE_PREFIX . "channel:info:{$channelId}";
    }
    
    /**
     * 获取供应商信息缓存键
     * @param int $supplierId 供应商ID
     * @return string
     */
    public static function getSupplierInfo(int $supplierId): string
    {
        return SystemConstants::CACHE_PREFIX . "supplier:info:{$supplierId}";
    }
    
    /**
     * 获取产品通道列表缓存键
     * @param int $productId 产品ID
     * @return string
     */
    public static function getProductChannels(int $productId): string
    {
        return SystemConstants::CACHE_PREFIX . "product:channels:{$productId}";
    }
    
    /**
     * 获取商户订单号检查缓存键
     * @param int $merchantId 商户ID
     * @param string $orderNo 订单号
     * @return string
     */
    public static function getMerchantOrderNoCheck(int $merchantId, string $orderNo): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant_order_check:{$merchantId}:" . $orderNo;
    }

    /**
     * 获取商户订单号“创建中”占位键（防并发占位用，短期有效）
     * @param int $merchantId 商户ID
     * @param string $orderNo 订单号
     * @return string
     */
    public static function getMerchantOrderNoPending(int $merchantId, string $orderNo): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant_order_pending:{$merchantId}:" . $orderNo;
    }
    
    /**
     * 获取订单创建数据缓存键
     * @param string $merchantKey 商户密钥
     * @param int $productId 产品ID
     * @return string
     */
    public static function getOrderCreationData(string $merchantKey, int $productId): string
    {
        return SystemConstants::CACHE_PREFIX . "order_creation_data:{$merchantKey}:{$productId}";
    }
    
    /**
     * 获取可用通道缓存键
     * @param int $productId 产品ID
     * @param int $orderAmountCents 订单金额（分）
     * @return string
     */
    public static function getAvailableChannels(int $productId, int $orderAmountCents): string
    {
        return SystemConstants::CACHE_PREFIX . "available_channels:{$productId}:{$orderAmountCents}";
    }
    
    /**
     * 获取产品代码查询缓存键
     * @param string $productCode 产品代码
     * @return string
     */
    public static function getProductCodeQuery(string $productCode): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:product_code:{$productCode}";
    }
    
    
    /**
     * 获取商户费率查询缓存键
     * @param int $merchantId 商户ID
     * @param int $productId 产品ID
     * @return string
     */
    public static function getMerchantRateQuery(int $merchantId, int $productId): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:merchant_rate:{$merchantId}:{$productId}";
    }
    
    /**
     * 获取通道列表查询缓存键
     * @param string $productCode 产品代码
     * @return string
     */
    public static function getChannelListQuery(string $productCode): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:channel_list:{$productCode}";
    }
    
    /**
     * 获取供应商信息查询缓存键
     * @param int $supplierId 供应商ID
     * @return string
     */
    public static function getSupplierQuery(int $supplierId): string
    {
        return SystemConstants::CACHE_PREFIX . "merchant:supplier:{$supplierId}";
    }
}
