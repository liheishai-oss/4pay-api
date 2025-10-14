<?php

namespace app\common\helpers;

use app\common\AppConstants;
use app\common\CommBase;
use app\service\notifier\OrderNotifier;
use support\Response;
use support\Log;
use Ramsey\Uuid\Uuid;
class CreateOrderExceptionHandler
{


    public function report(\Throwable $e, array $params): Response
    {
        $params['merchant_order_number'] = $params['merchant_order_number'] ?? '';
        $params['appid'] = $params['appid'] ?? '未传递appid';

        $message = '[appid]:' . $params['appid'] . ',' . $e->getMessage();

        CommBase::pushToRobotQueue([
            'content' => OrderNotifier::formatCreateFailedMessage($params, $message, $e->getFile(), $e->getLine())
        ], AppConstants::TELEGRAM_DEFAULT_CHAT_ID);
        ExceptionContextHelper::set($e);

        return error($e->getCode(), $message);
    }


}