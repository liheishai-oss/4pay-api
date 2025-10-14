<?php

namespace app\middleware;

use app\common\helpers\ExceptionContextHelper;
use app\common\helpers\TraceIdHelper;
use app\enums\log\LogReason;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class TraceMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $startTime = microtime(true);
        // 初始化 trace_id
        $traceId = TraceIdHelper::get();

        $response = $handler($request);

        $duration = (int)((microtime(true) - $startTime) * 1000);

        $exception = ExceptionContextHelper::get();
        $route = LogReason::getSceneStageByRoute($request->path());
        $logData = [
            'trace_id' => $traceId,
            'request_date' => date('Y-m-d H:i:s'),
            'duration_ms' => $duration,
            'scene'=>$route['scene'],
            'stage'=>$route['stage'],
            'request' => [
                'method' => $request->method(),
                'uri' => $request->path(),
                'client_ip' => $request->getRealIp(),
                'headers' => $request->header(),
                'body' => $request->all(),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => method_exists($response, 'rawBody') ? $response->rawBody() : null,
            ],
            'extra' => []
        ];
        if ($exception instanceof \Throwable) {

            $extra = [
                'error_code'            => $exception->getCode(),
                'error_message'         => $exception->getMessage(),
                'file'                  => $exception->getFile(),
                'line'                  => $exception->getLine(),
                'stack_trace'                 => $exception->getTraceAsString(),
            ];
            $logData['extra'] = $extra;
            // 日志记录（全链路追踪）
            Log::channel('system')->error('异常请求', $logData);
        }else{
            // 日志记录（全链路追踪）
            Log::channel('system')->info('请求日志', $logData);
        }


        return $response;
    }
}
