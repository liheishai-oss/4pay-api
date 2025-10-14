<?php

namespace app\common\helpers;

class SignatureHelper
{
    public static function generate(array $params, string $secretKey, array $signFields = [], string $algo = 'sha256'): string
    {
        if(empty($signFields)) {
            ksort($params);
            foreach ($params as $k => $v) {
                if ($k === 'sign' || $k === 'client_ip' ||  $k === 'entities_id') continue;
                $signFields[] = $k;
            }
        }
        ksort($signFields);
        $stringToSign = '';
        foreach ($signFields as $field) {
            $stringToSign .= (string)($params[$field] ?? '');
        }
        return md5(hash_hmac($algo, $stringToSign, $secretKey));
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