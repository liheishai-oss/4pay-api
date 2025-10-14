<?php

namespace app\service;

use app\admin\controller\v1\system\validator\LoginDataValidator;
use app\common\helpers\ConfigHelper;
use app\exception\MyBusinessException;
use app\model\SystemConfig;
use app\repository\AdminAuthRepository;

class LoginService
{
    public function __construct(private readonly AdminAuthRepository $repository, private readonly LoginDataValidator $doginDataValidator)
    {
    }

    public function login(array $param): array
    {
        // --- 改造：检查是否开启谷歌验证 ---
        $config = ConfigHelper::getAll();

        $googleEnabled = json_decode($config['admin_login_verify_mode'], true);

        if (count($googleEnabled)>0) {
            $googleEnabled = in_array('google', $googleEnabled);
        }

        if ($googleEnabled) {
            $admin = $this->doginDataValidator->validate($param,false);
//            // 查询谷歌密钥
//            $googleSecret = SystemConfig::where('config_key', 'google_2fa_secret')
//                ->where('scope', 'system')
//                ->where('user_id', $admin->id)
//                ->value('config_value');
//
//            if (!$googleSecret || !$this->verifyGoogleAuth($param['google_code'], $googleSecret)) {
//                throw new MyBusinessException('Google 验证码错误');
//            }
        } else {
            $admin = $this->doginDataValidator->validate($param);
        }
        // --- 改造结束 ---

        if ($admin->group_id <= 0) {
            throw new MyBusinessException('用户信息错误');
        }

        $allGroupIds = $this->repository->getAllGroupIdsIncludingSelf($admin->group_id);
        $userInfo = [
            'admin_id' => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'user_group_id' => $admin->group_id,
            'group_id' => json_encode($allGroupIds),
            'status' => $admin->status,
        ];

        $token = $this->repository->persistLoginToken($userInfo);


        return ['Authorization' => $token];
    }

    private function verifyGoogleAuth(string $googleCode, string $secret): bool
    {
        $googleAuthenticator = new \Google\Authenticator\GoogleAuthenticator();
        return $googleAuthenticator->checkCode($secret, $googleCode);
    }
}