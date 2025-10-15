<?php

namespace app\admin\controller\v1;

use app\exception\MyBusinessException;
use app\model\SystemConfig;
use app\service\GoogleAuthService;
use support\Request;
use support\Response;

class GoogleAuthController
{
    public function __construct(
        private readonly GoogleAuthService $googleAuthService
    ) {
    }

    /**
     * 生成谷歌验证码二维码
     */
    public function generateQrCode(Request $request): Response
    {
        try {
            $userId = $request->userData['admin_id'];
            
            // 检查是否已经绑定
            $existingSecret = SystemConfig::where('config_key', 'google_2fa_secret')
                ->where('user_id', $userId)
                ->value('config_value');
                
            if ($existingSecret) {
                return success([
                    'already_bound' => true,
                    'message' => '您已经绑定过谷歌验证码'
                ]);
            }
            
            // 生成新的密钥和二维码
            $result = $this->googleAuthService->generateQrCode($userId);
            
            return success($result);
        } catch (\Exception $e) {
            return error('生成二维码失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 验证并绑定谷歌验证码
     */
    public function bindGoogleAuth(Request $request): Response
    {
        try {
            $userId = $request->userData['admin_id'];
            $googleCode = $request->post('google_code');
            $secret = $request->post('secret');
            
            if (empty($googleCode) || empty($secret)) {
                throw new MyBusinessException('参数错误');
            }
            
            // 验证谷歌验证码
            if (!$this->googleAuthService->verifyCode($secret, $googleCode)) {
                throw new MyBusinessException('谷歌验证码错误');
            }
            
            // 保存密钥到数据库
            $this->googleAuthService->saveSecret($userId, $secret);
            
            return success([], '谷歌验证码绑定成功');
        } catch (\Exception $e) {
            return error('绑定失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 检查用户是否已绑定谷歌验证码
     */
    public function checkBinding(Request $request): Response
    {
        try {
            $userId = $request->userData['admin_id'];
            
            $secret = SystemConfig::where('config_key', 'google_2fa_secret')
                ->where('user_id', $userId)
                ->value('config_value');
                
            return success([
                'is_bound' => !empty($secret)
            ]);
        } catch (\Exception $e) {
            return error('检查绑定状态失败：' . $e->getMessage(), 500);
        }
    }
}