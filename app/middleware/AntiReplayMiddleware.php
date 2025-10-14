<?php

namespace app\middleware;

use Exception;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\exception\MyBusinessException;
use support\Redis;

class AntiReplayMiddleware implements MiddlewareInterface
{
    /**
     * 处理防重放攻击
     */
    public function process(Request $request, callable $handler): Response
    {
        // 检查是否需要防重放验证
        if (!$this->needAntiReplayValidation($request)) {
            return $handler($request);
        }
        
        $data = $request->all();
        
        // 检查nonce参数
        if (empty($data['nonce'])) {
            throw new MyBusinessException('缺少nonce参数', 400);
        }

        $merchantKey = $data['merchant_key'] ?? '';
        $nonce = $data['nonce'];

        // 检查nonce是否已使用
        $nonceKey = "nonce:{$merchantKey}:{$nonce}";
        if (Redis::exists($nonceKey)) {
            throw new MyBusinessException('请求重复', 400);
        }

        // 设置nonce过期时间（5分钟，仅在Redis正常时）
        try {
            Redis::setex($nonceKey, 300, 1);
        } catch (\Throwable $redisException) {
            Log::warning('Redis写入失败，跳过nonce缓存', [
                'nonce_key' => $nonceKey,
                'error' => $redisException->getMessage()
            ]);
        }

        return $handler($request);
    }
    
    /**
     * 检查是否需要防重放验证
     */
    private function needAntiReplayValidation(Request $request): bool
    {
        $controllerClass = $request->controller;
        $action = $request->action;
        
        if (!$controllerClass || !$action) {
            return false;
        }
        
        // 需要防重放验证的接口
        $needAntiReplay = [
            'app\\api\\controller\\v1\\order\\CreateController' => ['create'],
            'app\\api\\controller\\v1\\order\\QueryController' => ['query'],
            'app\\api\\controller\\v1\\merchant\\BalanceController' => ['query']
        ];
        
        return isset($needAntiReplay[$controllerClass]) && 
               in_array($action, $needAntiReplay[$controllerClass]);
    }
}