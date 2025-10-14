<?php

namespace app\admin\controller\v1\product;

use app\model\Product;
use app\model\PaymentChannel;
use app\model\Supplier;
use app\model\ProductChannel;
use app\common\helpers\ProductCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Request;
use support\Response;
use support\DB;

class PoolController
{

    /**
     * 获取轮询池列表
     * @param Request $request
     * @return Response
     */
    public function getPoolList(Request $request): Response
    {
        try {
            $productId = $request->get('product_id');
            
            if (!$productId) {
                return error('产品ID不能为空', 400);
            }

            // 获取产品信息
            $product = Product::find($productId);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 获取已分配的通道ID
            $assignedChannelIds = ProductChannel::where('product_id', $productId)
                ->where('status', ProductChannel::STATUS_ENABLED)
                ->pluck('channel_id')
                ->toArray();

            // 获取所有未删除且未分配的通道，关联供应商信息（包括未启用的通道）
            $allChannels = PaymentChannel::with('supplier')
                ->whereHas('supplier', function($query) {
                    $query->where('is_deleted', 0); // 只过滤已删除的供应商
                })
                ->whereNotIn('id', $assignedChannelIds) // 排除已分配的通道
                ->orderBy('status', 'desc') // 启用的通道排在前面
                ->orderBy('weight', 'desc') // 权重越高的通道越优先
                ->orderBy('supplier_id')
                ->orderBy('channel_name')
                ->get()
                ->map(function($channel) {
                    $supplierName = $channel->supplier->supplier_name ?? '未知供应商';
                    $displayName = $supplierName . '-' . $channel->channel_name . '-' . $channel->cost_rate . '%';
                    
                    return [
                        'key' => $channel->id,
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->channel_name,
                        'cost_rate' => $channel->cost_rate,
                        'weight' => $channel->weight,
                        'min_amount' => $channel->min_amount,
                        'max_amount' => $channel->max_amount,
                        'interface_code' => $channel->interface_code,
                        'supplier_name' => $supplierName,
                        'display_name' => $displayName,
                        'status' => $channel->status,
                        'is_enabled' => $channel->status == PaymentChannel::STATUS_ENABLED
                    ];
                })
                ->toArray();

            // 获取已分配的通道
            $assignedChannels = [];
            if (!empty($assignedChannelIds)) {
                $assignedChannels = PaymentChannel::with('supplier')
                    ->whereIn('id', $assignedChannelIds)
                    ->whereHas('supplier', function($query) {
                        $query->where('is_deleted', 0);
                    })
                    ->orderBy('status', 'desc') // 启用的通道排在前面
                    ->orderBy('weight', 'desc') // 权重越高的通道越优先
                    ->orderBy('supplier_id')
                    ->orderBy('channel_name')
                    ->get()
                    ->map(function($channel) {
                        $supplierName = $channel->supplier->supplier_name ?? '未知供应商';
                        $displayName = $supplierName . '-' . $channel->channel_name . '-' . $channel->cost_rate . '%';
                        
                        return [
                            'key' => $channel->id,
                            'channel_id' => $channel->id,
                            'channel_name' => $channel->channel_name,
                            'cost_rate' => $channel->cost_rate,
                            'weight' => $channel->weight,
                            'min_amount' => $channel->min_amount,
                            'max_amount' => $channel->max_amount,
                            'interface_code' => $channel->interface_code,
                            'supplier_name' => $supplierName,
                            'display_name' => $displayName,
                            'status' => $channel->status,
                            'is_enabled' => $channel->status == PaymentChannel::STATUS_ENABLED
                        ];
                    })
                    ->toArray();
            }

            $data = [
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'external_code' => $product->external_code
                ],
                'available_channels' => $allChannels,
                'assigned_channels' => $assignedChannels
            ];

            // 添加调试信息
            \support\Log::info('轮询池查询结果', [
                'product_id' => $productId,
                'channels_count' => count($allChannels),
                'channels' => array_map(function($channel) {
                    return [
                        'id' => $channel['channel_id'],
                        'name' => $channel['display_name'],
                        'supplier' => $channel['supplier_name']
                    ];
                }, $allChannels)
            ]);

            return success($data, '获取轮询池数据成功');
        } catch (\Exception $e) {
            return error('获取轮询池数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 分配通道到轮询池
     * @param Request $request
     * @return Response
     */
    public function assignToPool(Request $request): Response
    {
        try {
            $params = $request->all();
            $productId = $params['product_id'] ?? null;
            $channelIds = $params['channel_ids'] ?? [];

            if (!$productId) {
                return error('产品ID不能为空', 400);
            }

            if (empty($channelIds)) {
                return error('通道ID不能为空', 400);
            }

            // 验证产品是否存在
            $product = Product::find($productId);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 验证通道是否存在且有效
            $validChannels = PaymentChannel::whereIn('id', $channelIds)
                ->where('status', PaymentChannel::STATUS_ENABLED)
                ->whereHas('supplier', function($query) {
                    $query->where('is_deleted', 0);
                })
                ->pluck('id')
                ->toArray();

            if (empty($validChannels)) {
                return error('没有有效的通道可以分配', 400);
            }

            // 使用事务处理分配逻辑
            DB::beginTransaction();
            try {
                $assignedCount = 0;
                foreach ($validChannels as $channelId) {
                    // 检查是否已经分配
                    $existing = ProductChannel::where('product_id', $productId)
                        ->where('channel_id', $channelId)
                        ->first();
                    
                    if (!$existing) {
                        // 创建新的分配关系
                        ProductChannel::create([
                            'product_id' => $productId,
                            'channel_id' => $channelId,
                            'status' => ProductChannel::STATUS_ENABLED
                        ]);
                        $assignedCount++;
                    } else {
                        // 如果存在但状态为禁用，则启用
                        if ($existing->status == ProductChannel::STATUS_DISABLED) {
                            $existing->update(['status' => ProductChannel::STATUS_ENABLED]);
                            $assignedCount++;
                        }
                    }
                }

                // 清除产品相关缓存
                $product = Product::find($productId);
                if ($product) {
                    ProductCacheHelper::clearProductAllCache($product->external_code);
                }
                
                // 清除可用通道列表缓存
                ChannelCacheHelper::clearAvailableChannelsCache($productId);

                DB::commit();
                
                \support\Log::info('通道分配到轮询池', [
                    'product_id' => $productId,
                    'channel_ids' => $validChannels,
                    'assigned_count' => $assignedCount
                ]);

                return success(['assigned_count' => $assignedCount], '分配成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return error('分配失败: ' . $e->getMessage());
        }
    }

    /**
     * 从轮询池移除通道
     * @param Request $request
     * @return Response
     */
    public function removeFromPool(Request $request): Response
    {
        try {
            $params = $request->all();
            $productId = $params['product_id'] ?? null;
            $channelIds = $params['channel_ids'] ?? [];

            if (!$productId) {
                return error('产品ID不能为空', 400);
            }

            if (empty($channelIds)) {
                return error('通道ID不能为空', 400);
            }

            // 验证产品是否存在
            $product = Product::find($productId);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 使用事务处理移除逻辑
            DB::beginTransaction();
            try {
                $removedCount = 0;
                foreach ($channelIds as $channelId) {
                    // 查找分配关系
                    $existing = ProductChannel::where('product_id', $productId)
                        ->where('channel_id', $channelId)
                        ->first();
                    
                    if ($existing) {
                        // 软删除：将状态设置为禁用
                        $existing->update(['status' => ProductChannel::STATUS_DISABLED]);
                        $removedCount++;
                    }
                }

                // 清除产品相关缓存
                $product = Product::find($productId);
                if ($product) {
                    ProductCacheHelper::clearProductAllCache($product->external_code);
                }
                
                // 清除可用通道列表缓存
                ChannelCacheHelper::clearAvailableChannelsCache($productId);

                DB::commit();
                
                \support\Log::info('通道从轮询池移除', [
                    'product_id' => $productId,
                    'channel_ids' => $channelIds,
                    'removed_count' => $removedCount
                ]);

                return success(['removed_count' => $removedCount], '移除成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return error('移除失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量更新轮询池
     * @param Request $request
     * @return Response
     */
    public function updatePool(Request $request): Response
    {
        try {
            $params = $request->all();
            $productId = $params['product_id'] ?? null;
            $channelIds = $params['channel_ids'] ?? [];

            if (!$productId) {
                return error('产品ID不能为空', 400);
            }

            // 验证产品是否存在
            $product = Product::find($productId);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 使用事务处理批量更新逻辑
            DB::beginTransaction();
            try {
                // 获取当前已分配的通道ID
                $currentChannelIds = ProductChannel::where('product_id', $productId)
                    ->where('status', ProductChannel::STATUS_ENABLED)
                    ->pluck('channel_id')
                    ->toArray();

                // 计算需要添加和移除的通道
                $toAdd = array_diff($channelIds, $currentChannelIds);
                $toRemove = array_diff($currentChannelIds, $channelIds);

                $addedCount = 0;
                $removedCount = 0;

                // 添加新通道
                if (!empty($toAdd)) {
                    // 验证通道是否有效
                    $validChannels = PaymentChannel::whereIn('id', $toAdd)
                        ->where('status', PaymentChannel::STATUS_ENABLED)
                        ->whereHas('supplier', function($query) {
                            $query->where('is_deleted', 0);
                        })
                        ->pluck('id')
                        ->toArray();

                    foreach ($validChannels as $channelId) {
                        // 检查是否已经存在（可能是禁用状态）
                        $existing = ProductChannel::where('product_id', $productId)
                            ->where('channel_id', $channelId)
                            ->first();
                        
                        if (!$existing) {
                            // 创建新的分配关系
                            ProductChannel::create([
                                'product_id' => $productId,
                                'channel_id' => $channelId,
                                'status' => ProductChannel::STATUS_ENABLED
                            ]);
                        } else {
                            // 启用已存在的分配关系
                            $existing->update(['status' => ProductChannel::STATUS_ENABLED]);
                        }
                        $addedCount++;
                    }
                }

                // 移除通道
                if (!empty($toRemove)) {
                    ProductChannel::where('product_id', $productId)
                        ->whereIn('channel_id', $toRemove)
                        ->update(['status' => ProductChannel::STATUS_DISABLED]);
                    $removedCount = count($toRemove);
                }

                // 清除产品相关缓存
                $product = Product::find($productId);
                if ($product) {
                    ProductCacheHelper::clearProductAllCache($product->external_code);
                }
                
                // 清除可用通道列表缓存
                ChannelCacheHelper::clearAvailableChannelsCache($productId);

                DB::commit();
                
                \support\Log::info('轮询池批量更新', [
                    'product_id' => $productId,
                    'new_channel_ids' => $channelIds,
                    'added_count' => $addedCount,
                    'removed_count' => $removedCount
                ]);

                return success([
                    'added_count' => $addedCount,
                    'removed_count' => $removedCount,
                    'total_channels' => count($channelIds)
                ], '轮询池更新成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return error('轮询池更新失败: ' . $e->getMessage());
        }
    }
}
