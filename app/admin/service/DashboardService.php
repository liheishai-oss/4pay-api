<?php

namespace app\admin\service;

use app\model\Order;
use app\model\PaymentChannel;
use app\enums\OrderStatus;
use Carbon\Carbon;

class DashboardService
{
    /**
     * 获取今日统计数据
     */
    public function getTodayStats()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // 今日总订单数
        $todayTotalOrders = Order::whereBetween('created_at', [$today, $tomorrow])->count();
        
        // 今日支付成功订单数
        $todayPaidOrders = Order::whereBetween('created_at', [$today, $tomorrow])
                               ->where('status', OrderStatus::SUCCESS)
                               ->count();
        
        // 今日支付金额
        $todayPaidAmount = Order::whereBetween('created_at', [$today, $tomorrow])
                               ->where('status', OrderStatus::SUCCESS)
                               ->sum('amount');

        return [
            'today_total_orders' => $todayTotalOrders,
            'today_paid_orders' => $todayPaidOrders,
            'today_paid_amount' => round($todayPaidAmount / 100, 2) // 分转元
        ];
    }

    /**
     * 获取最近7天订单变化趋势
     */
    public function getOrderTrend()
    {
        $dates = [];
        $totalOrders = [];
        $paidOrders = [];

        // 获取最近7天的数据
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $nextDate = $date->copy()->addDay();
            
            $dates[] = $date->format('Y-m-d');
            
            // 当天总订单数
            $totalCount = Order::whereBetween('created_at', [$date, $nextDate])->count();
            $totalOrders[] = $totalCount;
            
            // 当天支付成功订单数
            $paidCount = Order::whereBetween('created_at', [$date, $nextDate])
                             ->where('status', OrderStatus::SUCCESS)
                             ->count();
            $paidOrders[] = $paidCount;
        }

        return [
            'dates' => $dates,
            'total' => $totalOrders,
            'paid' => $paidOrders
        ];
    }

    /**
     * 获取渠道订单排行榜
     */
    public function getChannelRanking()
    {
        // 获取最近30天的数据
        $startDate = Carbon::today()->subDays(30);
        $endDate = Carbon::tomorrow();

        $channelStats = Order::whereBetween('created_at', [$startDate, $endDate])
                           ->with('channel')
                           ->get()
                           ->groupBy('channel_id')
                           ->map(function ($orders, $channelId) {
                               $channel = $orders->first()->channel;
                               $totalOrders = $orders->count();
                               $successOrders = $orders->where('status', OrderStatus::SUCCESS)->count();
                               $successRate = $totalOrders > 0 ? round(($successOrders / $totalOrders) * 100, 2) : 0;
                               
                               return [
                                   'channel_id' => $channelId,
                                   'channel_name' => $channel ? $channel->channel_name : '未知渠道',
                                   'total_orders' => $totalOrders,
                                   'success_orders' => $successOrders,
                                   'success_rate' => $successRate
                               ];
                           })
                           ->sortByDesc('total_orders')
                           ->take(10)
                           ->values()
                           ->map(function ($item, $index) {
                               return [
                                   'rank' => $index + 1,
                                   'channel' => $item['channel_name'],
                                   'orders' => $item['total_orders'],
                                   'success_orders' => $item['success_orders'],
                                   'success_rate' => $item['success_rate']
                               ];
                           });

        return $channelStats->toArray();
    }

    /**
     * 获取综合仪表板数据
     */
    public function getDashboardData()
    {
        return [
            'today_stats' => $this->getTodayStats(),
            'order_trend' => $this->getOrderTrend(),
            'channel_ranking' => $this->getChannelRanking()
        ];
    }

    /**
     * 获取订单状态分布
     */
    public function getOrderStatusDistribution()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $statusDistribution = Order::whereBetween('created_at', [$today, $tomorrow])
                                   ->selectRaw('status, COUNT(*) as count')
                                   ->groupBy('status')
                                   ->get()
                                   ->mapWithKeys(function ($item) {
                                       return [$item->status => $item->count];
                                   });

        return [
            'pending' => $statusDistribution->get(OrderStatus::PENDING, 0),
            'paying' => $statusDistribution->get(OrderStatus::PAYING, 0),
            'success' => $statusDistribution->get(OrderStatus::SUCCESS, 0),
            'failed' => $statusDistribution->get(OrderStatus::FAILED, 0),
            'closed' => $statusDistribution->get(OrderStatus::CLOSED, 0),
            'refunded' => $statusDistribution->get(OrderStatus::REFUNDED, 0)
        ];
    }

    /**
     * 获取支付方式统计
     */
    public function getPaymentMethodStats()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $paymentMethodStats = Order::whereBetween('created_at', [$today, $tomorrow])
                                   ->where('status', OrderStatus::SUCCESS)
                                   ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total_amount')
                                   ->groupBy('payment_method')
                                   ->get()
                                   ->map(function ($item) {
                                       return [
                                           'payment_method' => $item->payment_method,
                                           'count' => $item->count,
                                           'total_amount' => round($item->total_amount / 100, 2) // 分转元
                                       ];
                                   });

        return $paymentMethodStats->toArray();
    }

    /**
     * 获取商户统计
     */
    public function getMerchantStats()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $merchantStats = Order::whereBetween('created_at', [$today, $tomorrow])
                             ->where('status', OrderStatus::SUCCESS)
                             ->with('merchant')
                             ->get()
                             ->groupBy('merchant_id')
                             ->map(function ($orders, $merchantId) {
                                 $merchant = $orders->first()->merchant;
                                 return [
                                     'merchant_id' => $merchantId,
                                     'merchant_name' => $merchant ? $merchant->merchant_name : '未知商户',
                                     'order_count' => $orders->count(),
                                     'total_amount' => round($orders->sum('amount') / 100, 2) // 分转元
                                 ];
                             })
                             ->sortByDesc('total_amount')
                             ->take(5)
                             ->values();

        return $merchantStats->toArray();
    }
}
