<?php

namespace app\service\supplier;

use app\model\TelegramAdmin;
use app\model\Supplier;
use app\model\SupplierAdmin;
use support\Log;

class TelegramNotificationService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        // 从配置中获取Telegram Bot Token
        $this->botToken = config('telegram.bot_token', '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * 发送供应商绑定成功通知到群组
     * @param Supplier $supplier
     * @param TelegramAdmin $admin
     * @return bool
     */
    public function sendSupplierBindingNotification(Supplier $supplier, TelegramAdmin $admin): bool
    {
        try {
            // 发送到群组（使用供应商的telegram_chat_id）

            $message = $this->formatGroupMessage($supplier, $admin);
            return $this->sendMessage($supplier->telegram_chat_id, $message);


            return false;
        } catch (\Exception $e) {
            Log::error('发送Telegram通知失败', [
                'supplier_id' => $supplier->id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送到群组
     * @param Supplier $supplier
     * @return bool
     */
    public function sendToGroup(Supplier $supplier): bool
    {
        try {
            $message = "🎉 <b>供应商绑定成功通知</b>\n\n";
            $message .= "• 名称：<code>{$supplier->supplier_name}</code>\n";
            
            $message .= "⏰ 绑定时间：" . $supplier->created_at->format('Y-m-d H:i:s') . "\n";

            return $this->sendMessage($supplier->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::error('发送群组通知失败', [
                'supplier_id' => $supplier->id,
                'chat_id' => $supplier->telegram_chat_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 格式化群组消息（包含管理员信息）
     * @param Supplier $supplier
     * @param TelegramAdmin $admin
     * @return string
     */
    private function formatGroupMessage(Supplier $supplier, TelegramAdmin $admin): string
    {
        $message = "🎉 <b>绑定管理员</b>\n";
        $message .= "• 账号：<code>{$admin->username}</code>\n";

        return $message;
    }

    /**
     * 发送Telegram消息
     * @param int $chatId
     * @param string $message
     * @return bool
     */
    private function sendMessage(int $chatId, string $message): bool
    {
        if (empty($this->botToken)) {
            Log::warning('Telegram Bot Token未配置');
            return false;
        }

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error('Telegram API请求失败', [
                'chat_id' => $chatId,
                'http_code' => $httpCode,
                'response' => $response
            ]);
            return false;
        }

        $result = json_decode($response, true);
        if (!$result['ok']) {
            Log::error('Telegram API返回错误', [
                'chat_id' => $chatId,
                'error' => $result['description'] ?? 'Unknown error'
            ]);
            return false;
        }

        Log::info('Telegram消息发送成功', [
            'chat_id' => $chatId,
            'message_id' => $result['result']['message_id'] ?? null
        ]);

        return true;
    }
}