<?php

namespace app\service\merchant;

use app\exception\MyBusinessException;
use app\model\Merchant;

class DetailService
{
    /**
     * 获取商户详情
     * @param int $id
     * @return array
     * @throws MyBusinessException
     */
    public function getMerchantDetail(int $id): array
    {
        $merchant = Merchant::with('admin')->find($id);

        if (!$merchant) {
            throw new MyBusinessException('商户不存在11');
        }

        // 将商户数据转换为数组
        $merchantData = $merchant->toArray();
        
        // 确保白名单IP字段为字符串格式（如果为空则设为空字符串）
        if (empty($merchantData['whitelist_ips'])) {
            $merchantData['whitelist_ips'] = '';
        } else {
            // 如果是JSON字符串格式，去掉多余的引号
            $merchantData['whitelist_ips'] = trim($merchantData['whitelist_ips'], '"');
        }

        return $merchantData;
    }
}




