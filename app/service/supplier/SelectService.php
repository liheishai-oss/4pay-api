<?php

namespace app\service\supplier;

use app\model\Supplier;

/**
 * 供应商选择服务
 * 用于支付通道添加/编辑时选择供应商
 */
class SelectService
{
    /**
     * 获取启用的供应商列表（用于选择）
     * @param array $params
     * @return array
     */
    public function getEnabledSuppliers(array $params = []): array
    {
        $keyword = $params['keyword'] ?? '';
        $limit = $params['limit'] ?? 50; // 限制返回数量，避免数据过多

        $query = Supplier::where('status', Supplier::STATUS_ENABLED);

        // 关键词搜索（供应商名称）
        if (!empty($keyword)) {
            $query->where('supplier_name', 'like', '%' . $keyword . '%');
        }

        // 只返回必要的字段，只查询未删除的供应商
        $suppliers = $query->active()
            ->select(['id', 'supplier_name', 'status', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        // 格式化返回数据
        $result = [];
        foreach ($suppliers as $supplier) {
            $result[] = [
                'id' => $supplier['id'],
                'supplier_name' => $supplier['supplier_name'],
                'status' => $supplier['status'],
                'status_text' => $supplier['status'] == Supplier::STATUS_ENABLED ? '启用' : '禁用',
                'created_at' => $supplier['created_at']
            ];
        }

        return $result;
    }

    /**
     * 获取所有供应商（包含禁用状态）
     * @param array $params
     * @return array
     */
    public function getAllSuppliers(array $params = []): array
    {
        $keyword = $params['keyword'] ?? '';
        $status = $params['status'] ?? '';
        $limit = $params['limit'] ?? 50;

        $query = Supplier::query();

        // 关键词搜索
        if (!empty($keyword)) {
            $query->where('supplier_name', 'like', '%' . $keyword . '%');
        }

        // 状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        $suppliers = $query->active()
            ->select(['id', 'supplier_name', 'status', 'created_at'])
            ->orderBy('status', 'desc') // 启用的在前
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        // 格式化返回数据
        $result = [];
        foreach ($suppliers as $supplier) {
            $result[] = [
                'id' => $supplier['id'],
                'name' => $supplier['supplier_name'],
            ];
        }

        return $result;
    }

    /**
     * 根据ID获取供应商信息
     * @param int $id
     * @return array|null
     */
    public function getSupplierById(int $id): ?array
    {
        $supplier = Supplier::select(['id', 'supplier_name', 'status', 'created_at'])
            ->find($id);

        if (!$supplier) {
            return null;
        }

        return [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'status' => $supplier->status,
            'status_text' => $supplier->status == Supplier::STATUS_ENABLED ? '启用' : '禁用',
            'created_at' => $supplier->created_at
        ];
    }

    /**
     * 获取供应商统计信息
     * @return array
     */
    public function getSupplierStats(): array
    {
        $total = Supplier::count();
        $enabled = Supplier::where('status', Supplier::STATUS_ENABLED)->count();
        $disabled = Supplier::where('status', Supplier::STATUS_DISABLED)->count();

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $disabled
        ];
    }
}


