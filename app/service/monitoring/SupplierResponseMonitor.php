<?php

namespace app\service\monitoring;

use support\Log;
use support\Redis;
use app\api\service\v1\order\TelegramAlertService;

/**
 * 供应商响应监控服务
 * 监控供应商响应时间和非正常响应，超过阈值时发送告警
 */
class SupplierResponseMonitor
{
    private TelegramAlertService $telegramAlertService;
    
    // 响应时间阈值（秒）
    private const RESPONSE_TIMEOUT_THRESHOLD = 2.0;
    
    // 告警冷却时间（分钟）
    private const ALERT_COOLDOWN = 5;
    
    // 非正常响应错误码阈值
    private const ERROR_RATE_THRESHOLD = 0.3; // 30%错误率
    
    public function __construct(TelegramAlertService $telegramAlertService)
    {
        $this->telegramAlertService = $telegramAlertService;
    }
    
    /**
     * 监控供应商响应时间
     * @param string $supplierName 供应商名称
     * @param string $channelName 通道名称
     * @param int $channelId 通道ID
     * @param float $responseTime 响应时间（秒）
     * @param string $orderNo 订单号
     * @param array $additionalData 额外数据
     */
    public function monitorResponseTime(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        float $responseTime, 
        string $orderNo = '',
        array $additionalData = [],
        ?string $traceId = null
    ): void {
        // 记录响应时间日志
        $this->logResponseTime($supplierName, $channelName, $channelId, $responseTime, $orderNo);
        
        // 检查是否超过阈值
        if ($responseTime > self::RESPONSE_TIMEOUT_THRESHOLD) {
            $this->handleSlowResponse($supplierName, $channelName, $channelId, $responseTime, $orderNo, $additionalData, $traceId);
        }
        
        // 更新统计数据
        $this->updateStatistics($supplierName, $channelName, $responseTime, true);
    }
    
    /**
     * 监控非正常响应
     * @param string $supplierName 供应商名称
     * @param string $channelName 通道名称
     * @param int $channelId 通道ID
     * @param string $errorType 错误类型
     * @param string $errorMessage 错误信息
     * @param int $httpStatus HTTP状态码
     * @param string $orderNo 订单号
     * @param array $additionalData 额外数据
     */
    public function monitorAbnormalResponse(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        string $errorType,
        string $errorMessage,
        int $httpStatus = 0,
        string $orderNo = '',
        array $additionalData = [],
        ?string $traceId = null
    ): void {
        // 记录非正常响应日志
        $this->logAbnormalResponse($supplierName, $channelName, $channelId, $errorType, $errorMessage, $httpStatus, $orderNo);
        
        // 注意：不再立即发送告警，等所有通道都失败后再统一发送
        // $this->handleAbnormalResponse($supplierName, $channelName, $channelId, $errorType, $errorMessage, $httpStatus, $orderNo, $additionalData, $traceId);
        
        // 更新错误统计
        $this->updateErrorStatistics($supplierName, $channelName, $errorType, $httpStatus);
    }
    
    /**
     * 记录响应时间日志
     */
    private function logResponseTime(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        float $responseTime, 
        string $orderNo
    ): void {
        $logData = [
            'supplier_name' => $supplierName,
            'channel_name' => $channelName,
            'channel_id' => $channelId,
            'response_time' => $responseTime,
            'order_no' => $orderNo,
            'threshold' => self::RESPONSE_TIMEOUT_THRESHOLD,
            'is_slow' => $responseTime > self::RESPONSE_TIMEOUT_THRESHOLD
        ];
        
        if ($responseTime > self::RESPONSE_TIMEOUT_THRESHOLD) {
            Log::warning('供应商响应超时', $logData);
        } else {
            Log::info('供应商响应正常', $logData);
        }
    }
    
    /**
     * 记录非正常响应日志
     */
    public function logAbnormalResponse(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        string $errorType,
        string $errorMessage,
        int $httpStatus,
        string $orderNo
    ): void {
        $logData = [
            'supplier_name' => $supplierName,
            'channel_name' => $channelName,
            'channel_id' => $channelId,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'http_status' => $httpStatus,
            'order_no' => $orderNo,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        Log::error('供应商非正常响应', $logData);
    }
    
    /**
     * 处理慢响应告警
     */
    private function handleSlowResponse(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        float $responseTime, 
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): void {
        // 检查告警冷却时间
        if ($this->isInCooldown($supplierName, $channelId, 'slow_response')) {
            return;
        }
        
        // 发送告警
        $this->sendSlowResponseAlert($supplierName, $channelName, $channelId, $responseTime, $orderNo, $additionalData, $traceId);
        
        // 设置冷却时间
        $this->setCooldown($supplierName, $channelId, 'slow_response');
    }
    
    /**
     * 处理非正常响应告警
     */
    private function handleAbnormalResponse(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        string $errorType,
        string $errorMessage,
        int $httpStatus,
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): void {
        // 所有供应商异常都立即发送告警，不受冷却时间限制
        $this->sendAbnormalResponseAlert($supplierName, $channelName, $channelId, $errorType, $errorMessage, $httpStatus, $orderNo, $additionalData, $traceId);
        
        // 设置冷却时间（用于统计，但不影响告警发送）
        $this->setCooldown($supplierName, $channelId, 'abnormal_response');
    }
    
    /**
     * 处理所有通道失败后的供应商非正常响应告警
     * @param array $failedChannels 失败的通道列表
     * @param string $orderNo 订单号
     * @param array $additionalData 额外数据
     * @param string|null $traceId 追踪ID
     */
    public function handleAllChannelsFailedAbnormalResponse(
        array $failedChannels,
        string $orderNo,
        array $additionalData = [],
        ?string $traceId = null
    ): void {
        // 按供应商分组失败的通道
        $supplierFailures = [];
        foreach ($failedChannels as $channel) {
            $supplierName = $channel['supplier_name'] ?? '未知供应商';
            if (!isset($supplierFailures[$supplierName])) {
                $supplierFailures[$supplierName] = [];
            }
            $supplierFailures[$supplierName][] = $channel;
        }
        
        // 为每个供应商发送非正常响应告警
        foreach ($supplierFailures as $supplierName => $channels) {
            $this->sendAllChannelsFailedAbnormalResponseAlert(
                $supplierName,
                $channels,
                $orderNo,
                $additionalData,
                $traceId
            );
        }
    }
    
    /**
     * 检查是否应该发送错误告警
     */
    private function shouldSendErrorAlert(string $supplierName, string $channelName): bool
    {
        $date = date('Y-m-d H:i');
        $supplierStats = $this->getSupplierStatistics($supplierName, $date);
        $channelStats = $this->getChannelStatistics($channelName, $date);
        
        // 如果总请求数少于10次，不发送告警
        if ($supplierStats['total_requests'] < 10 && $channelStats['total_requests'] < 10) {
            return false;
        }
        
        // 检查错误率是否超过阈值
        return $supplierStats['error_rate'] > self::ERROR_RATE_THRESHOLD || 
               $channelStats['error_rate'] > self::ERROR_RATE_THRESHOLD;
    }
    
    /**
     * 发送慢响应告警
     */
    private function sendSlowResponseAlert(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        float $responseTime, 
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): void {
        $message = $this->buildSlowResponseAlertMessage($supplierName, $channelName, $channelId, $responseTime, $orderNo, $additionalData, $traceId);
        
        try {
            $this->telegramAlertService->sendSlowResponseAlert($message, [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'channel_id' => $channelId,
                'response_time' => $responseTime,
                'order_no' => $orderNo,
                'threshold' => self::RESPONSE_TIMEOUT_THRESHOLD
            ]);
            
            Log::info('慢响应告警已发送', [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'response_time' => $responseTime
            ]);
            
        } catch (\Exception $e) {
            Log::error('发送慢响应告警失败', [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 发送非正常响应告警
     */
    private function sendAbnormalResponseAlert(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        string $errorType,
        string $errorMessage,
        int $httpStatus,
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): void {
        $message = $this->buildAbnormalResponseAlertMessage($supplierName, $channelName, $channelId, $errorType, $errorMessage, $httpStatus, $orderNo, $additionalData, $traceId);
        
        try {
            $this->telegramAlertService->sendAbnormalResponseAlert($message, [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'channel_id' => $channelId,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'http_status' => $httpStatus,
                'order_no' => $orderNo
            ]);
            
            Log::info('非正常响应告警已发送', [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'error_type' => $errorType
            ]);
            
        } catch (\Exception $e) {
            Log::error('发送非正常响应告警失败', [
                'supplier_name' => $supplierName,
                'channel_name' => $channelName,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 发送所有通道失败后的供应商非正常响应告警
     */
    private function sendAllChannelsFailedAbnormalResponseAlert(
        string $supplierName,
        array $channels,
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): void {
        $message = $this->buildAllChannelsFailedAbnormalResponseMessage($supplierName, $channels, $orderNo, $additionalData, $traceId);
        
        try {
            $this->telegramAlertService->sendAbnormalResponseAlert($message, [
                'supplier_name' => $supplierName,
                'order_no' => $orderNo,
                'failed_channels_count' => count($channels)
            ]);
            
            Log::info('所有通道失败后的供应商非正常响应告警已发送', [
                'supplier_name' => $supplierName,
                'failed_channels_count' => count($channels),
                'order_no' => $orderNo
            ]);
            
        } catch (\Exception $e) {
            Log::error('发送所有通道失败后的供应商非正常响应告警失败', [
                'supplier_name' => $supplierName,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 构建慢响应告警消息
     */
    private function buildSlowResponseAlertMessage(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        float $responseTime, 
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): string {
        $responseTimeFormatted = number_format($responseTime, 3);
        $thresholdFormatted = number_format(self::RESPONSE_TIMEOUT_THRESHOLD, 1);
        
        $message = "🚨 供应商响应超时告警\n\n";
        $message .= "📊 响应信息\n";
        $message .= "• 供应商: {$supplierName}\n";
        $message .= "• 通道: {$channelName}\n";
        $message .= "• 通道ID: {$channelId}\n";
        $message .= "• 响应时间: {$responseTimeFormatted}s\n";
        $message .= "• 阈值: {$thresholdFormatted}s\n";
        
        if (!empty($orderNo)) {
            $message .= "• 订单号: {$orderNo}\n";
        }
        
        if (!empty($traceId)) {
            $message .= "• 追踪ID: {$traceId}\n";
        }
        
        $message .= "\n⏰ 时间信息\n";
        $message .= "• 告警时间: " . date('Y-m-d H:i:s') . "\n";
        $message .= "• 超时倍数: " . number_format($responseTime / self::RESPONSE_TIMEOUT_THRESHOLD, 2) . "x\n";
        
        if (!empty($additionalData)) {
            $message .= "\n📋 额外信息\n";
            foreach ($additionalData as $key => $value) {
                $message .= "• {$key}: {$value}\n";
            }
        }
        
        $message .= "\n🔧 建议操作\n";
        $message .= "• 检查供应商服务状态\n";
        $message .= "• 检查网络连接\n";
        $message .= "• 考虑切换备用通道\n";
        
        return $message;
    }
    
    /**
     * 构建非正常响应告警消息
     */
    private function buildAbnormalResponseAlertMessage(
        string $supplierName, 
        string $channelName, 
        int $channelId, 
        string $errorType,
        string $errorMessage,
        int $httpStatus,
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): string {
        // 404错误使用特殊的告警标题
        if ($httpStatus === 404) {
            $message = "🚨 供应商接口404错误告警\n\n";
        } else {
            $message = "⚠️ 供应商非正常响应告警\n\n";
        }
        
        $message .= "📊 响应信息\n";
        $message .= "• 供应商: {$supplierName}\n";
        $message .= "• 通道: {$channelName}\n";
        $message .= "• 通道ID: {$channelId}\n";
        $message .= "• 错误类型: {$errorType}\n";
        $message .= "• 错误信息: " . str_replace(['*', '_', '`', '[', ']'], '', $errorMessage) . "\n";
        
        if ($httpStatus > 0) {
            $message .= "• HTTP状态码: {$httpStatus}\n";
        }
        
        // 添加请求地址信息
        if (!empty($additionalData['request_url'])) {
            $message .= "• 请求地址: {$additionalData['request_url']}\n";
        }
        
        if (!empty($orderNo)) {
            $message .= "• 订单号: {$orderNo}\n";
        }
        
        if (!empty($traceId)) {
            $message .= "• 追踪ID: {$traceId}\n";
        }
        
        $message .= "\n⏰ 时间信息\n";
        $message .= "• 告警时间: " . date('Y-m-d H:i:s') . "\n";
        
        // 添加错误率统计
        $date = date('Y-m-d H:i');
        $supplierStats = $this->getSupplierStatistics($supplierName, $date);
        $channelStats = $this->getChannelStatistics($channelName, $date);
        
        $message .= "\n📈 *错误率统计*\n";
        $message .= "• 供应商错误率: " . number_format($supplierStats['error_rate'], 2) . "%\n";
        $message .= "• 通道错误率: " . number_format($channelStats['error_rate'], 2) . "%\n";
        $message .= "• 阈值: " . number_format(self::ERROR_RATE_THRESHOLD * 100, 1) . "%\n";
        
        if (!empty($additionalData)) {
            $message .= "\n📋 额外信息\n";
            foreach ($additionalData as $key => $value) {
                $message .= "• {$key}: {$value}\n";
            }
        }
        
        $message .= "\n🔧 建议操作\n";
        $message .= "• 检查供应商API状态\n";
        $message .= "• 验证请求参数格式\n";
        $message .= "• 检查供应商配置\n";
        $message .= "• 考虑切换备用通道\n";
        
        return $message;
    }
    
    /**
     * 构建所有通道失败后的供应商非正常响应告警消息
     */
    private function buildAllChannelsFailedAbnormalResponseMessage(
        string $supplierName,
        array $channels,
        string $orderNo,
        array $additionalData,
        ?string $traceId = null
    ): string {
        $message = "🚨 供应商非正常响应告警（所有通道失败）\n\n";
        
        $message .= "📊 供应商信息\n";
        $message .= "• 供应商: {$supplierName}\n";
        $message .= "• 失败通道数: " . count($channels) . "\n";
        
        if (!empty($orderNo)) {
            $message .= "• 订单号: {$orderNo}\n";
        }
        
        if (!empty($traceId)) {
            $message .= "• 追踪ID: {$traceId}\n";
        }
        
        $message .= "\n📋 失败通道详情\n";
        foreach ($channels as $channel) {
            $channelName = $channel['name'] ?? '未知通道';
            $channelId = $channel['id'] ?? '未知ID';
            $error = $channel['error'] ?? '未知错误';
            $message .= "• {$channelName} (ID: {$channelId}): " . str_replace(['*', '_', '`', '[', ']'], '', $error) . "\n";
        }
        
        $message .= "\n⏰ 时间信息\n";
        $message .= "• 告警时间: " . date('Y-m-d H:i:s') . "\n";
        
        // 添加错误率统计
        $date = date('Y-m-d H:i');
        try {
            $supplierStats = $this->getSupplierStatistics($supplierName, $date);
            
            $message .= "\n📈 错误率统计\n";
            $message .= "• 供应商错误率: " . number_format($supplierStats['error_rate'], 2) . "%\n";
            $message .= "• 阈值: " . number_format(self::ERROR_RATE_THRESHOLD * 100, 1) . "%\n";
        } catch (\Exception $e) {
            $message .= "\n📈 错误率统计\n";
            $message .= "• 统计信息暂时不可用\n";
        }
        
        if (!empty($additionalData)) {
            $message .= "\n📋 额外信息\n";
            foreach ($additionalData as $key => $value) {
                $message .= "• {$key}: {$value}\n";
            }
        }
        
        $message .= "\n🔧 建议操作\n";
        $message .= "• 检查供应商服务状态\n";
        $message .= "• 验证供应商配置\n";
        $message .= "• 检查网络连接\n";
        $message .= "• 联系供应商技术支持\n";
        $message .= "• 考虑启用备用供应商\n";
        
        return $message;
    }
    
    /**
     * 检查是否在冷却时间内
     */
    private function isInCooldown(string $supplierName, int $channelId, string $alertType): bool
    {
        $key = "{$alertType}_alert_cooldown:{$supplierName}:{$channelId}";
        return Redis::exists($key);
    }
    
    /**
     * 设置冷却时间
     */
    private function setCooldown(string $supplierName, int $channelId, string $alertType): void
    {
        $key = "{$alertType}_alert_cooldown:{$supplierName}:{$channelId}";
        Redis::setEx($key, self::ALERT_COOLDOWN * 60, 1);
    }
    
    /**
     * 更新统计数据
     */
    private function updateStatistics(string $supplierName, string $channelName, float $responseTime, bool $isSuccess): void
    {
        $date = date('Y-m-d H:i');
        $supplierKey = "supplier_response_stats:{$supplierName}:{$date}";
        $channelKey = "channel_response_stats:{$channelName}:{$date}";
        
        // 更新供应商统计
        Redis::hIncrBy($supplierKey, 'total_requests', 1);
        Redis::hIncrByFloat($supplierKey, 'total_response_time', $responseTime);
        if (!$isSuccess) {
            Redis::hIncrBy($supplierKey, 'error_requests', 1);
        }
        Redis::expire($supplierKey, 3600); // 1小时过期
        
        // 更新通道统计
        Redis::hIncrBy($channelKey, 'total_requests', 1);
        Redis::hIncrByFloat($channelKey, 'total_response_time', $responseTime);
        if (!$isSuccess) {
            Redis::hIncrBy($channelKey, 'error_requests', 1);
        }
        Redis::expire($channelKey, 3600); // 1小时过期
        
        // 记录慢响应统计
        if ($responseTime > self::RESPONSE_TIMEOUT_THRESHOLD) {
            Redis::hIncrBy($supplierKey, 'slow_requests', 1);
            Redis::hIncrBy($channelKey, 'slow_requests', 1);
        }
    }
    
    /**
     * 更新错误统计
     */
    private function updateErrorStatistics(string $supplierName, string $channelName, string $errorType, int $httpStatus): void
    {
        $date = date('Y-m-d H:i');
        $supplierKey = "supplier_error_stats:{$supplierName}:{$date}";
        $channelKey = "channel_error_stats:{$channelName}:{$date}";
        
        // 更新供应商错误统计
        Redis::hIncrBy($supplierKey, 'total_errors', 1);
        Redis::hIncrBy($supplierKey, "error_type:{$errorType}", 1);
        if ($httpStatus > 0) {
            Redis::hIncrBy($supplierKey, "http_status:{$httpStatus}", 1);
        }
        Redis::expire($supplierKey, 3600); // 1小时过期
        
        // 更新通道错误统计
        Redis::hIncrBy($channelKey, 'total_errors', 1);
        Redis::hIncrBy($channelKey, "error_type:{$errorType}", 1);
        if ($httpStatus > 0) {
            Redis::hIncrBy($channelKey, "http_status:{$httpStatus}", 1);
        }
        Redis::expire($channelKey, 3600); // 1小时过期
    }
    
    /**
     * 获取供应商响应统计
     * @param string $supplierName
     * @param string $date 日期格式 Y-m-d H:i
     * @return array
     */
    public function getSupplierStatistics(string $supplierName, string $date = null): array
    {
        $date = $date ?: date('Y-m-d H:i');
        $key = "supplier_response_stats:{$supplierName}:{$date}";
        
        try {
            $stats = Redis::hGetAll($key);
        } catch (\Exception $e) {
            // Redis不可用时返回默认值
            return [
                'total_requests' => 0,
                'total_response_time' => 0,
                'slow_requests' => 0,
                'error_requests' => 0,
                'average_response_time' => 0,
                'slow_rate' => 0,
                'error_rate' => 0
            ];
        }
        
        if (empty($stats)) {
            return [
                'total_requests' => 0,
                'total_response_time' => 0,
                'slow_requests' => 0,
                'error_requests' => 0,
                'average_response_time' => 0,
                'slow_rate' => 0,
                'error_rate' => 0
            ];
        }
        
        $totalRequests = (int)($stats['total_requests'] ?? 0);
        $totalResponseTime = (float)($stats['total_response_time'] ?? 0);
        $slowRequests = (int)($stats['slow_requests'] ?? 0);
        $errorRequests = (int)($stats['error_requests'] ?? 0);
        
        return [
            'total_requests' => $totalRequests,
            'total_response_time' => $totalResponseTime,
            'slow_requests' => $slowRequests,
            'error_requests' => $errorRequests,
            'average_response_time' => $totalRequests > 0 ? $totalResponseTime / $totalRequests : 0,
            'slow_rate' => $totalRequests > 0 ? ($slowRequests / $totalRequests) * 100 : 0,
            'error_rate' => $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0
        ];
    }
    
    /**
     * 获取通道响应统计
     * @param string $channelName
     * @param string $date 日期格式 Y-m-d H:i
     * @return array
     */
    public function getChannelStatistics(string $channelName, string $date = null): array
    {
        $date = $date ?: date('Y-m-d H:i');
        $key = "channel_response_stats:{$channelName}:{$date}";
        
        $stats = Redis::hGetAll($key);
        
        if (empty($stats)) {
            return [
                'total_requests' => 0,
                'total_response_time' => 0,
                'slow_requests' => 0,
                'error_requests' => 0,
                'average_response_time' => 0,
                'slow_rate' => 0,
                'error_rate' => 0
            ];
        }
        
        $totalRequests = (int)($stats['total_requests'] ?? 0);
        $totalResponseTime = (float)($stats['total_response_time'] ?? 0);
        $slowRequests = (int)($stats['slow_requests'] ?? 0);
        $errorRequests = (int)($stats['error_requests'] ?? 0);
        
        return [
            'total_requests' => $totalRequests,
            'total_response_time' => $totalResponseTime,
            'slow_requests' => $slowRequests,
            'error_requests' => $errorRequests,
            'average_response_time' => $totalRequests > 0 ? $totalResponseTime / $totalRequests : 0,
            'slow_rate' => $totalRequests > 0 ? ($slowRequests / $totalRequests) * 100 : 0,
            'error_rate' => $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0
        ];
    }
    
    /**
     * 获取错误统计详情
     * @param string $supplierName
     * @param string $date 日期格式 Y-m-d H:i
     * @return array
     */
    public function getErrorStatistics(string $supplierName, string $date = null): array
    {
        $date = $date ?: date('Y-m-d H:i');
        $key = "supplier_error_stats:{$supplierName}:{$date}";
        
        $stats = Redis::hGetAll($key);
        
        $errorTypes = [];
        $httpStatuses = [];
        
        foreach ($stats as $field => $value) {
            if (strpos($field, 'error_type:') === 0) {
                $errorType = substr($field, 12);
                $errorTypes[$errorType] = (int)$value;
            } elseif (strpos($field, 'http_status:') === 0) {
                $status = substr($field, 13);
                $httpStatuses[$status] = (int)$value;
            }
        }
        
        return [
            'total_errors' => (int)($stats['total_errors'] ?? 0),
            'error_types' => $errorTypes,
            'http_statuses' => $httpStatuses
        ];
    }
}