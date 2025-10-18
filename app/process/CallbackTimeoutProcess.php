<?php

namespace app\process;

use app\admin\service\CallbackTimeoutService;
use support\Log;
use Workerman\Timer;

/**
 * 回调超时检查进程
 * 定期检查支付成功但10分钟内没有回调成功的订单，将其状态改为回调失败
 */
class CallbackTimeoutProcess
{
    /**
     * 进程启动
     */
    public function onWorkerStart()
    {
//        print_r("回调超时检查进程启动");
        Log::info('回调超时检查进程启动', [
            'process_id' => getmypid(),
            'start_time' => date('Y-m-d H:i:s')
        ]);

        // 每5分钟检查一次回调超时的订单
        new \Workerman\Crontab\Crontab('*/5 * * * *', function(){
            echo "开始检查回调超时订单";
            $this->checkCallbackTimeout();
        });
        
        Log::info('回调超时检查进程已启动', [
            'process_name' => 'CallbackTimeoutProcess',
            'check_interval' => '5分钟',
            'timeout_minutes' => '10分钟',
            'description' => '监控支付成功但10分钟内没有回调成功的订单'
        ]);
    }

    /**
     * 检查回调超时的订单
     */
    private function checkCallbackTimeout(): void
    {
        try {
            $callbackTimeoutService = new CallbackTimeoutService();
            
            // 检查10分钟内没有回调成功的订单
            $result = $callbackTimeoutService->checkCallbackTimeout(10, 50); // 10分钟超时，最多处理50个
            
            if ($result['total'] > 0) {
                Log::info('回调超时检查：发现超时订单', [
                    'total' => $result['total'],
                    'success' => $result['success'],
                    'skip' => $result['skip'],
                    'error' => $result['error'],
                    'timeout_minutes' => $result['timeout_minutes']
                ]);
                
                // 记录处理结果详情
                foreach ($result['results'] as $orderResult) {
                    if ($orderResult['status'] === 'success') {
                        Log::info('回调超时检查：已标记订单为回调失败', [
                            'order_no' => $orderResult['order_no'],
                            'paid_time' => $orderResult['paid_time'],
                            'time_since_paid' => $orderResult['time_since_paid']
                        ]);
                    } elseif ($orderResult['status'] === 'skip') {
                        Log::debug('回调超时检查：跳过订单', [
                            'order_no' => $orderResult['order_no'],
                            'reason' => $orderResult['message']
                        ]);
                    } elseif ($orderResult['status'] === 'error') {
                        Log::warning('回调超时检查：处理订单失败', [
                            'order_no' => $orderResult['order_no'],
                            'error' => $orderResult['message']
                        ]);
                    }
                }
            } else {
                Log::debug('回调超时检查：未发现需要处理的超时订单');
            }
            
        } catch (\Exception $e) {
            echo $e->getMessage();
            Log::error('回调超时检查进程执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 进程停止
     */
    public function onWorkerStop()
    {
        Log::info('回调超时检查进程已停止');
    }
}
