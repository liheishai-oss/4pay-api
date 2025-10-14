<?php

namespace app\api\service\v1\order;

use support\Log;
use app\api\service\v1\order\TelegramAlertService;

/**
 * 企业级监控和日志服务
 * 提供完整的订单创建过程监控、性能统计和异常告警
 */
class EnterpriseMonitoringService
{
    private TelegramAlertService $telegramAlertService;
    private array $performanceMetrics = [];
    private array $validationResults = [];

    public function __construct(TelegramAlertService $telegramAlertService)
    {
        $this->telegramAlertService = $telegramAlertService;
    }

    /**
     * 开始监控订单创建过程
     * @param array $orderData
     * @param array $merchantInfo
     * @return string 返回监控ID
     */
    public function startOrderCreationMonitoring(array $orderData, array $merchantInfo): string
    {
        $monitorId = uniqid('order_monitor_', true);
        
        $this->performanceMetrics[$monitorId] = [
            'start_time' => microtime(true),
            'order_data' => $orderData,
            'merchant_info' => $merchantInfo,
            'steps' => [],
            'validation_results' => [],
            'channel_selections' => [],
            'errors' => []
        ];

        Log::info('开始订单创建监控', [
            'monitor_id' => $monitorId,
            'merchant_id' => $merchantInfo['id'],
            'merchant_order_no' => $orderData['merchant_order_no'],
            'order_amount_cents' => $orderData['order_amount_cents']
        ]);

        return $monitorId;
    }

    /**
     * 记录验证步骤
     * @param string $monitorId
     * @param string $step
     * @param bool $success
     * @param string|null $error
     * @param array $details
     */
    public function recordValidationStep(string $monitorId, string $step, bool $success, ?string $error = null, array $details = []): void
    {
        if (!isset($this->performanceMetrics[$monitorId])) {
            return;
        }

        $stepData = [
            'step' => $step,
            'success' => $success,
            'timestamp' => microtime(true),
            'error' => $error,
            'details' => $details
        ];

        $this->performanceMetrics[$monitorId]['steps'][] = $stepData;
        $this->performanceMetrics[$monitorId]['validation_results'][$step] = $stepData;

        if (!$success) {
            $this->performanceMetrics[$monitorId]['errors'][] = $stepData;
        }

        Log::info('验证步骤记录', [
            'monitor_id' => $monitorId,
            'step' => $step,
            'success' => $success,
            'error' => $error
        ]);
    }

    /**
     * 记录通道选择过程
     * @param string $monitorId
     * @param array $availableChannels
     * @param array $selectedChannel
     * @param string $strategy
     */
    public function recordChannelSelection(string $monitorId, array $availableChannels, array $selectedChannel, string $strategy): void
    {
        if (!isset($this->performanceMetrics[$monitorId])) {
            return;
        }

        $selectionData = [
            'timestamp' => microtime(true),
            'strategy' => $strategy,
            'available_channels_count' => count($availableChannels),
            'selected_channel' => $selectedChannel,
            'available_channels' => array_map(function($channel) {
                return [
                    'id' => $channel['id'],
                    'name' => $channel['name'],
                    'weight' => $channel['weight'],
                    'supplier_name' => $channel['supplier_name']
                ];
            }, $availableChannels)
        ];

        $this->performanceMetrics[$monitorId]['channel_selections'][] = $selectionData;

        Log::info('通道选择记录', [
            'monitor_id' => $monitorId,
            'strategy' => $strategy,
            'available_channels' => count($availableChannels),
            'selected_channel_id' => $selectedChannel['id'],
            'selected_channel_name' => $selectedChannel['name']
        ]);
    }

    /**
     * 记录支付执行过程
     * @param string $monitorId
     * @param array $channels
     * @param array $results
     * @param array $failedChannels
     */
    public function recordPaymentExecution(string $monitorId, array $channels, array $results, array $failedChannels = []): void
    {
        if (!isset($this->performanceMetrics[$monitorId])) {
            return;
        }

        $executionData = [
            'timestamp' => microtime(true),
            'total_channels' => count($channels),
            'successful_channels' => count($results),
            'failed_channels' => count($failedChannels),
            'results' => $results,
            'failed_channels' => $failedChannels
        ];

        $this->performanceMetrics[$monitorId]['payment_execution'] = $executionData;

        Log::info('支付执行记录', [
            'monitor_id' => $monitorId,
            'total_channels' => count($channels),
            'successful_channels' => count($results),
            'failed_channels' => count($failedChannels)
        ]);
    }

    /**
     * 完成监控并生成报告
     * @param string $monitorId
     * @param bool $success
     * @param array $orderInfo
     * @param string|null $error
     */
    public function completeMonitoring(string $monitorId, bool $success, array $orderInfo = [], ?string $error = null): void
    {
        if (!isset($this->performanceMetrics[$monitorId])) {
            return;
        }

        $endTime = microtime(true);
        $startTime = $this->performanceMetrics[$monitorId]['start_time'];
        $totalTime = $endTime - $startTime;

        $this->performanceMetrics[$monitorId]['end_time'] = $endTime;
        $this->performanceMetrics[$monitorId]['total_time'] = $totalTime;
        $this->performanceMetrics[$monitorId]['success'] = $success;
        $this->performanceMetrics[$monitorId]['order_info'] = $orderInfo;
        $this->performanceMetrics[$monitorId]['final_error'] = $error;

        // 生成性能报告
        $this->generatePerformanceReport($monitorId);

        // 发送告警（如果需要）
        if (!$success) {
            $this->sendFailureAlert($monitorId, $error);
        }

        Log::info('订单创建监控完成', [
            'monitor_id' => $monitorId,
            'success' => $success,
            'total_time' => round($totalTime, 3),
            'steps_count' => count($this->performanceMetrics[$monitorId]['steps']),
            'errors_count' => count($this->performanceMetrics[$monitorId]['errors'])
        ]);

        // 清理监控数据（可选，避免内存泄漏）
        unset($this->performanceMetrics[$monitorId]);
    }

    /**
     * 生成性能报告
     * @param string $monitorId
     */
    private function generatePerformanceReport(string $monitorId): void
    {
        $metrics = $this->performanceMetrics[$monitorId];
        
        $report = [
            'monitor_id' => $monitorId,
            'merchant_id' => $metrics['merchant_info']['id'],
            'merchant_order_no' => $metrics['order_data']['merchant_order_no'],
            'total_time' => round($metrics['total_time'], 3),
            'success' => $metrics['success'],
            'steps_summary' => $this->generateStepsSummary($metrics['steps']),
            'validation_summary' => $this->generateValidationSummary($metrics['validation_results']),
            'channel_selection_summary' => $this->generateChannelSelectionSummary($metrics['channel_selections']),
            'errors_summary' => $this->generateErrorsSummary($metrics['errors'])
        ];

        Log::info('性能报告生成', $report);
    }

    /**
     * 生成步骤摘要
     * @param array $steps
     * @return array
     */
    private function generateStepsSummary(array $steps): array
    {
        $summary = [
            'total_steps' => count($steps),
            'successful_steps' => 0,
            'failed_steps' => 0,
            'step_times' => []
        ];

        foreach ($steps as $step) {
            if ($step['success']) {
                $summary['successful_steps']++;
            } else {
                $summary['failed_steps']++;
            }
            
            $summary['step_times'][$step['step']] = round($step['timestamp'], 3);
        }

        return $summary;
    }

    /**
     * 生成验证摘要
     * @param array $validationResults
     * @return array
     */
    private function generateValidationSummary(array $validationResults): array
    {
        $summary = [
            'total_validations' => count($validationResults),
            'successful_validations' => 0,
            'failed_validations' => 0,
            'validation_details' => []
        ];

        foreach ($validationResults as $step => $result) {
            if ($result['success']) {
                $summary['successful_validations']++;
            } else {
                $summary['failed_validations']++;
            }
            
            $summary['validation_details'][$step] = [
                'success' => $result['success'],
                'error' => $result['error'] ?? null
            ];
        }

        return $summary;
    }

    /**
     * 生成通道选择摘要
     * @param array $channelSelections
     * @return array
     */
    private function generateChannelSelectionSummary(array $channelSelections): array
    {
        if (empty($channelSelections)) {
            return ['no_selections' => true];
        }

        $lastSelection = end($channelSelections);
        
        return [
            'total_selections' => count($channelSelections),
            'final_strategy' => $lastSelection['strategy'],
            'available_channels' => $lastSelection['available_channels_count'],
            'selected_channel' => $lastSelection['selected_channel']
        ];
    }

    /**
     * 生成错误摘要
     * @param array $errors
     * @return array
     */
    private function generateErrorsSummary(array $errors): array
    {
        if (empty($errors)) {
            return ['no_errors' => true];
        }

        $errorTypes = [];
        foreach ($errors as $error) {
            $step = $error['step'];
            if (!isset($errorTypes[$step])) {
                $errorTypes[$step] = 0;
            }
            $errorTypes[$step]++;
        }

        return [
            'total_errors' => count($errors),
            'error_types' => $errorTypes,
            'errors' => $errors
        ];
    }

    /**
     * 发送失败告警
     * @param string $monitorId
     * @param string|null $error
     */
    private function sendFailureAlert(string $monitorId, ?string $error): void
    {
        $metrics = $this->performanceMetrics[$monitorId];
        
        $alertData = [
            'monitor_id' => $monitorId,
            'merchant_info' => $metrics['merchant_info'],
            'order_data' => $metrics['order_data'],
            'error' => $error,
            'total_time' => round($metrics['total_time'], 3),
            'steps_count' => count($metrics['steps']),
            'errors_count' => count($metrics['errors'])
        ];

        // 这里可以调用TelegramAlertService发送告警
        Log::error('订单创建失败告警', $alertData);
    }

    /**
     * 获取监控统计信息
     * @return array
     */
    public function getMonitoringStatistics(): array
    {
        return [
            'active_monitors' => count($this->performanceMetrics),
            'monitor_ids' => array_keys($this->performanceMetrics)
        ];
    }
}
