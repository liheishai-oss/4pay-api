<?php

namespace app\admin\controller\v1\payment\channel;

use app\service\thirdparty_payment\TestParamGenerator;
use support\Request;
use support\Response;

/**
 * 支付通道测试配置管理控制器
 */
class TestConfigController
{

    /**
     * 获取支持的渠道列表
     * @param Request $request
     * @return Response
     */
    public function getChannels(Request $request): Response
    {
        try {
            $channels = TestParamGenerator::getSupportedChannels();
            
            return success('获取成功', [
                'channels' => $channels,
                'total' => count($channels)
            ]);
        } catch (\Exception $e) {
            return error('获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取指定渠道的配置模板
     * @param Request $request
     * @return Response
     */
    public function getTemplate(Request $request): Response
    {
        try {
            $channelType = $request->input('channel_type');
            if (empty($channelType)) {
                return error('渠道类型不能为空');
            }

            $template = TestParamGenerator::getChannelTemplate($channelType);
            
            return success('获取成功', [
                'channel_type' => $channelType,
                'template' => $template
            ]);
        } catch (\Exception $e) {
            return error('获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 生成测试参数
     * @param Request $request
     * @return Response
     */
    public function generateParams(Request $request): Response
    {
        try {
            $channelType = $request->input('channel_type');
            $customParams = $request->input('custom_params', []);
            $orderNo = $request->input('order_no');

            if (empty($channelType)) {
                return error('渠道类型不能为空');
            }

            $params = TestParamGenerator::generate($channelType, $customParams, $orderNo);
            
            return success('生成成功', [
                'channel_type' => $channelType,
                'params' => $params,
                'order_no' => $orderNo
            ]);
        } catch (\Exception $e) {
            return error('生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 添加新的渠道配置
     * @param Request $request
     * @return Response
     */
    public function addChannel(Request $request): Response
    {
        try {
            $channelType = $request->input('channel_type');
            $config = $request->input('config', []);

            if (empty($channelType)) {
                return error('渠道类型不能为空');
            }

            if (empty($config)) {
                return error('配置不能为空');
            }

            $result = TestParamGenerator::addChannelConfig($channelType, $config);
            
            if ($result) {
                return success('添加成功', [
                    'channel_type' => $channelType,
                    'config' => $config
                ]);
            } else {
                return error('添加失败');
            }
        } catch (\Exception $e) {
            return error('添加失败: ' . $e->getMessage());
        }
    }
}


