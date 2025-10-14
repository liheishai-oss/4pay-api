<?php
namespace app\common\log;

use Monolog\Formatter\JsonFormatter;
class TraceLineFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(
            self::BATCH_MODE_NEWLINES,   // 每条日志单独一行
            true,                        // 每行自动加换行符
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES // 让日志更易读
        );
    }
    public function format(array $record): string
    {
        return parent::format($record);
    }
}
