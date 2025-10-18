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
            
            // 屏蔽特定本地访问地址的维护状态验证
            // 只允许 127.0.0.1:8787 和 localhost:8787 跳过维护检查
            if ($host === '127.0.0.1:8787' || $host === 'localhost:8787') {
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
            
            // 获取当前服务器IP
            $currentServerIp = $this->getCurrentServerIp();
            
            // 检查数据库表是否存在
            try {
                // 查询当前服务器IP的维护状态
                $currentServer = Server::where('server_ip', $currentServerIp)
                    ->where(function($query) {
                        $query->where('is_maintenance', true)
                              ->orWhere('status', Server::STATUS_MAINTENANCE);
                    })
                    ->first();
            } catch (\Exception $dbError) {
                // 如果数据库表不存在或连接失败，跳过维护状态检查
                Log::warning('维护状态检查跳过，数据库表可能不存在', [
                    'error' => $dbError->getMessage(),
                    'request_uri' => $request->uri(),
                    'current_server_ip' => $currentServerIp
                ]);
                return $handler($request);
            }

            if ($currentServer) {
                // 检测到当前服务器处于维护状态，直接返回HTTP 503状态码
                Log::info('检测到当前服务器维护状态，返回HTTP 503', [
                    'current_server' => [
                        'id' => $currentServer->id,
                        'server_name' => $currentServer->server_name,
                        'server_ip' => $currentServer->server_ip,
                        'server_port' => $currentServer->server_port,
                        'status' => $currentServer->status,
                        'is_maintenance' => $currentServer->is_maintenance
                    ],
                    'request_uri' => $request->uri(),
                    'request_method' => $request->method(),
                    'current_server_ip' => $currentServerIp
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

    /**
     * 获取当前服务器IP地址
     * @return string
     */
    private function getCurrentServerIp(): string
    {
        // 优先使用SERVER_ADDR
        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }
        
        // 尝试获取本地IP
        $localIp = $this->getLocalIpAddress();
        if ($localIp) {
            return $localIp;
        }
        
        // 默认返回127.0.0.1
        return '127.0.0.1';
    }

    /**
     * 获取本地IP地址
     * @return string|null
     */
    private function getLocalIpAddress(): ?string
    {
        try {
            // 尝试通过socket获取本地IP
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket === false) {
                return null;
            }
            
            socket_connect($socket, '8.8.8.8', 80);
            socket_getsockname($socket, $localIp);
            socket_close($socket);
            
            return $localIp;
        } catch (\Exception $e) {
            // 如果socket方法失败，尝试其他方法
            try {
                $ip = gethostbyname(gethostname());
                return $ip !== gethostname() ? $ip : null;
            } catch (\Exception $e2) {
                return null;
            }
        }
    }
}
