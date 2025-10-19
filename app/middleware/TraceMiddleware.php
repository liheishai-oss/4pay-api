<?php

namespace app\middleware;

use app\service\TraceService;
use app\common\helpers\TraceIdHelper;
use support\Request;
use support\Response;
use support\Log;

/**
 * 追踪中间件
 * 自动记录关键步骤到追踪服务
 */
class TraceMiddleware
{
    private TraceService $traceService;

    public function __construct()
    {
        $this->traceService = new TraceService();
    }

    /**
     * 处理请求
     */
    public function process(Request $request, callable $next)
    {
        $startTime = microtime(true);
        $traceId = TraceIdHelper::get();

        // 记录请求开始
        $this->logRequestStart($request, $traceId);
        
        try {
            // 执行下一个中间件或控制器
            $response = $next($request);
            
            // 记录请求成功
            $this->logRequestSuccess($request, $response, $traceId, $startTime);
            
            return $response;
        } catch (\Exception $e) {
            // 记录请求失败
            $this->logRequestError($request, $e, $traceId, $startTime);
            throw $e;
        }
    }

    /**
     * 记录请求开始
     */
    private function logRequestStart(Request $request, string $traceId): void
    {
        $path = $request->path();
        $method = $request->method();
        
        // 根据路径判断业务类型
        if ($this->isOrderCreate($path)) {
            $this->logOrderCreateStart($request, $traceId);
        } elseif ($this->isOrderQuery($path)) {
            $this->logOrderQueryStart($request, $traceId);
        } elseif ($this->isBalanceQuery($path)) {
            $this->logBalanceQueryStart($request, $traceId);
        }
    }

    /**
     * 记录请求成功
     */
    private function logRequestSuccess(Request $request, $response, string $traceId, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $path = $request->path();
        $statusCode = $response->getStatusCode();
        
        // 根据HTTP状态码和业务状态码判断是成功还是失败
        $responseData = json_decode($response->rawBody(), true);
        $businessCode = $responseData['code'] ?? $statusCode;
        $isSuccess = ($statusCode >= 200 && $statusCode < 300) && ($businessCode >= 200 && $businessCode < 300);
        
        if ($isSuccess) {
            // 成功响应
            if ($this->isOrderCreate($path)) {
                $this->logOrderCreateSuccess($request, $response, $traceId, $duration);
            } elseif ($this->isOrderQuery($path)) {
                $this->logOrderQuerySuccess($request, $response, $traceId, $duration);
            } elseif ($this->isBalanceQuery($path)) {
                $this->logBalanceQuerySuccess($request, $response, $traceId, $duration);
            }
        } else {
            // 失败响应
            if ($this->isOrderCreate($path)) {
                $this->logOrderCreateFailed($request, $response, $traceId, $duration);
            } elseif ($this->isOrderQuery($path)) {
                $this->logOrderQueryFailed($request, $response, $traceId, $duration);
            } elseif ($this->isBalanceQuery($path)) {
                $this->logBalanceQueryFailed($request, $response, $traceId, $duration);
            }
        }
    }

    /**
     * 记录请求失败
     */
    private function logRequestError(Request $request, \Exception $e, string $traceId, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $path = $request->path();
        
        if ($this->isOrderCreate($path)) {
            $this->logOrderCreateError($request, $e, $traceId, $duration);
        } elseif ($this->isOrderQuery($path)) {
            $this->logOrderQueryError($request, $e, $traceId, $duration);
        } elseif ($this->isBalanceQuery($path)) {
            $this->logBalanceQueryError($request, $e, $traceId, $duration);
        }
    }

    /**
     * 判断是否为订单创建请求
     */
    private function isOrderCreate(string $path): bool
    {
        return str_contains($path, '/api/v1/order/create');
    }

    /**
     * 判断是否为订单查询请求
     */
    private function isOrderQuery(string $path): bool
    {
        return str_contains($path, '/api/v1/order/query');
    }

    /**
     * 判断是否为余额查询请求
     */
    private function isBalanceQuery(string $path): bool
    {
        return str_contains($path, '/api/v1/merchant/balance');
    }

    /**
     * 记录订单创建开始
     */
    private function logOrderCreateStart(Request $request, string $traceId): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        } else if (isset($data['merchant_key'])) {
            // 如果商户验证还没完成，尝试通过merchant_key查找商户ID
            try {
                $merchant = \app\model\Merchant::where('merchant_key', $data['merchant_key'])->first();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            } catch (\Exception $e) {
                // 如果查找失败，记录日志但不影响主流程
                Log::warning('无法通过merchant_key查找merchant_id', [
                    'merchant_key' => $data['merchant_key'] ?? '',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // 记录到追踪服务
        $this->traceService->logLifecycleStep(
            $traceId,
            0, // 订单ID暂时为0，创建成功后会更新
            $merchantId,
            'order_create_start',
            'pending',
            [
                'merchant_key' => $data['merchant_key'] ?? '',
                'merchant_order_no' => $data['merchant_order_no'] ?? '',
                'order_amount' => $data['order_amount'] ?? '',
                'product_code' => $data['product_code'] ?? '',
                'client_ip' => $request->getRealIp(),
                'user_agent' => $request->header('User-Agent', '')
            ],
            null,
            0,
            null, // 平台订单号暂时为空
            $data['merchant_order_no'] ?? null // 商户订单号
        );

        // 记录到现有日志系统
        Log::info('订单创建请求开始', [
            'trace_id' => $traceId,
            'merchant_key' => $data['merchant_key'] ?? '',
            'merchant_order_no' => $data['merchant_order_no'] ?? '',
            'order_amount' => $data['order_amount'] ?? '',
            'product_code' => $data['product_code'] ?? ''
        ]);
    }

    /**
     * 记录订单创建成功
     */
    private function logOrderCreateSuccess(Request $request, Response $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 从响应中获取订单ID（通过order_no查找）
        $orderId = 0;
        if (isset($responseData['data']['order_no'])) {
            // 通过order_no查找对应的order_id
            try {
                $order = \app\model\Order::where('order_no', $responseData['data']['order_no'])->first();
                if ($order) {
                    $orderId = $order->id;
                }
            } catch (\Exception $e) {
                // 如果查找失败，记录日志但不影响主流程
                Log::warning('无法通过order_no查找order_id', [
                    'order_no' => $responseData['data']['order_no'] ?? '',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // 记录到追踪服务
        $this->traceService->logLifecycleStep(
            $traceId,
            $orderId,
            $merchantId,
            'order_create_success',
            'success',
            [
                'order_no' => $responseData['data']['order_no'] ?? '',
                'payment_url' => $responseData['data']['payment_url'] ?? '',
                'response_code' => $responseData['code'] ?? 0,
                'response_message' => $responseData['message'] ?? '',
                'duration_ms' => $duration
            ],
            null,
            $duration,
            $responseData['data']['order_no'] ?? null, // 平台订单号
            $data['merchant_order_no'] ?? null // 商户订单号
        );

        // 记录到现有日志系统
        Log::info('订单创建请求成功', [
            'trace_id' => $traceId,
            'order_no' => $responseData['data']['order_no'] ?? '',
            'duration_ms' => $duration,
            'response_code' => $responseData['code'] ?? 0
        ]);
    }

    /**
     * 记录订单创建失败（HTTP响应失败）
     */
    private function logOrderCreateFailed(Request $request, $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 如果商户验证失败，尝试从请求数据中获取商户信息
        if ($merchantId === 0 && isset($data['merchant_key'])) {
            // 这里可以尝试通过merchant_key查找商户ID，但为了简化，我们暂时使用0
            // 在实际应用中，可能需要查询数据库来获取merchant_id
        }
        
        // 记录到追踪服务
        $this->traceService->logLifecycleStep(
            $traceId,
            0, // 失败时没有订单ID
            $merchantId,
            'order_create_failed',
            'failed',
            [
                'error_code' => $responseData['code'] ?? $response->getStatusCode(),
                'error_message' => $responseData['message'] ?? 'HTTP Error',
                'duration_ms' => $duration
            ],
            null,
            $duration
        );

        // 记录到现有日志系统
        Log::error('订单创建请求失败', [
            'trace_id' => $traceId,
            'error_code' => $responseData['code'] ?? $response->getStatusCode(),
            'error_message' => $responseData['message'] ?? 'HTTP Error',
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录订单创建失败（异常）
     */
    private function logOrderCreateError(Request $request, \Exception $e, string $traceId, float $duration): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logLifecycleStep(
            $traceId,
            0,
            $merchantId,
            'order_create_error',
            'failed',
            [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'duration_ms' => $duration
            ],
            null,
            $duration
        );

        // 记录到现有日志系统
        Log::error('订单创建请求失败', [
            'trace_id' => $traceId,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录订单查询开始
     */
    private function logOrderQueryStart(Request $request, string $traceId): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'by_order_no',
            'query_request_start',
            'pending',
            [
                'order_no' => $data['order_no'] ?? '',
                'merchant_key' => $data['merchant_key'] ?? '',
                'client_ip' => $request->getRealIp(),
                'user_agent' => $request->header('User-Agent', '')
            ]
        );

        // 记录到现有日志系统
        Log::info('订单查询请求开始', [
            'trace_id' => $traceId,
            'order_no' => $data['order_no'] ?? '',
            'merchant_key' => $data['merchant_key'] ?? ''
        ]);
    }

    /**
     * 记录订单查询成功
     */
    private function logOrderQuerySuccess(Request $request, Response $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            $responseData['data']['order_id'] ?? null,
            $merchantId,
            'by_order_no',
            'query_request_success',
            'success',
            [
                'order_no' => $responseData['data']['order_no'] ?? '',
                'order_status' => $responseData['data']['status'] ?? '',
                'response_code' => $responseData['code'] ?? 0,
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::info('订单查询请求成功', [
            'trace_id' => $traceId,
            'order_no' => $responseData['data']['order_no'] ?? '',
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录订单查询失败（HTTP响应失败）
     */
    private function logOrderQueryFailed(Request $request, $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'by_order_no',
            'query_request_failed',
            'failed',
            [
                'error_code' => $responseData['code'] ?? $response->getStatusCode(),
                'error_message' => $responseData['message'] ?? 'HTTP Error',
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::error('订单查询请求失败', [
            'trace_id' => $traceId,
            'error_code' => $responseData['code'] ?? $response->getStatusCode(),
            'error_message' => $responseData['message'] ?? 'HTTP Error',
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录订单查询失败（异常）
     */
    private function logOrderQueryError(Request $request, \Exception $e, string $traceId, float $duration): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'by_order_no',
            'query_request_error',
            'failed',
            [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::error('订单查询请求失败', [
            'trace_id' => $traceId,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录余额查询开始
     */
    private function logBalanceQueryStart(Request $request, string $traceId): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'balance_query',
            'balance_query_start',
            'pending',
            [
                'merchant_key' => $data['merchant_key'] ?? '',
                'client_ip' => $request->getRealIp(),
                'user_agent' => $request->header('User-Agent', '')
            ]
        );

        // 记录到现有日志系统
        Log::info('余额查询请求开始', [
            'trace_id' => $traceId,
            'merchant_key' => $data['merchant_key'] ?? ''
        ]);
    }

    /**
     * 记录余额查询成功
     */
    private function logBalanceQuerySuccess(Request $request, Response $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'balance_query',
            'balance_query_success',
            'success',
            [
                'balance' => $responseData['data']['balance'] ?? 0,
                'response_code' => $responseData['code'] ?? 0,
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::info('余额查询请求成功', [
            'trace_id' => $traceId,
            'balance' => $responseData['data']['balance'] ?? 0,
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录余额查询失败（HTTP响应失败）
     */
    private function logBalanceQueryFailed(Request $request, $response, string $traceId, float $duration): void
    {
        $data = $request->all();
        $responseData = json_decode($response->rawBody(), true);
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'balance_query',
            'balance_query_failed',
            'failed',
            [
                'error_code' => $responseData['code'] ?? $response->getStatusCode(),
                'error_message' => $responseData['message'] ?? 'HTTP Error',
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::error('余额查询请求失败', [
            'trace_id' => $traceId,
            'error_code' => $responseData['code'] ?? $response->getStatusCode(),
            'error_message' => $responseData['message'] ?? 'HTTP Error',
            'duration_ms' => $duration
        ]);
    }

    /**
     * 记录余额查询失败（异常）
     */
    private function logBalanceQueryError(Request $request, \Exception $e, string $traceId, float $duration): void
    {
        $data = $request->all();
        
        // 从请求中获取商户信息
        $merchantId = 0;
        if (isset($request->merchant) && isset($request->merchant['id'])) {
            $merchantId = $request->merchant['id'];
        }
        
        // 记录到追踪服务
        $this->traceService->logQueryStep(
            $traceId,
            null,
            $merchantId,
            'balance_query',
            'balance_query_error',
            'failed',
            [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'duration_ms' => $duration
            ],
            $duration
        );

        // 记录到现有日志系统
        Log::error('余额查询请求失败', [
            'trace_id' => $traceId,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);
    }
}