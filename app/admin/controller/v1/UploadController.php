<?php

namespace app\admin\controller\v1;

use app\exception\MyBusinessException;

use Illuminate\Support\Facades\Storage;

use support\Request;
use support\Response;

class UploadController
{
    // 上传文件接口
    public function upload(Request $request): Response
    {
        // 获取上传的文件
        $file = $request->file('file');
        $appid = $request->get('appid');
        if(empty($appid)){
            throw new MyBusinessException('请先填写appid');
        }
        if (!$file) {
            throw new MyBusinessException('没有文件上传');
        }
        // 获取文件路径
        $filePath = $file->getRealPath();

        // 使用 finfo 获取文件的 MIME 类型
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        print_r($mimeType);
        // 验证文件类型
        $allowedTypes = ['text/plain']; // 证书文件类型
        if (!in_array($mimeType, $allowedTypes)) {
            throw new MyBusinessException('不支持的文件类型');
        }

        // 获取当前日期
        $dateDir = date('Ymd'); // 格式为 20250417

        // 生成存储路径
        $filePath = "/upload/files/{$dateDir}/".$appid.'_'. $file->getUploadName();

        // 将文件保存到 storage 的 public 目录


        $file->move(config("filesystems.disks.public.root").$filePath);

        // 返回上传结果
        return success(['file_path' => $filePath]);
    }

    // 上传文件接口
    public function images(Request $request): Response
    {
        // 获取上传的文件
        $file = $request->file('file');

        if (!$file) {
            throw new MyBusinessException('没有文件上传');
        }
        // 获取文件路径
        $filePath = $file->getRealPath();

        // 使用 finfo 获取文件的 MIME 类型
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // 证书文件类型
        if (!in_array($mimeType, $allowedTypes)) {
            throw new MyBusinessException('不支持的文件类型');
        }

        // 获取当前日期
        $dateDir = date('Ymd'); // 格式为 20250417

        // 生成存储路径
        $filePath = "/upload/files/{$dateDir}/images/". $file->getUploadName();

        // 将文件保存到 storage 的 public 目录


        $file->move(config("filesystems.disks.public.root").$filePath);

        // 返回上传结果
        return success(['file_path' => $filePath,'url'=>'http://hub.huizeqifu.com/home'.$filePath]);
    }
}