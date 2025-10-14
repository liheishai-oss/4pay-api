<?php

namespace app\api\service\v1\order;

use app\enums\MerchantStatus;
use app\model\Supplier;
use app\model\PaymentChannel;
use app\model\Product;
use app\model\ProductChannel;
use app\model\ProductMerchant;
use app\model\Merchant;
use app\exception\MyBusinessException;
use support\Log;
use Carbon\Carbon;

/**
 * 企业级状态验证服务
 * 严格验证供应商、通道、产品的开关状态
 */
class EnterpriseStatusValidator
{
    /**
     * 验证商户状态
     * @param array $merchant
     * @throws MyBusinessException
     */
    public function validateMerchantStatus(array $merchant): void
    {
        if (count($merchant) <= 0) {
            throw new MyBusinessException('商户不存在6');
        }
        if ($merchant['status'] != MerchantStatus::ENABLED) { // 假设商户状态常量
            Log::warning('商户状态异常', [
                'merchant_id' => $merchant['id'],
                'merchant_status' => $merchant['status'],
                'expected_status' => 1
            ]);
            throw new MyBusinessException('商户已禁用1');
        }
    }

    /**
     * 验证产品状态
     * @param int $productId
     * @return Product
     * @throws MyBusinessException
     */
    public function validateProductStatus(int $productId): Product
    {
        $product = Product::find($productId);
        if (!$product) {
            throw new MyBusinessException('产品不存在');
        }

        if ($product->status !== Product::STATUS_ENABLED) {
            Log::warning('产品状态异常', [
                'product_id' => $productId,
                'product_status' => $product->status,
                'expected_status' => Product::STATUS_ENABLED
            ]);
            throw new MyBusinessException('产品已禁用');
        }

        return $product;
    }

    /**
     * 验证商户产品关系状态
     * @param int $merchantId
     * @param int $productId
     * @return ProductMerchant
     * @throws MyBusinessException
     */
    public function validateProductMerchantStatus(int $merchantId, int $productId): ProductMerchant
    {
        $productMerchant = ProductMerchant::where('merchant_id', $merchantId)
            ->where('product_id', $productId)
            ->first();

        if (!$productMerchant) {
            Log::warning('商户产品关系不存在', [
                'merchant_id' => $merchantId,
                'product_id' => $productId
            ]);
            throw new MyBusinessException('商户未分配该产品');
        }

        if ($productMerchant->status !== ProductMerchant::STATUS_ENABLED) {
            Log::warning('商户产品关系状态异常', [
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'product_merchant_status' => $productMerchant->status,
                'expected_status' => ProductMerchant::STATUS_ENABLED
            ]);
            throw new MyBusinessException('商户产品关系已禁用');
        }

        return $productMerchant;
    }

    /**
     * 验证供应商状态
     * @param int $supplierId
     * @return Supplier
     * @throws MyBusinessException
     */
    public function validateSupplierStatus(int $supplierId): Supplier
    {
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }

        if ($supplier->status !== Supplier::STATUS_ENABLED) {
            Log::warning('供应商状态异常', [
                'supplier_id' => $supplierId,
                'supplier_status' => $supplier->status,
                'expected_status' => Supplier::STATUS_ENABLED
            ]);
            throw new MyBusinessException('供应商已禁用');
        }

        if ($supplier->is_deleted === Supplier::DELETED) {
            Log::warning('供应商已删除', [
                'supplier_id' => $supplierId,
                'is_deleted' => $supplier->is_deleted
            ]);
            throw new MyBusinessException('供应商已删除');
        }

        return $supplier;
    }

    /**
     * 验证支付通道状态
     * @param int $channelId
     * @return PaymentChannel
     * @throws MyBusinessException
     */
    public function validatePaymentChannelStatus(int $channelId): PaymentChannel
    {
        $channel = PaymentChannel::with('supplier')->find($channelId);
        if (!$channel) {
            throw new MyBusinessException('支付通道不存在');
        }

        if ($channel->status !== PaymentChannel::STATUS_ENABLED) {
            Log::warning('支付通道状态异常', [
                'channel_id' => $channelId,
                'channel_status' => $channel->status,
                'expected_status' => PaymentChannel::STATUS_ENABLED
            ]);
            throw new MyBusinessException('支付通道已禁用');
        }

        // 验证关联的供应商状态
        if ($channel->supplier) {
            $this->validateSupplierStatus($channel->supplier->id);
        } else {
            Log::warning('支付通道关联供应商不存在', [
                'channel_id' => $channelId,
                'supplier_id' => $channel->supplier_id
            ]);
            throw new MyBusinessException('支付通道关联供应商不存在');
        }

        return $channel;
    }

    /**
     * 验证产品通道关联状态
     * @param int $productId
     * @param int $channelId
     * @return object
     * @throws MyBusinessException
     */
    public function validateProductChannelStatus(int $productId, int $channelId): object
    {
        $productChannel = ProductChannel::where('product_id', $productId)
            ->where('channel_id', $channelId)
            ->first();

        if (!$productChannel) {
            throw new MyBusinessException('产品通道关联不存在');
        }

        if ($productChannel->status !== ProductChannel::STATUS_ENABLED) {
            Log::warning('产品通道关联状态异常', [
                'product_id' => $productId,
                'channel_id' => $channelId,
                'product_channel_status' => $productChannel->status,
                'expected_status' => ProductChannel::STATUS_ENABLED
            ]);
            throw new MyBusinessException('产品通道关联已禁用');
        }

        return $productChannel;
    }

    /**
     * 综合验证通道的完整状态链
     * @param int $productId
     * @param int $channelId
     * @return array 返回验证通过的完整通道信息
     * @throws MyBusinessException
     */
    public function validateCompleteChannelStatus(int $productId, int $channelId): array
    {
        // 1. 验证产品状态
        $product = $this->validateProductStatus($productId);

        // 2. 验证支付通道状态（包含供应商验证）
        $channel = $this->validatePaymentChannelStatus($channelId);

        // 3. 验证产品通道关联状态
        $productChannel = $this->validateProductChannelStatus($productId, $channelId);

        // 4. 构建完整的通道信息
        $channelInfo = [
            'id'                => $channel->id,
            'name'              => $channel->channel_name,
            'interface_code'    => $channel->interface_code,
            'product_code'      => $channel->product_code,
            'weight'            => $productChannel->weight ?? 0,
            'cost_rate'         => $channel->cost_rate,
            'min_amount'        => $productChannel->min_amount ?? $channel->min_amount ?? 0,
            'max_amount'        => $productChannel->max_amount ?? $channel->max_amount ?? 999999,
            'supplier_id'       => $channel->supplier->id,
            'supplier_name'     => $channel->supplier->supplier_name,
            'product_channel_id' => $productChannel->id,
            'basic_params'      => $channel->basic_params, // 添加基本参数
            'validation_status' => 'passed',
            'validated_at'      => Carbon::now()
        ];

        Log::info('通道状态验证通过', [
            'product_id'  => $productId,
            'channel_id'  => $channelId,
            'supplier_id' => $channel->supplier->id,
            'channel_info' => $channelInfo
        ]);

        return $channelInfo;
    }

    /**
     * 批量验证通道列表
     * @param int $productId
     * @param array $channelIds
     * @return array 返回所有验证通过的通道信息
     */
    public function validateChannelList(int $productId, array $channelIds): array
    {
        $validChannels = [];
        $failedChannels = [];

        foreach ($channelIds as $channelId) {
            try {
                $channelInfo = $this->validateCompleteChannelStatus($productId, $channelId);
                $validChannels[] = $channelInfo;
            } catch (MyBusinessException $e) {
                $failedChannels[] = [
                    'channel_id' => $channelId,
                    'error'      => $e->getMessage()
                ];
                Log::warning('通道验证失败', [
                    'product_id' => $productId,
                    'channel_id' => $channelId,
                    'error'      => $e->getMessage()
                ]);
            }
        }

        if (empty($validChannels)) {
            throw new MyBusinessException('没有可用的支付通道，所有通道状态验证失败');
        }

        Log::info('通道列表验证完成', [
            'product_id'      => $productId,
            'total_channels'  => count($channelIds),
            'valid_channels'  => count($validChannels),
            'failed_channels' => count($failedChannels),
            'failed_details'  => $failedChannels
        ]);

        return $validChannels;
    }

    /**
     * 获取产品可用的所有通道（带状态验证）
     * @param int $productId
     * @return array
     * @throws MyBusinessException
     */
    public function getValidatedChannelsForProduct(int $productId): array
    {
        // 1. 验证产品状态
//        $product = $this->validateProductStatus($productId);

        // 2. 获取产品的所有通道关联
        $productChannels = ProductChannel::where('product_id', $productId)
            ->where('status', ProductChannel::STATUS_ENABLED)
            ->with(['channel.supplier'])
            ->orderBy('weight', 'desc')
            ->orderBy('channel_id', 'asc')
            ->get();

        if ($productChannels->isEmpty()) {
            throw new MyBusinessException('产品没有配置任何通道');
        }

        // 3. 验证每个通道的完整状态
        $validChannels = [];
        foreach ($productChannels as $productChannel) {
            try {
                $channelInfo = $this->validateCompleteChannelStatus($productId, $productChannel->channel_id);
                $validChannels[] = $channelInfo;
            } catch (MyBusinessException $e) {
                Log::warning('产品通道验证失败', [
                    'product_id' => $productId,
                    'channel_id' => $productChannel->channel_id,
                    'error'      => $e->getMessage()
                ]);
            }
        }

        if (empty($validChannels)) {
            throw new MyBusinessException('产品没有可用的支付通道，所有通道状态验证失败');
        }

        return $validChannels;
    }
}
