<?php

namespace app\admin\controller\v1\robot\commands;

use app\admin\service\OrderManagementService;
use app\model\Order;
use app\model\Supplier;
use support\Log;

/**
 * 订单查询命令
 * 格式：/查单 订单号
 */
class OrderQueryCommand implements TelegramCommandInterface
{
    /**
     * 执行命令
     */
    public function execute(array $message): array
    {
        $messageText = isset($message['message_text']) ? $message['message_text'] : '';
        $groupId = isset($message['group_id']) ? $message['group_id'] : null;
        $groupName = isset($message['group_name']) ? $message['group_name'] : '';
        $senderId = isset($message['sender_id']) ? $message['sender_id'] : null;

        Log::info('执行订单查询命令', [
            'message_text' => $messageText,
            'group_id' => $groupId,
            'sender_id' => $senderId
        ]);

        try {
            // 解析订单号
            $orderNo = $this->parseOrderNo($messageText);
            if (!$orderNo) {
                return [
                    'success' => false,
                    'message' => '❌ 订单号格式错误，请使用：/查单 订单号',
                    'error_code' => 'INVALID_ORDER_NO'
                ];
            }

            // 校验订单权限
            $permissionResult = $this->checkOrderPermission($orderNo, $groupId);
            if (!$permissionResult['success']) {
                return $permissionResult;
            }

            // 执行查单
            $orderService = new OrderManagementService();
            $result = $orderService->queryOrder($orderNo);

            // 获取订单详细信息（用于显示通道名字和创建时间）
            $order = Order::where('order_no', $orderNo)
                         ->orWhere('third_party_order_no', $orderNo)
                         ->with(['channel', 'channel.supplier'])
                         ->first();

            // 判断查询者类型：如果订单属于当前群组绑定的供应商，则为供应商查询；否则为商户查询
            $isSupplierQuery = false;
            if ($permissionResult['success'] && isset($permissionResult['supplier'])) {
                $isSupplierQuery = true;
            }
            
            // 格式化返回结果
            $response = $this->formatQueryResult($result, $order, $isSupplierQuery);

            Log::info('订单查询成功', [
                'order_no' => $orderNo,
                'query_success' => $result['query_success'] ?? false
            ]);

            return [
                'success' => true,
                'message' => $response
            ];

        } catch (\Exception $e) {
            Log::error('订单查询失败', [
                'order_no' => $orderNo ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 根据不同的异常类型给出更友好的提示
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, '订单不存在') !== false) {
                $message = "❌ 订单不存在，请检查订单号是否正确";
            } elseif (strpos($errorMessage, '订单未关联支付通道') !== false) {
                $message = "❌ 订单未关联支付通道，无法查询";
            } else {
                $message = "❌ 查单失败：{$errorMessage}";
            }

            return [
                'success' => false,
                'message' => $message,
                'error_code' => 'QUERY_FAILED'
            ];
        }
    }

    /**
     * 解析订单号
     */
    private function parseOrderNo(string $messageText): ?string
    {
        // 匹配格式：/查单 订单号
        if (preg_match('/^\/查单\s+([A-Za-z0-9]+)$/', trim($messageText), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 校验订单权限
     * 检查订单是否属于当前群组绑定的供应商
     */
    private function checkOrderPermission(string $orderNo, ?int $groupId): array
    {
        try {
            // 查找订单（支持通过商户订单号或第三方订单号查询）
            $order = Order::where('order_no', $orderNo)
                         ->orWhere('third_party_order_no', $orderNo)
                         ->with(['channel.supplier'])
                         ->first();

            if (!$order) {
                return [
                    'success' => false,
                    'message' => "❌ 订单不存在\n\n订单号：{$orderNo}\n请检查订单号是否正确",
                    'error_code' => 'ORDER_NOT_FOUND'
                ];
            }

            // 检查订单是否有支付通道
            if (!$order->channel || !$order->channel->supplier) {
                return [
                    'success' => false,
                    'message' => '❌ 订单未关联供应商',
                    'error_code' => 'NO_SUPPLIER'
                ];
            }

            // 检查群组是否绑定了供应商
            if (!$groupId) {
                return [
                    'success' => false,
                    'message' => '❌ 无法获取群组信息',
                    'error_code' => 'NO_GROUP_ID'
                ];
            }

            // 查找绑定到当前群组的供应商
            $supplier = Supplier::where('telegram_chat_id', $groupId)->first();
            if (!$supplier) {
                return [
                    'success' => false,
                    'message' => '❌ 当前群组未绑定供应商',
                    'error_code' => 'NO_BOUND_SUPPLIER'
                ];
            }

            // 检查订单是否属于绑定的供应商
            if ($order->channel->supplier_id !== $supplier->id) {
                return [
                    'success' => false,
                    'message' => "❌ 订单不属于当前群组绑定的供应商（{$supplier->supplier_name}）",
                    'error_code' => 'ORDER_NOT_BELONG_TO_SUPPLIER'
                ];
            }

            Log::info('订单权限校验通过', [
                'order_no' => $orderNo,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'group_id' => $groupId
            ]);

            return [
                'success' => true,
                'supplier' => $supplier
            ];

        } catch (\Exception $e) {
            Log::error('订单权限校验异常', [
                'order_no' => $orderNo,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '❌ 权限校验异常',
                'error_code' => 'PERMISSION_CHECK_ERROR'
            ];
        }
    }

    /**
     * 格式化查询结果
     * @param array $result 查询结果
     * @param object|null $order 订单对象
     * @param bool $isSupplierQuery 是否为供应商查询
     */
    private function formatQueryResult(array $result, $order = null, bool $isSupplierQuery = false): string
    {
        $orderNo = $result['order_no'] ?? '';
        $status = $result['status'] ?? '';
        $amount = $result['amount'] ?? 0;
        $querySuccess = $result['query_success'] ?? false;
        $queryResult = $result['query_result'] ?? [];
        $queryMessage = $result['query_message'] ?? '';

        $response = "📋 订单查询结果\n\n";
        $response .= "🔢 订单号：{$orderNo}\n";
        $response .= "💰 金额：¥" . number_format($amount / 100, 2) . "\n";
        
        // 根据查询者类型显示不同的状态
        if ($isSupplierQuery) {
            // 供应商查询：优先显示第三方支付的实际状态
            if ($querySuccess && !empty($queryResult)) {
                $paymentStatus = $queryResult['payment_status'] ?? $queryResult['status'] ?? '';
                $paymentMessage = $queryResult['message'] ?? '';
                
                if ($paymentStatus) {
                    $response .= "📊 支付状态：{$this->getPaymentStatusText($paymentStatus)}\n";
                } else {
                    $response .= "📊 系统状态：{$this->getStatusText($status)}\n";
                }
                
                if ($paymentMessage) {
                    $response .= "💬 状态说明：{$paymentMessage}\n";
                }
            } else {
                $response .= "📊 系统状态：{$this->getStatusText($status)}\n";
            }
        } else {
            // 商户查询：显示数据库中的订单状态
            $response .= "📊 订单状态：{$this->getStatusText($status)}\n";
        }
        
        // 显示通道信息
        if ($order && $order->channel) {
            $response .= "🏦 支付通道：{$order->channel->channel_name}\n";
            $response .= "🏢 供应商：{$order->channel->supplier->supplier_name}\n";
        }
        
        // 显示创建时间
        if ($order && $order->created_at) {
            $response .= "📅 创建时间：" . $order->created_at->format('Y-m-d H:i:s') . "\n";
        }
        
        $response .= "\n";

        if ($querySuccess) {
            $response .= "✅ 查单成功\n";
        } else {
            $response .= "❌ 查单失败\n";
            $response .= "💬 错误信息：{$queryMessage}\n";
        }

        return $response;
    }

    /**
     * 获取系统状态文本
     */
    private function getStatusText(int $status): string
    {
        $statusMap = [
            1 => '待支付',
            2 => '支付中',
            3 => '支付成功',
            4 => '支付失败',
            5 => '已退款',
            6 => '已关闭'
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取支付状态文本
     */
    private function getPaymentStatusText($status): string
    {
        // 布尔状态（百亿支付等）
        if (is_bool($status)) {
            return $status ? '支付成功' : '支付失败';
        }
        
        // 数字状态
        if (is_numeric($status)) {
            $statusMap = [
                '1' => '支付成功',  // 根据海豚支付文档，1为支付成功
                '0' => '支付失败',  // 根据海豚支付文档，0为未支付，统一为支付失败
                '2' => '支付失败',  // 支付中状态统一为支付失败
                '3' => '支付成功',
                '4' => '支付失败'
            ];
            return $statusMap[$status] ?? "支付失败";
        }

        // 字符串状态
        $statusMap = [
            'SUCCESS' => '支付成功',
            'PAID' => '支付成功',
            'PENDING' => '支付失败',  // 待支付状态统一为支付失败
            'WAITING' => '支付失败',  // 等待状态统一为支付失败
            'FAILED' => '支付失败',
            'CANCELLED' => '支付失败',  // 取消状态统一为支付失败
            'EXPIRED' => '支付失败'     // 过期状态统一为支付失败
        ];

        return $statusMap[$status] ?? '支付失败';
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return 'order_query';
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return '/查单 订单号 - 查询订单状态';
    }

    /**
     * 检查消息是否匹配此命令
     */
    public function matches(string $messageText): bool
    {
        return preg_match('/^\/查单\s+[A-Za-z0-9]+$/', trim($messageText));
    }

    /**
     * 检查用户权限
     */
    public function hasPermission(array $message): bool
    {
        // 查单命令不限制权限，所有用户都可以使用
        return true;
    }
}
