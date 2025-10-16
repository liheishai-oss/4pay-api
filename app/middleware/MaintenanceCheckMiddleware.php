<?php

namespace app\middleware;

use support\Request;
use support\Response;
use app\model\Server;
use support\Log;
use Webman\MiddlewareInterface;

/**
 * 维护状态检查中间件
 * 在API请求前检查服务器维护状态，如果检测到维护状态则返回nginx配置
 */
class MaintenanceCheckMiddleware implements MiddlewareInterface
{
    /**
     * 处理请求
     * @param \Webman\Http\Request $request
     * @param callable $handler
     * @return \Webman\Http\Response
     */
    public function process(\Webman\Http\Request $request, callable $handler): \Webman\Http\Response
    {
        // 暂时完全禁用维护状态检查
        Log::info('维护状态检查已完全禁用', [
            'uri' => $request->uri(),
            'host' => $request->host(),
            'method' => $request->method()
        ]);
        return $handler($request);
    }
}
