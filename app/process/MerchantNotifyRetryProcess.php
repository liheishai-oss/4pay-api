<?php

namespace app\process;

use support\Log;
use app\service\notification\MerchantNotificationService;
use Workerman\Crontab\Crontab;

/**
 * 商户通知重试队列处理进程
 * 每10秒检查一次重试队列，处理失败的通知
 */
class MerchantNotifyRetryProcess
{
    private $notificationService;
    
    public function __construct()
    {
        $this->notificationService = new MerchantNotificationService();
    }
    
    /**
     * 进程启动时调用
     */
    public function onWorkerStart()
    {
        Log::info('商户通知重试队列处理进程启动');
        
        // 每10秒处理一次重试队列
        new Crontab('*/10 * * * * *', function(){
            try {
                $this->processRetryQueue();
            } catch (\Exception $e) {
                Log::error('处理重试队列异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }
    
    /**
     * 处理重试队列
     */
    private function processRetryQueue(): void
    {
        $this->notificationService->processRetryQueue();
    }
    
    /**
     * 进程停止时调用
     */
    public function onWorkerStop()
    {
        Log::info('商户通知重试队列处理进程停止');
    }
}

