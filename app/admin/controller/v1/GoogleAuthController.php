<?php

namespace app\admin\controller\v1;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Webman\Http\Response;

class GoogleAuthController
{
    protected array $noNeedLogin = ['*'];
    public function generateQrCode(string $secret)
    {
        // 生成密钥
//        $secret = generateSecret();
        $secret = 'JBSWY3DPEHPK3PXI';
        // 创建二维码内容
        $url = "otpauth://totp/MyApp:{$secret}?secret={$secret}&issuer=HaiTunPay"; // 这里的 MyApp 需要替换成实际的应用名称

        // 创建二维码对象
        $qrCode = new QrCode($url);
        $writer = new PngWriter();

        // 生成二维码并输出 PNG 图像
        $result = $writer->write($qrCode);  // 这里会返回 PngData 对象

        // 创建响应并设置正确的 header
        $response = new Response();
        $response->header('Content-Type', 'image/png');
        $response->withBody($result->getString());  // 获取图像字节流并发送到浏览器

        // 返回响应
        return $response;
    }
}