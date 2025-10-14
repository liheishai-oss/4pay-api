<?php

namespace app\api\service\v1\order;

use app\exception\MyBusinessException;
use support\Log;

/**
 * 智能通道选择器
 * 支持多种选择策略：轮询、权重、随机、最少使用等
 */
class IntelligentChannelSelector
{
    private EnterpriseStatusValidator $statusValidator;

    public function __construct(EnterpriseStatusValidator $statusValidator)
    {
        $this->statusValidator = $statusValidator;
    }

    /**
     * 选择策略常量
     */
    const STRATEGY_ROUND_ROBIN = 'round_robin';      // 轮询
    const STRATEGY_WEIGHT = 'weight';                // 权重
    const STRATEGY_RANDOM = 'random';                // 随机
    const STRATEGY_LEAST_USED = 'least_used';        // 最少使用
    const STRATEGY_AMOUNT_OPTIMIZED = 'amount_optimized'; // 金额优化

    /**
     * 根据策略选择通道
     * @param int $productId
     * @param int $orderAmountCents
     * @param string $strategy
     * @return array
     * @throws MyBusinessException
     */
    public function selectChannel(int $productId, int $orderAmountCents, string $strategy = self::STRATEGY_WEIGHT): array
    {
        // 获取所有验证通过的通道
        $validChannels = $this->statusValidator->getValidatedChannelsForProduct($productId);

        if (empty($validChannels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }

        // 过滤金额范围内的通道
        $availableChannels = $this->filterChannelsByAmount($validChannels, $orderAmountCents);

        if (empty($availableChannels)) {
            throw new MyBusinessException('订单金额不在任何通道的限额范围内');
        }

        // 根据策略选择通道
        $selectedChannel = $this->applySelectionStrategy($availableChannels, $orderAmountCents, $strategy);

        Log::info('通道选择完成', [
            'product_id'           => $productId,
            'order_amount_cents'   => $orderAmountCents,
            'strategy'             => $strategy,
            'total_channels'       => count($validChannels),
            'available_channels'   => count($availableChannels),
            'selected_channel_id'  => $selectedChannel['id'],
            'selected_channel_name' => $selectedChannel['name']
        ]);

        return $selectedChannel;
    }

    /**
     * 过滤通道（按金额范围）
     * @param array $channels
     * @param int $orderAmountCents
     * @return array
     */
    private function filterChannelsByAmount(array $channels, int $orderAmountCents): array
    {
        return array_filter($channels, function($channel) use ($orderAmountCents) {
            // 检查最小金额限制
            if ($orderAmountCents < $channel['min_amount']) {
                return false;
            }
            
            // 检查最大金额限制：max_amount = 0 表示不限制上限
            if ($channel['max_amount'] > 0 && $orderAmountCents > $channel['max_amount']) {
                return false;
            }
            
            return true;
        });
    }

    /**
     * 应用选择策略
     * @param array $channels
     * @param int $orderAmountCents
     * @param string $strategy
     * @return array
     */
    private function applySelectionStrategy(array $channels, int $orderAmountCents, string $strategy): array
    {
        switch ($strategy) {
            case self::STRATEGY_ROUND_ROBIN:
                return $this->selectByRoundRobin($channels);
            case self::STRATEGY_WEIGHT:
                return $this->selectByWeight($channels);
            case self::STRATEGY_RANDOM:
                return $this->selectByRandom($channels);
            case self::STRATEGY_LEAST_USED:
                return $this->selectByLeastUsed($channels);
            case self::STRATEGY_AMOUNT_OPTIMIZED:
                return $this->selectByAmountOptimized($channels, $orderAmountCents);
            default:
                return $this->selectByWeight($channels);
        }
    }

    /**
     * 轮询选择
     * @param array $channels
     * @return array
     */
    private function selectByRoundRobin(array $channels): array
    {
        // 这里可以实现基于Redis的轮询计数器
        // 简化实现：随机选择一个
        return $channels[array_rand($channels)];
    }

    /**
     * 权重选择
     * @param array $channels
     * @return array
     */
    private function selectByWeight(array $channels): array
    {
        if (empty($channels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }
        
        // 按权重排序
        usort($channels, function($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });

        // 权重选择算法
        $totalWeight = array_sum(array_column($channels, 'weight'));
        if ($totalWeight <= 0) {
            return $channels[0];
        }

        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($channels as $channel) {
            $currentWeight += $channel['weight'];
            if ($random <= $currentWeight) {
                return $channel;
            }
        }

        return $channels[0];
    }

    /**
     * 随机选择
     * @param array $channels
     * @return array
     */
    private function selectByRandom(array $channels): array
    {
        if (empty($channels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }
        return $channels[array_rand($channels)];
    }

    /**
     * 最少使用选择
     * @param array $channels
     * @return array
     */
    private function selectByLeastUsed(array $channels): array
    {
        if (empty($channels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }
        
        // 这里应该查询数据库获取每个通道的使用次数
        // 简化实现：按权重排序，选择权重最高的
        usort($channels, function($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });

        return $channels[0];
    }

    /**
     * 金额优化选择
     * @param array $channels
     * @param int $orderAmountCents
     * @return array
     */
    private function selectByAmountOptimized(array $channels, int $orderAmountCents): array
    {
        if (empty($channels)) {
            throw new MyBusinessException('没有可用的支付通道');
        }
        
        // 选择费率最低的通道
        usort($channels, function($a, $b) {
            return $a['cost_rate'] <=> $b['cost_rate'];
        });

        return $channels[0];
    }

    /**
     * 获取所有可用通道（用于降级）
     * @param int $productId
     * @param int $orderAmountCents
     * @return array
     * @throws MyBusinessException
     */
    public function getAllAvailableChannels(int $productId, int $orderAmountCents): array
    {
        $validChannels = $this->statusValidator->getValidatedChannelsForProduct($productId);
        $availableChannels = $this->filterChannelsByAmount($validChannels, $orderAmountCents);

        // 按权重排序
        usort($availableChannels, function($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });

        return $availableChannels;
    }



    /**
     * 获取通道统计信息
     * @param int $productId
     * @return array
     */
    public function getChannelStatistics(int $productId): array
    {
        try {
            $validChannels = $this->statusValidator->getValidatedChannelsForProduct($productId);
            
            return [
                'total_channels' => count($validChannels),
                'channels' => array_map(function($channel) {
            return [
                'id'            => $channel['id'],
                'name'          => $channel['name'],
                'weight'        => $channel['weight'],
                'cost_rate'     => $channel['cost_rate'],
                'min_amount'    => $channel['min_amount'],
                'max_amount'    => $channel['max_amount'],
                'supplier_name' => $channel['supplier_name']
            ];
                }, $validChannels)
            ];
        } catch (MyBusinessException $e) {
            return [
                'total_channels' => 0,
                'channels'       => [],
                'error'          => $e->getMessage()
            ];
        }
    }
}
