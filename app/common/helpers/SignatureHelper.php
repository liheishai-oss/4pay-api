<?php

namespace app\common\helpers;

class SignatureHelper
{
    public static function generate(array $params, string $secretKey, array $signFields = [], string $algo = 'sha256'): string
    {
        // 移除不需要签名的字段
        unset($params['sign']);
        unset($params['client_ip']);
        unset($params['entities_id']);
        unset($params['debug']);
        
        // 如果指定了签名字段，只使用指定的字段
        if (!empty($signFields)) {
            $filteredParams = [];
            foreach ($signFields as $field) {
                if (isset($params[$field])) {
                    $filteredParams[$field] = $params[$field];
                }
            }
            $params = $filteredParams;
        }
        
        // 按键名排序
        ksort($params);
        
        // 构建签名字符串
        $stringToSign = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $stringToSign .= (string)$value;
            }
        }
        
        $signature = md5($stringToSign . $secretKey);
        
        return $signature;
    }

    public static function verify(array $params, string $secretKey, array $signFields = [], string $algo = 'sha256'): bool
    {
        if (!isset($params['sign'])) {
            return false;
        }

        $expectedSign = self::generate($params, $secretKey, $signFields, $algo);

        return hash_equals($expectedSign, $params['sign']); // 防止时间攻击
    }
}