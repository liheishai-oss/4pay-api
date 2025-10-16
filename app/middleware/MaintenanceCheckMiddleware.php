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
        try {
            // 只对API请求进行维护状态检查，跳过前端页面请求
            $uri = $request->uri();
            $host = $request->host();
            $serverPort = $_SERVER['SERVER_PORT'] ?? null;
            
            Log::info('维护状态检查中间件开始', [
                'uri' => $uri,
                'host' => $host,
                'server_port' => $serverPort,
                'is_api' => str_starts_with($uri, '/api/'),
                'method' => $request->method()
            ]);
            
            // 跳过非API请求
            if (!str_starts_with($uri, '/api/')) {
                return $handler($request);
            }
            
            // 屏蔽特定本地访问地址的维护状态验证
            // 只允许 127.0.0.1:8787 和 localhost:8787 跳过维护检查
            if (($host === '127.0.0.1' || $host === 'localhost') && $serverPort == 8787) {
                Log::info('跳过特定本地访问地址的维护状态检查', [
                    'host' => $host,
                    'server_port' => $serverPort,
                    'uri' => $uri
                ]);
                return $handler($request);
            }
            
            // 排除所有管理员接口，避免管理员无法在维护期间管理服务器状态
            if (str_starts_with($uri, '/api/v1/admin')) {
                Log::info('跳过管理员接口的维护状态检查', [
                    'uri' => $uri
                ]);
                return $handler($request);
            }
            
            // 检查数据库表是否存在
            try {
                // 检查是否有服务器处于维护状态
                $maintenanceServers = Server::where('is_maintenance', true)
                    ->orWhere('status', Server::STATUS_MAINTENANCE)
                    ->get();
            } catch (\Exception $dbError) {
                // 如果数据库表不存在或连接失败，跳过维护状态检查
                Log::warning('维护状态检查跳过，数据库表可能不存在', [
                    'error' => $dbError->getMessage(),
                    'request_uri' => $request->uri()
                ]);
                return $handler($request);
            }

            if ($maintenanceServers->isNotEmpty()) {
                // 检测到维护状态，直接返回HTTP 503状态码
                $maintenanceInfo = $maintenanceServers->map(function ($server) {
                    return [
                        'id' => $server->id,
                        'server_name' => $server->server_name,
                        'server_ip' => $server->server_ip,
                        'server_port' => $server->server_port,
                        'status' => $server->status,
                        'is_maintenance' => $server->is_maintenance
                    ];
                });

                Log::info('检测到服务器维护状态，返回HTTP 503', [
                    'maintenance_servers' => $maintenanceInfo->toArray(),
                    'request_uri' => $request->uri(),
                    'request_method' => $request->method()
                ]);

                // 直接返回HTTP 503状态码，让nginx知道服务器在维护
                return response('Service Unavailable - Maintenance Mode', 503)
                    ->header('Content-Type', 'text/plain')
                    ->header('Retry-After', '300'); // 5分钟后重试
            }

            // 没有维护状态，继续处理请求
            return $handler($request);

        } catch (\Exception $e) {
            Log::error('维护状态检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $request->uri()
            ]);

            // 检查失败时，为了安全起见，继续处理请求
            return $handler($request);
        }
    }
}
