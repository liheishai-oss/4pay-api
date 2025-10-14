<?php

namespace app\admin\controller\v1;

use app\model\Server;
use support\Request;
use support\Response;
use support\Log;

class TestController
{
    /**
     * 检查并自动添加当前外网IP
     * @return array
     */
    private function checkAndAddCurrentIp(): array
    {
        try {
            // 获取当前外网IP
            $currentIp = $this->getCurrentPublicIp();
            if (!$currentIp) {
                return ['success' => false, 'message' => '无法获取当前外网IP'];
            }

            // 检查IP是否已存在
            $existingServer = Server::where('server_ip', $currentIp)->first();
            if ($existingServer) {
                return ['success' => true, 'message' => '当前IP已存在', 'server' => $existingServer];
            }

            // 自动添加当前IP
            $server = new Server();
            $server->server_name = '自动添加-' . $currentIp;
            $server->server_ip = $currentIp;
            $server->server_port = 80;
            $server->server_type = 'web';
            $server->status = 'online';
            $server->is_maintenance = false;
            $server->cpu_usage = 0;
            $server->memory_usage = 0;
            $server->disk_usage = 0;
            $server->network_usage = 0;
            $server->remarks = '系统自动添加的当前服务器IP';
            $server->save();

            Log::info('自动添加当前外网IP到服务器列表', [
                'ip' => $currentIp,
                'server_id' => $server->id
            ]);

            return ['success' => true, 'message' => '已自动添加当前IP', 'server' => $server];
        } catch (\Exception $e) {
            Log::error('检查并添加当前IP失败', [
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => '添加失败: ' . $e->getMessage()];
        }
    }

    /**
     * 获取当前外网IP
     * @return string|null
     */
    private function getCurrentPublicIp(): ?string
    {
        try {
            // 尝试多个IP检测服务
            $services = [
                'https://api.ipify.org',
                'https://ipinfo.io/ip',
                'https://icanhazip.com',
                'https://ifconfig.me/ip'
            ];

            foreach ($services as $service) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'user_agent' => 'Mozilla/5.0 (compatible; ServerManager/1.0)'
                    ]
                ]);
                
                $ip = @file_get_contents($service, false, $context);
                if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return trim($ip);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('获取外网IP失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 测试服务器列表（不需要认证）
     * @param Request $request
     * @return Response
     */
    public function serverList(Request $request): Response
    {
        try {
            // 检查并自动添加当前外网IP
            $ipCheckResult = $this->checkAndAddCurrentIp();
            if ($ipCheckResult['success']) {
                Log::info('IP检查结果', $ipCheckResult);
            }

            $servers = Server::all();
            
            return json([
                'code' => 200,
                'status' => true,
                'message' => '获取成功',
                'data' => [
                    'data' => $servers,
                    'total' => $servers->count()
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'status' => false,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
