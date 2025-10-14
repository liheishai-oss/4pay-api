<?php

namespace app\service\supplier;

use app\exception\MyBusinessException;
use app\model\Supplier;
use app\model\PaymentChannel;
use app\common\helpers\SupplierCacheHelper;
use app\common\helpers\ChannelCacheHelper;
use support\Db;

class SoftDeleteService
{
    /**
     * 软删除供应商
     * @param int $id
     * @param string $deletedBy
     * @return bool
     * @throws MyBusinessException
     */
    public function softDeleteSupplier(int $id, string $deletedBy = ''): bool
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }
        
        if ($supplier->is_deleted) {
            throw new MyBusinessException('供应商已被删除');
        }
        
        // 检查是否有活跃的通道
        $activeChannels = PaymentChannel::where('supplier_id', $id)
            ->where('status', 1)
            ->count();
            
        if ($activeChannels > 0) {
            throw new MyBusinessException('供应商存在活跃通道，无法删除');
        }
        
        try {
            Db::beginTransaction();
            
            // 执行软删除
            $supplier->is_deleted = 1;
            $supplier->deleted_at = now();
            $supplier->save();
            
            // 清除供应商相关缓存
            SupplierCacheHelper::clearSupplierAllCache($supplier->id);
            
            // 清除相关通道缓存
            $channels = PaymentChannel::where('supplier_id', $supplier->id)->get();
            foreach ($channels as $channel) {
                ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
            }
            
            // 记录操作日志
            $this->logOperation('soft_delete', $id, $deletedBy, '软删除供应商');
            
            Db::commit();
            
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('删除供应商失败：' . $e->getMessage());
        }
    }
    
    /**
     * 恢复供应商
     * @param int $id
     * @param string $restoredBy
     * @return bool
     * @throws MyBusinessException
     */
    public function restoreSupplier(int $id, string $restoredBy = ''): bool
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new MyBusinessException('供应商不存在');
        }
        
        if (!$supplier->is_deleted) {
            throw new MyBusinessException('供应商未被删除');
        }
        
        try {
            Db::beginTransaction();
            
            // 恢复供应商
            $supplier->is_deleted = 0;
            $supplier->deleted_at = null;
            $supplier->save();
            
            // 清除供应商相关缓存（恢复后需要重新缓存）
            SupplierCacheHelper::clearSupplierAllCache($supplier->id);
            
            // 清除相关通道缓存
            $channels = PaymentChannel::where('supplier_id', $supplier->id)->get();
            foreach ($channels as $channel) {
                ChannelCacheHelper::clearChannelAllCache($channel->id, null, $channel->product_code);
            }
            
            // 记录操作日志
            $this->logOperation('restore', $id, $restoredBy, '恢复供应商');
            
            Db::commit();
            
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('恢复供应商失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取已删除的供应商列表
     * @param array $params
     * @return array
     */
    public function getDeletedSuppliers(array $params = []): array
    {
        $query = Supplier::where('is_deleted', 1);
        
        if (isset($params['supplier_name'])) {
            $query->where('supplier_name', 'like', '%' . $params['supplier_name'] . '%');
        }
        
        if (isset($params['deleted_at_start'])) {
            $query->where('deleted_at', '>=', $params['deleted_at_start']);
        }
        
        if (isset($params['deleted_at_end'])) {
            $query->where('deleted_at', '<=', $params['deleted_at_end']);
        }
        
        $suppliers = $query->orderBy('deleted_at', 'desc')
            ->paginate($params['per_page'] ?? 10);
            
        return $suppliers->toArray();
    }
    
    /**
     * 检查数据一致性
     * @return array
     */
    public function checkDataConsistency(): array
    {
        $issues = [];
        
        // 检查孤立的通道
        $orphanedChannels = Db::table('fourth_party_payment_channel as c')
            ->leftJoin('fourth_party_payment_supplier as s', 'c.supplier_id', '=', 's.id')
            ->whereNull('s.id')
            ->orWhere('s.is_deleted', 1)
            ->select('c.id as channel_id', 'c.channel_name', 'c.supplier_id')
            ->get();
            
        foreach ($orphanedChannels as $channel) {
            $issues[] = [
                'type' => 'orphaned_channel',
                'channel_id' => $channel->channel_id,
                'channel_name' => $channel->channel_name,
                'supplier_id' => $channel->supplier_id,
                'message' => "通道 '{$channel->channel_name}' 引用了已删除的供应商 ID: {$channel->supplier_id}"
            ];
        }
        
        return $issues;
    }
    
    /**
     * 清理孤立数据
     * @return int
     */
    public function cleanupOrphanedData(): int
    {
        $count = 0;
        
        try {
            Db::beginTransaction();
            
            // 删除孤立的通道
            $deletedChannels = Db::table('fourth_party_payment_channel as c')
                ->leftJoin('fourth_party_payment_supplier as s', 'c.supplier_id', '=', 's.id')
                ->whereNull('s.id')
                ->orWhere('s.is_deleted', 1)
                ->delete();
                
            $count += $deletedChannels;
            
            Db::commit();
            
            return $count;
        } catch (\Exception $e) {
            Db::rollBack();
            throw new MyBusinessException('清理孤立数据失败：' . $e->getMessage());
        }
    }
    
    /**
     * 记录操作日志
     * @param string $action
     * @param int $targetId
     * @param string $operator
     * @param string $description
     */
    private function logOperation(string $action, int $targetId, string $operator, string $description): void
    {
        // 这里可以记录到操作日志表
        // OperationLog::create([
        //     'admin_id' => $operator,
        //     'module' => 'supplier',
        //     'action' => $action,
        //     'target_type' => 'supplier',
        //     'target_id' => $targetId,
        //     'description' => $description,
        //     'ip_address' => request()->getRealIp(),
        //     'user_agent' => request()->header('User-Agent')
        // ]);
    }
}

