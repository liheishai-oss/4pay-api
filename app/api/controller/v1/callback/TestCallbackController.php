<?php

namespace app\api\controller\v1\callback;

use app\common\helpers\SignatureHelper;
use support\Request;
use support\Response;
use support\Log;

/**
 * 测试回调控制器
 * 用于测试商户回调功能，支持签名验证
 */
class TestCallbackController
{
    /**
     * 测试回调接口
     * 接收支付系统发送的回调数据，验证签名后返回success
     * 
     * @param Request $request
     * @return Response
     */
    public function notify(Request $request)
    {
        return 'success';
        $startTime = microtime(true);
        
        try {
            // 获取请求数据
            $data = $request->all();
            $clientIp = $request->getRealIp();
            $userAgent = $request->header('User-Agent', '');
            
            // 记录回调接收日志
            $this->logCallbackReceived($data, $clientIp, $userAgent);
            
            Log::info('测试回调接口收到请求', [
                'method' => $request->method(),
                'headers' => $request->header(),
                'data' => $data,
                'ip' => $clientIp
            ]);
            
            // 验证必要参数
            $requiredFields = ['order_no', 'merchant_order_no', 'amount', 'status', 'sign'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    Log::warning('测试回调缺少必要参数', [
                        'missing_field' => $field,
                        'received_data' => $data
                    ]);
                    
                    return $this->errorResponse('缺少必要参数: ' . $field);
                }
            }
            
            // 验证签名
            $secretKey = $this->getTestSecretKey();
            $isValid = SignatureHelper::verify($data, $secretKey);
            
            if (!$isValid) {
                Log::warning('测试回调签名验证失败', [
                    'received_sign' => $data['sign'] ?? '',
                    'data' => $data
                ]);
                
                $response = $this->errorResponse('签名验证失败');
                $this->logCallbackResponse($data, $response, $startTime, false);
                return $response;
            }
            
            Log::info('测试回调签名验证成功', [
                'order_no' => $data['order_no'],
                'merchant_order_no' => $data['merchant_order_no'],
                'amount' => $data['amount'],
                'status' => $data['status']
            ]);
            
            // 返回成功响应
            $response = $this->successResponse();
            $this->logCallbackResponse($data, $response, $startTime, true);
            return $response;
            
        } catch (\Exception $e) {
            Log::error('测试回调处理异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            $response = $this->errorResponse('处理异常: ' . $e->getMessage());
            $this->logCallbackResponse($request->all(), $response, $startTime, false);
            return $response;
        }
    }
    
    /**
     * 获取测试用的密钥
     * @return string
     */
    private function getTestSecretKey(): string
    {
        // 这里使用一个固定的测试密钥
        // 在实际使用中，应该根据商户信息动态获取
        return 'test_secret_key_123456789';
    }
    
    /**
     * 返回成功响应
     * @return Response
     */
    private function successResponse(): Response
    {
        // 返回纯文本 "success"
        return response('success', 200, [
            'Content-Type' => 'text/plain; charset=utf-8'
        ]);
    }
    
    /**
     * 返回错误响应
     * @param string $message
     * @return Response
     */
    private function errorResponse(string $message): Response
    {
        return response($message, 400, [
            'Content-Type' => 'text/plain; charset=utf-8'
        ]);
    }
    
    /**
     * 生成测试回调数据
     * 用于生成带有正确签名的测试数据
     * 
     * @param Request $request
     * @return Response
     */
    public function generateTestData(Request $request): Response
    {
        try {
            $orderNo = $request->input('order_no', 'TEST_' . date('YmdHis') . '_' . rand(1000, 9999));
            $merchantOrderNo = $request->input('merchant_order_no', 'MERCHANT_' . date('YmdHis') . '_' . rand(1000, 9999));
            $amount = $request->input('amount', '100.00');
            $status = $request->input('status', 'success');
            
            // 构建回调数据（不包含third_party_order_no和extra_data字段）
            $data = [
                'order_no' => $orderNo,
                'merchant_order_no' => $merchantOrderNo,
                'amount' => number_format((float)$amount, 2, '.', ''), // 确保金额格式为元，保留2位小数
                'status' => $status,
                'status_text' => $this->getStatusText($status),
                'paid_time' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'timestamp' => time()
            ];
            
            // 生成签名
            $secretKey = $this->getTestSecretKey();
            $signature = SignatureHelper::generate($data, $secretKey);
            $data['sign'] = $signature;
            
            Log::info('生成测试回调数据', [
                'data' => $data,
                'secret_key' => $secretKey
            ]);
            
            return json([
                'code' => 200,
                'message' => '生成成功',
                'data' => [
                    'callback_data' => $data,
                    'secret_key' => $secretKey,
                    'curl_example' => $this->generateCurlExample($data)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('生成测试回调数据异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'message' => '生成失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
    
    /**
     * 获取状态文本
     * @param string $status
     * @return string
     */
    private function getStatusText(string $status): string
    {
        $statusMap = [
            'success' => '支付成功',
            'failed' => '支付失败',
            'pending' => '待支付',
            'processing' => '支付中'
        ];
        
        return $statusMap[$status] ?? '未知状态';
    }
    
    /**
     * 生成curl测试示例
     * @param array $data
     * @return string
     */
    private function generateCurlExample(array $data): string
    {
        $url = 'http://localhost:8787/api/v1/callback/test/notify';
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        return "curl -X POST '{$url}' \\\n" .
               "  -H 'Content-Type: application/json' \\\n" .
               "  -d '{$jsonData}'";
    }
    
    /**
     * 测试签名验证
     * @param Request $request
     * @return Response
     */
    public function testSignature(Request $request): Response
    {
        try {
            $data = $request->all();
            $secretKey = $this->getTestSecretKey();
            
            // 生成签名
            $generatedSign = SignatureHelper::generate($data, $secretKey);
            
            // 验证签名
            $data['sign'] = $generatedSign;
            $isValid = SignatureHelper::verify($data, $secretKey);
            
            return json([
                'code' => 200,
                'message' => '签名测试完成',
                'data' => [
                    'input_data' => $request->all(),
                    'generated_signature' => $generatedSign,
                    'signature_valid' => $isValid,
                    'secret_key' => $secretKey
                ]
            ]);
            
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'message' => '签名测试失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
    
    /**
     * 记录回调接收日志
     * @param array $data
     * @param string $clientIp
     * @param string $userAgent
     */
    private function logCallbackReceived(array $data, string $clientIp, string $userAgent): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'callback_received',
            'order_no' => $data['order_no'] ?? '',
            'merchant_order_no' => $data['merchant_order_no'] ?? '',
            'amount' => $data['amount'] ?? '',
            'status' => $data['status'] ?? '',
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'sign' => $data['sign'] ?? '',
            'raw_data' => $data
        ];
        
        // 记录到本地日志文件
        $this->writeToLocalLog('callback_received', $logData);
        
        Log::info('回调接收日志', $logData);
    }
    
    /**
     * 记录回调响应日志
     * @param array $data
     * @param Response $response
     * @param float $startTime
     * @param bool $signatureValid
     */
    private function logCallbackResponse(array $data, Response $response, float $startTime, bool $signatureValid): void
    {
        $responseTime = microtime(true) - $startTime;
        $httpCode = $response->getStatusCode();
        $responseContent = (string)$response->rawBody();
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'callback_response',
            'order_no' => $data['order_no'] ?? '',
            'merchant_order_no' => $data['merchant_order_no'] ?? '',
            'amount' => $data['amount'] ?? '',
            'status' => $data['status'] ?? '',
            'signature_valid' => $signatureValid,
            'http_code' => $httpCode,
            'response_content' => $responseContent,
            'response_time' => round($responseTime, 3),
            'sign' => $data['sign'] ?? ''
        ];
        
        // 记录到本地日志文件
        $this->writeToLocalLog('callback_response', $logData);
        
        Log::info('回调响应日志', $logData);
    }
    
    /**
     * 写入本地日志文件
     * @param string $type
     * @param array $data
     */
    private function writeToLocalLog(string $type, array $data): void
    {
        try {
            $logDir = runtime_path() . '/logs/callback';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
            
            // 确保所有数据都是可序列化的
            $safeData = $this->makeDataSerializable($data);
            $logLine = date('Y-m-d H:i:s') . ' | ' . json_encode($safeData, JSON_UNESCAPED_UNICODE) . "\n";
            
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            Log::error('写入本地回调日志失败', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 确保数据可序列化
     * @param array $data
     * @return array
     */
    private function makeDataSerializable(array $data): array
    {
        $safeData = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $safeData[$key] = $this->makeDataSerializable($value);
            } elseif (is_object($value)) {
                $safeData[$key] = '[Object: ' . get_class($value) . ']';
            } elseif (is_resource($value)) {
                $safeData[$key] = '[Resource]';
            } else {
                $safeData[$key] = (string)$value;
            }
        }
        return $safeData;
    }
    
    /**
     * 获取回调日志
     * @param Request $request
     * @return Response
     */
    public function getCallbackLogs(Request $request): Response
    {
        try {
            $date = $request->input('date', date('Y-m-d'));
            $type = $request->input('type', 'all'); // all, received, response
            $limit = (int)$request->input('limit', 100);
            
            $logDir = runtime_path() . '/logs/callback';
            $logs = [];
            
            if (is_dir($logDir)) {
                $files = glob($logDir . '/*_' . $date . '.log');
                
                foreach ($files as $file) {
                    $fileType = basename($file, '_' . $date . '.log');
                    
                    if ($type !== 'all' && $fileType !== $type) {
                        continue;
                    }
                    
                    $content = file_get_contents($file);
                    $lines = array_filter(explode("\n", $content));
                    
                    foreach (array_slice($lines, -$limit) as $line) {
                        if (empty(trim($line))) continue;
                        
                        $parts = explode(' | ', $line, 2);
                        if (count($parts) === 2) {
                            $timestamp = $parts[0];
                            $data = json_decode($parts[1], true);
                            
                            if ($data) {
                                $logs[] = [
                                    'timestamp' => $timestamp,
                                    'type' => $fileType,
                                    'data' => $data
                                ];
                            }
                        }
                    }
                }
            }
            
            // 按时间倒序排列
            usort($logs, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => [
                    'logs' => array_slice($logs, 0, $limit),
                    'total' => count($logs),
                    'date' => $date,
                    'type' => $type
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取回调日志失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 500,
                'message' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
