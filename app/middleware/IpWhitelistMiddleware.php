<?php

namespace app\middleware;

use app\model\Merchant;
use app\exception\MyBusinessException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\common\helpers\CacheHelper;
use app\common\helpers\CacheKeys;

class IpWhitelistMiddleware implements MiddlewareInterface
{
    /**
     * 处理IP白名单验证
     * 
     * @param Request $request
     * @param callable $handler
     * @return Response
     * @throws MyBusinessException
     */
    public function process(Request $request, callable $handler): Response
    {
        // 检查当前控制器和方法是否需要IP白名单验证
        if (!$this->needIpWhitelistValidation($request)) {
            return $handler($request);
        }
        
        // 获取客户端IP
        $clientIp = $this->getClientIp($request);
        
        // 从请求中获取商户标识
        $merchantKey = $request->input('merchant_key');
        
        if (!$merchantKey) {
            throw new MyBusinessException('商户标识不能为空', 400);
        }
        
        // 获取商户信息（带缓存）
        $merchant = CacheHelper::getCacheOrDb(
            CacheKeys::getMerchantInfo($merchantKey),
            fn () => Merchant::where('merchant_key', $merchantKey)->first()?->toArray()
        );
        
        if (!$merchant) {
            throw new MyBusinessException('商户不存在', 404);
        }
        
        // 检查商户状态
        if (($merchant['status'] ?? null) != Merchant::STATUS_ENABLED) {
            throw new MyBusinessException('商户已被禁用', 403);
        }
        
        // 检查IP白名单
        $this->validateIpWhitelistObject($merchant, $clientIp);

        // 将商户信息存储到请求中，供后续使用
        $request->merchant = $merchant;
        // 继续执行后续处理
        return $handler($request);
    }
    
    /**
     * 检查是否需要IP白名单验证
     * 
     * @param Request $request
     * @return bool
     */
    private function needIpWhitelistValidation(Request $request): bool
    {
        // 获取控制器类名
        $controllerClass = $request->controller;
        $action = $request->action;
        
        if (!$controllerClass || !$action) {
            return false;
        }
        
        // 使用反射获取控制器类
        try {
            $reflectionClass = new \ReflectionClass($controllerClass);
            $controller = $reflectionClass->newInstanceWithoutConstructor();
            
            // 检查控制器是否有needIpWhitelist属性
            if (!$reflectionClass->hasProperty('needIpWhitelist')) {
                return false;
            }
            
            $property = $reflectionClass->getProperty('needIpWhitelist');
            $property->setAccessible(true);
            $needIpWhitelist = $property->getValue($controller);
            
            // 检查当前方法是否在需要验证的列表中
            return is_array($needIpWhitelist) && in_array($action, $needIpWhitelist);
            
        } catch (\Exception $e) {
            // 如果反射失败，默认不进行验证
            return false;
        }
    }
    
    /**
     * 获取客户端真实IP
     * 
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        // 优先从X-Forwarded-For头获取
        $xForwardedFor = $request->header('X-Forwarded-For');
        if ($xForwardedFor) {
            $ips = explode(',', $xForwardedFor);
            $clientIp = trim($ips[0]);
            if ($this->isValidIp($clientIp)) {
                return $clientIp;
            }
        }
        
        // 从X-Real-IP头获取
        $xRealIp = $request->header('X-Real-IP');
        if ($xRealIp && $this->isValidIp($xRealIp)) {
            return $xRealIp;
        }
        
        // 从CF-Connecting-IP头获取（Cloudflare）
        $cfConnectingIp = $request->header('CF-Connecting-IP');
        if ($cfConnectingIp && $this->isValidIp($cfConnectingIp)) {
            return $cfConnectingIp;
        }
        
        // 使用框架的getRealIp方法
        return $request->getRealIp(true);
    }
    
    /**
     * 验证IP是否有效
     * 
     * @param string $ip
     * @return bool
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    
    /**
     * 验证IP白名单（数组结构）
     */
    private function validateIpWhitelistObject(array $merchant, string $clientIp): void
    {
        $whitelistIps = $merchant['whitelist_ips'] ?? '';
        // 如果商户没有设置IP白名单，则允许所有IP
        if (empty($whitelistIps)) {
            return;
        }
        // 将字符串格式的白名单转换为数组
        $list = $this->parseWhitelistIps($whitelistIps);
        foreach ($list as $allowedIp) {
            if ($this->ipMatches($clientIp, $allowedIp)) {
                return;
            }
        }
//        throw new MyBusinessException("IP地址 {$clientIp} 不在允许的访问列表中", 403);
    }

    /**
     * 解析白名单IP字符串为数组
     * 
     * @param string $whitelistIps
     * @return array
     */
    private function parseWhitelistIps(string $whitelistIps): array
    {
        if (empty($whitelistIps)) {
            return [];
        }
        
        // 按|分隔符分割，并过滤空值
        return array_filter(array_map('trim', explode('|', $whitelistIps)));
    }
    
    /**
     * 检查IP是否匹配（支持CIDR格式）
     * 
     * @param string $clientIp
     * @param string $allowedIp
     * @return bool
     */
    private function ipMatches(string $clientIp, string $allowedIp): bool
    {
        // 精确匹配
        if ($clientIp === $allowedIp) {
            return true;
        }
        
        // CIDR格式匹配
        if (strpos($allowedIp, '/') !== false) {
            return $this->ipInCidr($clientIp, $allowedIp);
        }
        
        // 通配符匹配（如 192.168.1.*）
        if (strpos($allowedIp, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($allowedIp, '/'));
            return preg_match('/^' . $pattern . '$/', $clientIp);
        }
        
        return false;
    }
    
    /**
     * 检查IP是否在CIDR范围内
     * 
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        // 将IP地址转换为长整型
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        // 计算网络掩码
        $maskLong = -1 << (32 - (int)$mask);
        
        // 检查IP是否在子网内
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
