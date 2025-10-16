<?php

namespace app\process;

use app\admin\service\CallbackMonitorService;
use support\Log;
use Workerman\Timer;

/**
 * 回调监控进程
 * 定期检查支付成功但5秒内没有通知的订单
 */
class CallbackMonitorProcess
{
    /**
     * 进程启动
     */
    public function onWorkerStart()
    {
        // 每30秒检查一次未通知的订单
        Timer::add(30, function() {
            $this->checkUnnotifiedOrders();
        });
        
        Log::info('回调监控进程已启动', [
            'process_name' => 'CallbackMonitorProcess',
            'check_interval' => '30秒',
            'description' => '监控支付成功但5秒内没有通知的订单'
        ]);
    }

    /**
     * 检查未通知的订单
     */
    private function checkUnnotifiedOrders(): void
    {
        try {
            $callbackMonitorService = new CallbackMonitorService();
            
            // 获取支付成功但5秒内没有通知的订单
            $result = $callbackMonitorService->fixUnnotifiedOrders(24, 3, 50); // 24小时内，支付成功状态，最多50个
            
            if ($result['total'] > 0) {
                Log::info('回调监控：发现未通知订单', [
                    'total' => $result['total'],
                    'success' => $result['success'],
                    'skip' => $result['skip'],
                    'error' => $result['error'],
                    'filter_conditions' => $result['filter_conditions']
                ]);
                
                // 记录处理结果详情
                foreach ($result['results'] as $orderResult) {
                    if ($orderResult['status'] === 'success') {
                        Log::info('回调监控：已触发订单通知', [
                            'order_no' => $orderResult['order_no'],
                            'paid_time' => $orderResult['paid_time'],
                            'time_since_paid' => $orderResult['time_since_paid']
                        ]);
                    } elseif ($orderResult['status'] === 'skip') {
                        Log::debug('回调监控：跳过订单', [
                            'order_no' => $orderResult['order_no'],
                            'reason' => $orderResult['message']
                        ]);
                    } elseif ($orderResult['status'] === 'error') {
                        Log::warning('回调监控：处理订单失败', [
                            'order_no' => $orderResult['order_no'],
                            'error' => $orderResult['message']
                        ]);
                    }
                }
            } else {
                Log::debug('回调监控：未发现需要处理的订单');
            }
            
        } catch (\Exception $e) {
            Log::error('回调监控进程执行失败', [
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
        Log::info('回调监控进程已停止');
    }
}
