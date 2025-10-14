<?php

namespace app\service\robot;

use support\Log;

class MessageDispatchService
{
    /**
     * 处理消息分发
     * @param array $messageData
     * @return bool
     */
    public static function dispatch(array $messageData): bool
    {
        try {
            Log::info('消息分发服务开始处理', $messageData);

            Log::info('消息分发服务处理完成');
            return true;
            
        } catch (\Exception $e) {
            Log::error('消息分发服务处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $messageData
            ]);
            
            return false;
        }
    }
}

