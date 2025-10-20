<?php

namespace app\middleware;

use support\Request;
use support\Response;
use app\model\Merchant;
use support\Log;

class SignatureMiddleware
{
    /**
     * 签名验证中间件
     */
    public function process(Request $request, callable $next): Response
    {
        // 获取安全配置
        $securityConfig = config('security', []);
        $signatureConfig = $securityConfig['signature'] ?? [];
        
        // 检查是否启用签名验证
        if (!($signatureConfig['enabled'] ?? false)) {
            return $next($request);
        }
        
        // 获取请求参数
        $params = $request->all();
        
        // 检查必要参数
        if (empty($params['merchant_key']) || empty($params['sign'])) {
            Log::warning('签名验证失败：缺少必要参数', [
                'merchant_key' => $params['merchant_key'] ?? null,
                'sign' => isset($params['sign']) ? '***' : null,
                'ip' => $request->getRealIp(),
                'url' => $request->url()
            ]);
            
            return json([
                'code' => 400,
                'msg' => '签名验证失败：缺少必要参数',
                'data' => null
            ]);
        }
        
        // 获取商户信息
        $merchant = Merchant::where('merchant_key', $params['merchant_key'])->first();
        if (!$merchant) {
            Log::warning('签名验证失败：商户不存在', [
                'merchant_key' => $params['merchant_key'],
                'ip' => $request->getRealIp(),
                'url' => $request->url()
            ]);
            
            return json([
                'code' => 400,
                'msg' => '签名验证失败：商户不存在',
                'data' => null
            ]);
        }
        
        // 验证签名
        $sign = $params['sign'];
        unset($params['sign']); // 移除签名参数
        
        // 按key排序参数
        ksort($params);
        
        // 构建签名字符串
        $signString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= $key . '=' . $value . '&';
            }
        }
        $signString = rtrim($signString, '&') . $merchant->merchant_secret;
        
        // 计算签名
        $algorithm = $signatureConfig['algorithm'] ?? 'md5';
        $calculatedSign = '';
        
        switch (strtolower($algorithm)) {
            case 'md5':
                $calculatedSign = md5($signString);
                break;
            case 'sha1':
                $calculatedSign = sha1($signString);
                break;
            case 'sha256':
                $calculatedSign = hash('sha256', $signString);
                break;
            default:
                $calculatedSign = md5($signString);
        }
        
        // 验证签名
        if (!hash_equals($calculatedSign, $sign)) {
            Log::warning('签名验证失败：签名不匹配', [
                'merchant_key' => $params['merchant_key'],
                'merchant_id' => $merchant->id,
                'calculated_sign' => $calculatedSign,
                'received_sign' => $sign,
                'sign_string' => $signString,
                'ip' => $request->getRealIp(),
                'url' => $request->url()
            ]);
            
            return json([
                'code' => 400,
                'msg' => '签名验证失败：签名不匹配',
                'data' => null
            ]);
        }
        
        // 验证时间戳（如果存在）
        if (isset($params['timestamp'])) {
            $timestamp = intval($params['timestamp']);
            $timeout = $signatureConfig['timeout'] ?? 300;
            $currentTime = time();
            
            if (abs($currentTime - $timestamp) > $timeout) {
                Log::warning('签名验证失败：时间戳过期', [
                    'merchant_key' => $params['merchant_key'],
                    'merchant_id' => $merchant->id,
                    'timestamp' => $timestamp,
                    'current_time' => $currentTime,
                    'timeout' => $timeout,
                    'ip' => $request->getRealIp(),
                    'url' => $request->url()
                ]);
                
                return json([
                    'code' => 400,
                    'msg' => '签名验证失败：请求已过期',
                    'data' => null
                ]);
            }
        }
        
        // 将商户信息添加到请求中
        $request->merchant = $merchant;
        
        Log::info('签名验证成功', [
            'merchant_key' => $params['merchant_key'],
            'merchant_id' => $merchant->id,
            'ip' => $request->getRealIp(),
            'url' => $request->url()
        ]);
        
        return $next($request);
    }
}
