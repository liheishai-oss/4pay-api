<?php

namespace app\service\thirdparty_payment;

class TestParamGenerator
{
    private static $config = null;
    
    /**
     * 获取测试配置
     * @return array
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/config/test_config.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            } else {
                self::$config = [];
            }
        }
        return self::$config;
    }
    
    /**
     * 生成测试参数
     * @param string $channelType 渠道类型
     * @param array $customParams 自定义参数（可选）
     * @param string $orderNo 订单号（可选，不传则自动生成）
     * @return array
     */
    public static function generate(string $channelType, array $customParams = [], string $orderNo = null): array
    {
        $config = self::getConfig();
        
        if (!isset($config[$channelType])) {
            throw new \InvalidArgumentException("不支持的渠道类型: {$channelType}");
        }
        
        $baseParams = $config[$channelType];
        $timestamp = time();
        
        // 处理动态参数
        $params = self::processDynamicParams($baseParams, $timestamp, $orderNo);
        
        // 合并自定义参数
        $params = array_merge($params, $customParams);
        
        return $params;
    }
    
    /**
     * 处理动态参数
     * @param array $params
     * @param int $timestamp
     * @param string|null $orderNo
     * @return array
     */
    private static function processDynamicParams(array $params, int $timestamp, ?string $orderNo = null): array
    {
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                // 替换时间戳占位符
                if (strpos($value, '{timestamp}') !== false) {
                    $params[$key] = str_replace('{timestamp}', $timestamp, $value);
                }
                
                // 替换当前时间戳占位符
                if (strpos($value, '{current_timestamp}') !== false) {
                    $params[$key] = str_replace('{current_timestamp}', $timestamp, $value);
                }
                
                // 替换订单号占位符
                if (strpos($value, '{order_no}') !== false) {
                    $params[$key] = str_replace('{order_no}', $orderNo ?: 'TEST_' . $timestamp, $value);
                }
            }
        }
        
        // 如果传入了订单号，替换相关的订单号字段
        if ($orderNo) {
            $orderFields = ['orderId', 'order_no', 'out_trade_no'];
            foreach ($orderFields as $field) {
                if (isset($params[$field])) {
                    $params[$field] = $orderNo;
                }
            }
        }
        
        return $params;
    }
    
    /**
     * 获取支持的渠道类型列表
     * @return array
     */
    public static function getSupportedChannels(): array
    {
        $config = self::getConfig();
        return array_keys($config);
    }
    
    /**
     * 获取指定渠道的配置模板
     * @param string $channelType
     * @return array
     */
    public static function getChannelTemplate(string $channelType): array
    {
        $config = self::getConfig();
        
        if (!isset($config[$channelType])) {
            throw new \InvalidArgumentException("不支持的渠道类型: {$channelType}");
        }
        
        return $config[$channelType];
    }
    
    /**
     * 添加新的渠道配置
     * @param string $channelType
     * @param array $config
     * @return bool
     */
    public static function addChannelConfig(string $channelType, array $config): bool
    {
        $configPath = __DIR__ . '/config/test_config.php';
        $allConfig = self::getConfig();
        $allConfig[$channelType] = $config;
        
        $content = "<?php\n\nreturn " . var_export($allConfig, true) . ";\n";
        
        return file_put_contents($configPath, $content) !== false;
    }
}


