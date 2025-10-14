<?php

namespace app\middleware;

use support\Request;
use support\Response;
use app\model\Server;
use support\Log;

/**
 * 维护状态检查中间件
 * 在API请求前检查服务器维护状态，如果检测到维护状态则返回nginx配置
 */
class MaintenanceCheckMiddleware
{
    /**
     * 处理请求
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        try {
            // 只对API请求进行维护状态检查，跳过前端页面请求
            $uri = $request->uri();
            Log::info('维护状态检查中间件', [
                'uri' => $uri,
                'is_api' => str_starts_with($uri, '/api/')
            ]);
            
            if (!str_starts_with($uri, '/api/')) {
                return $next($request);
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
                return $next($request);
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
            return $next($request);

        } catch (\Exception $e) {
            Log::error('维护状态检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $request->uri()
            ]);

            // 检查失败时，为了安全起见，继续处理请求
            return $next($request);
        }
    }
}
