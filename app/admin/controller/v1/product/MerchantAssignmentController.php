<?php
namespace app\admin\controller\v1\product;

use app\model\Product;
use app\model\Merchant;
use app\model\ProductMerchant;
use app\common\helpers\MerchantCacheHelper;
use app\common\helpers\RelationCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Request;
use support\Response;
use support\DB;

class MerchantAssignmentController
{
    protected array $noNeedLogin = ['*'];

    /**
     * 获取产品的商户列表
     */
    public function getMerchantList(Request $request, int $product_id): Response
    {
        try {
            if (!$product_id) {
                return error('产品ID不能为空', 400);
            }

            $product = Product::find($product_id);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 获取所有商户
            $merchants = Merchant::where('is_deleted', 0)
                ->orderBy('status', 'desc')
                ->orderBy('merchant_name')
                ->get();

            // 获取已分配的商户
            $assignedMerchantIds = ProductMerchant::where('product_id', $product_id)
                ->where('status', ProductMerchant::STATUS_ENABLED)
                ->pluck('merchant_id')
                ->toArray();

            $merchantList = $merchants->map(function($merchant) use ($product, $assignedMerchantIds) {
                $isAssigned = in_array($merchant->id, $assignedMerchantIds);
                $merchantRateBp = $product->default_rate_bp;

                if ($isAssigned) {
                    $assignment = ProductMerchant::where('product_id', $product->id)
                        ->where('merchant_id', $merchant->id)
                        ->where('status', ProductMerchant::STATUS_ENABLED)
                        ->first();
                    if ($assignment) {
                        $merchantRateBp = $assignment->merchant_rate;
                    }
                }

                return [
                    'id' => $merchant->id,
                    'merchant_name' => $merchant->merchant_name,
                    'status' => $merchant->status,
                    'default_rate_bp' => $product->default_rate_bp,
                    'merchant_rate' => $merchantRateBp,
                    'is_assigned' => $isAssigned
                ];
            });

            $data = [
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'external_code' => $product->external_code,
                    'default_rate_bp' => $product->default_rate_bp
                ],
                'merchants' => $merchantList->toArray()
            ];

            return success($data, '获取商户列表成功');
        } catch (\Exception $e) {
            return error('获取商户列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 分配产品给商户
     */
    public function assignProductToMerchant(Request $request): Response
    {
        try {
            $data = $request->post();
            
            // 验证参数
            if (empty($data['product_id']) || empty($data['merchant_id'])) {
                return error('产品ID和商户ID不能为空', 400);
            }

            if (!isset($data['merchant_rate']) || $data['merchant_rate'] < 0) {
                return error('商户费率不能为空且不能小于0', 400);
            }

            $productId = $data['product_id'];
            $merchantId = $data['merchant_id'];
                $merchantRate = $data['merchant_rate'];

            // 验证产品是否存在
            $product = Product::find($productId);
            if (!$product) {
                return error('产品不存在', 404);
            }

            // 验证商户是否存在
            $merchant = Merchant::find($merchantId);
            if (!$merchant) {
                return error('商户不存在2', 404);
            }

            if ($merchant->is_deleted == 1) {
                return error('商户已被删除', 400);
            }

            if ($merchant->status != Merchant::STATUS_ENABLED) {
                return error('商户未启用，无法分配', 400);
            }

            DB::beginTransaction();
            try {
                // 检查是否已存在分配记录
                $existingAssignment = ProductMerchant::where('product_id', $productId)
                    ->where('merchant_id', $merchantId)
                    ->first();

                if ($existingAssignment) {
                    // 更新现有记录
                $existingAssignment->update([
                    'merchant_rate' => $merchantRate,
                    'status' => ProductMerchant::STATUS_ENABLED
                ]);
                } else {
                    // 创建新记录
                    ProductMerchant::create([
                        'product_id' => $productId,
                        'merchant_id' => $merchantId,
                        'merchant_rate' => $merchantRate,
                        'status' => ProductMerchant::STATUS_ENABLED
                    ]);
                }

                // 逐个清除商户相关缓存
                if (!empty($merchant->merchant_key)) {
                    MerchantCacheHelper::clearMerchantsCacheIndividually([$merchant->merchant_key]);
                }
                
                // 清除商户费率缓存
                RelationCacheHelper::clearMerchantRateCache($merchantId, $productId);
                
                // 清除可用通道列表缓存（因为产品关系变化可能影响通道选择）
                ChannelCacheHelper::clearAvailableChannelsCache($productId);

                DB::commit();
                return success([], '分配成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return error('分配失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新商户费率
     */
    public function updateMerchantRate(Request $request): Response
    {
        try {
            $data = $request->post();
            
            if (empty($data['product_id']) || empty($data['merchant_id'])) {
                return error('产品ID和商户ID不能为空', 400);
            }

            if (!isset($data['merchant_rate']) || $data['merchant_rate'] < 0) {
                return error('商户费率不能为空且不能小于0', 400);
            }

            $assignment = ProductMerchant::where('product_id', $data['product_id'])
                ->where('merchant_id', $data['merchant_id'])
                ->where('status', ProductMerchant::STATUS_ENABLED)
                ->first();

            if (!$assignment) {
                return error('分配记录不存在', 404);
            }

            $assignment->update([
                'merchant_rate' => $data['merchant_rate']
            ]);

            // 逐个清除商户相关缓存
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant && !empty($merchant->merchant_key)) {
                MerchantCacheHelper::clearMerchantsCacheIndividually([$merchant->merchant_key]);
            }
            
            // 清除商户费率缓存
            RelationCacheHelper::clearMerchantRateCache($data['merchant_id'], $data['product_id']);
            
            // 清除可用通道列表缓存
            ChannelCacheHelper::clearAvailableChannelsCache($data['product_id']);

            return success([], '费率更新成功');
        } catch (\Exception $e) {
            return error('费率更新失败: ' . $e->getMessage());
        }
    }

    /**
     * 移除产品分配
     */
    public function removeAssignment(Request $request): Response
    {
        try {
            $data = $request->post();
            
            if (empty($data['product_id']) || empty($data['merchant_id'])) {
                return error('产品ID和商户ID不能为空', 400);
            }

            $assignment = ProductMerchant::where('product_id', $data['product_id'])
                ->where('merchant_id', $data['merchant_id'])
                ->first();

            if (!$assignment) {
                return error('分配记录不存在', 404);
            }

            $assignment->update([
                'status' => ProductMerchant::STATUS_DISABLED
            ]);

            // 逐个清除商户相关缓存
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant && !empty($merchant->merchant_key)) {
                MerchantCacheHelper::clearMerchantsCacheIndividually([$merchant->merchant_key]);
            }
            
            // 清除商户费率缓存
            RelationCacheHelper::clearMerchantRateCache($data['merchant_id'], $data['product_id']);
            
            // 清除可用通道列表缓存
            ChannelCacheHelper::clearAvailableChannelsCache($data['product_id']);

            return success([], '移除分配成功');
        } catch (\Exception $e) {
            return error('移除分配失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取商户的产品列表
     */
    public function getMerchantProducts(Request $request, int $merchant_id): Response
    {
        try {
            if (!$merchant_id) {
                return error('商户ID不能为空', 400);
            }

            // 获取商户信息
            $merchant = Merchant::find($merchant_id);
            if (!$merchant) {
                return error('商户不存在3', 404);
            }

            // 获取分配给该商户的产品
            $assignments = ProductMerchant::where('merchant_id', $merchant_id)
                ->where('status', ProductMerchant::STATUS_ENABLED)
                ->with(['product'])
                ->get();

            $products = [];
            foreach ($assignments as $assignment) {
                if ($assignment->product) {
                    $products[] = [
                        'id' => $assignment->product->id,
                        'product_name' => $assignment->product->product_name,
                        'external_code' => $assignment->product->external_code,
                        'merchant_rate' => $assignment->merchant_rate,
                        'is_assigned' => true,
                        'assigned_at' => $assignment->created_at ? $assignment->created_at->format('Y-m-d H:i:s') : null
                    ];
                }
            }

            return success([
                'merchant' => [
                    'id' => $merchant->id,
                    'merchant_name' => $merchant->merchant_name
                ],
                'products' => $products
            ], '获取商户产品列表成功');

        } catch (\Exception $e) {
            \support\Log::error('获取商户产品列表失败: ' . $e->getMessage());
            return error('获取商户产品列表失败: ' . $e->getMessage(), 500);
        }
    }
}
