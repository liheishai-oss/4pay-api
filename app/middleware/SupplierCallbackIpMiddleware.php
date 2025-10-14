<?php

namespace app\middleware;

use app\model\Supplier;
use app\exception\MyBusinessException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 供应商回调IP白名单中间件
 * 用于验证供应商回调请求的IP地址
 */
class SupplierCallbackIpMiddleware implements MiddlewareInterface
{
    /**
     * 处理供应商回调IP验证
     * 
     * @param Request $request
     * @param callable $handler
     * @return Response
     * @throws MyBusinessException
     */
    public function process(Request $request, callable $handler): Response
    {
        // 获取客户端IP
        $clientIp = $this->getClientIp($request);
        
        // 从路由参数中获取支付服务商名称
        $paymentName = $request->route('payment_name');
        
        if (!$paymentName) {
            throw new MyBusinessException('支付服务商名称不能为空', 400);
        }
        
        // 根据支付服务商名称获取对应的供应商
        $supplier = $this->getSupplierByPaymentName($paymentName);
        
        // 记录调试信息
        \support\Log::info('SupplierCallbackIpMiddleware 调试信息', [
            'payment_name' => $paymentName,
            'supplier_found' => $supplier ? true : false,
            'supplier_id' => $supplier ? $supplier->id : null,
            'supplier_interface_code' => $supplier ? $supplier->interface_code : null,
            'client_ip' => $clientIp
        ]);
        
        if (!$supplier) {
            throw new MyBusinessException('未找到对应的供应商配置', 404);
        }
        
        // 检查供应商状态
        if ($supplier->status !== Supplier::STATUS_ENABLED) {
            throw new MyBusinessException('供应商已被禁用', 403);
        }
        
        // 验证IP白名单
        $this->validateCallbackIpWhitelist($supplier, $clientIp);
        
        // 将供应商信息存储到请求中，供后续使用
        $request->supplier = $supplier;
        
        // 继续执行后续处理
        return $handler($request);
    }
    
    /**
     * 根据支付服务商名称获取供应商
     * 
     * @param string $paymentName
     * @return Supplier|null
     */
    private function getSupplierByPaymentName(string $paymentName): ?Supplier
    {
        // 根据支付服务商名称动态映射到 interface_code
        $studly = $this->toStudlyCase($paymentName);
        return Supplier::where('interface_code', $studly)->first();
    }
    
    /**
     * 将字符串转为 StudlyCase（例："haitun_pay"/"haitun" -> "HaitunPay"/"Haitun"）
     * @param string $string
     * @return string
     */
    private function toStudlyCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
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
     * 验证供应商回调IP白名单
     * 
     * @param Supplier $supplier
     * @param string $clientIp
     * @throws MyBusinessException
     */
    private function validateCallbackIpWhitelist(Supplier $supplier, string $clientIp): void
    {
        // 如果供应商没有设置回调IP白名单，则允许所有IP
        if (empty($supplier->callback_whitelist_ips)) {
            return;
        }
        
        // 将字符串格式的白名单转换为数组
        $whitelistIps = $this->parseWhitelistIps($supplier->callback_whitelist_ips);
        
        // 检查客户端IP是否在白名单中
        foreach ($whitelistIps as $allowedIp) {
            if ($this->ipMatches($clientIp, $allowedIp)) {
                return;
            }
        }
        
        // IP不在白名单中，抛出异常
        throw new MyBusinessException("回调IP地址 {$clientIp} 不在供应商允许的访问列表中", 403);
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
