<?php

namespace app\service\supplier;

use app\model\TelegramAdmin;

class TelegramAdminListService
{
    /**
     * 获取启用的Telegram管理员列表
     * 返回格式：id,账号-昵称
     * @return array
     */
    public function getEnabledAdminList(): array
    {
        $admins = TelegramAdmin::enabled()
            ->select('id', 'username', 'nickname')
            ->orderBy('id', 'asc')
            ->get();

        $result = [];
        foreach ($admins as $admin) {
            $result[] = [
                'id'   => $admin->id,
                'name' => $admin->username . '-' . $admin->nickname
            ];
        }

        return $result;
    }
}





