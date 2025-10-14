<?php

namespace app\service\thirdparty_payment\enums;

/**
 * 支付服务类型枚举
 */
class PaymentServiceType
{
    // 支付宝相关
    public const ALIPAY_WEB = 'alipay_web';           // 支付宝网页支付
    public const ALIPAY_APP = 'alipay_app';           // 支付宝APP支付
    public const ALIPAY_WAP = 'alipay_wap';           // 支付宝手机网站支付
    public const ALIPAY_QR = 'alipay_qr';             // 支付宝扫码支付

    // 微信支付相关
    public const WECHAT_JSAPI = 'wechat_jsapi';       // 微信JSAPI支付
    public const WECHAT_APP = 'wechat_app';           // 微信APP支付
    public const WECHAT_H5 = 'wechat_h5';             // 微信H5支付
    public const WECHAT_NATIVE = 'wechat_native';     // 微信扫码支付
    public const WECHAT_MICROPAY = 'wechat_micropay'; // 微信刷卡支付

    // 银联支付相关
    public const UNIONPAY_WEB = 'unionpay_web';       // 银联网页支付
    public const UNIONPAY_APP = 'unionpay_app';       // 银联APP支付
    public const UNIONPAY_WAP = 'unionpay_wap';       // 银联手机支付

    // 其他支付方式
    public const PAYPAL = 'paypal';                   // PayPal支付
    public const STRIPE = 'stripe';                   // Stripe支付
    public const SQUARE = 'square';                   // Square支付

    /**
     * 获取所有支持的服务类型
     * @return array
     */
    public static function getAllTypes(): array
    {
        return [
            self::ALIPAY_WEB,
            self::ALIPAY_APP,
            self::ALIPAY_WAP,
            self::ALIPAY_QR,
            self::WECHAT_JSAPI,
            self::WECHAT_APP,
            self::WECHAT_H5,
            self::WECHAT_NATIVE,
            self::WECHAT_MICROPAY,
            self::UNIONPAY_WEB,
            self::UNIONPAY_APP,
            self::UNIONPAY_WAP,
            self::PAYPAL,
            self::STRIPE,
            self::SQUARE,
        ];
    }

    /**
     * 获取支付宝相关类型
     * @return array
     */
    public static function getAlipayTypes(): array
    {
        return [
            self::ALIPAY_WEB,
            self::ALIPAY_APP,
            self::ALIPAY_WAP,
            self::ALIPAY_QR,
        ];
    }

    /**
     * 获取微信支付相关类型
     * @return array
     */
    public static function getWechatTypes(): array
    {
        return [
            self::WECHAT_JSAPI,
            self::WECHAT_APP,
            self::WECHAT_H5,
            self::WECHAT_NATIVE,
            self::WECHAT_MICROPAY,
        ];
    }

    /**
     * 获取银联支付相关类型
     * @return array
     */
    public static function getUnionpayTypes(): array
    {
        return [
            self::UNIONPAY_WEB,
            self::UNIONPAY_APP,
            self::UNIONPAY_WAP,
        ];
    }

    /**
     * 检查服务类型是否有效
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::getAllTypes());
    }

    /**
     * 获取服务类型描述
     * @param string $type
     * @return string
     */
    public static function getDescription(string $type): string
    {
        $descriptions = [
            self::ALIPAY_WEB => '支付宝网页支付',
            self::ALIPAY_APP => '支付宝APP支付',
            self::ALIPAY_WAP => '支付宝手机网站支付',
            self::ALIPAY_QR => '支付宝扫码支付',
            self::WECHAT_JSAPI => '微信JSAPI支付',
            self::WECHAT_APP => '微信APP支付',
            self::WECHAT_H5 => '微信H5支付',
            self::WECHAT_NATIVE => '微信扫码支付',
            self::WECHAT_MICROPAY => '微信刷卡支付',
            self::UNIONPAY_WEB => '银联网页支付',
            self::UNIONPAY_APP => '银联APP支付',
            self::UNIONPAY_WAP => '银联手机支付',
            self::PAYPAL => 'PayPal支付',
            self::STRIPE => 'Stripe支付',
            self::SQUARE => 'Square支付',
        ];

        return $descriptions[$type] ?? '未知支付类型';
    }
}


