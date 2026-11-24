<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

class InventoryService
{
    public static function getMaterialStockSummary(?int $materialId = null): array
    {
        $where = $materialId ? 'WHERE m.id = :material_id' : '';
        $params = $materialId ? ['material_id' => $materialId] : [];

        $rows = Database::select("
            SELECT
                m.*,
                c.name as category_name,
                w.name as warehouse_name,
                w.code as warehouse_code,
                s.name as supplier_name,
                s.lead_time_days as supplier_lead_time,
                COALESCE((
                    SELECT SUM(b.current_qty)
                    FROM inventory_batches b
                    WHERE b.material_id = m.id AND b.status = 'active'
                ), 0) as physical_stock,
                COALESCE((
                    SELECT SUM(b.allocated_qty)
                    FROM inventory_batches b
                    WHERE b.material_id = m.id AND b.status = 'active'
                ), 0) as allocated_stock
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN warehouses w ON w.id = m.warehouse_id
            LEFT JOIN suppliers s ON s.id = m.preferred_supplier_id
            {$where}
            ORDER BY m.id ASC
        ", $params);

        foreach ($rows as &$item) {
            $physical = (float) $item['physical_stock'];
            $allocated = (float) $item['allocated_stock'];
            $available = max(0.0, $physical - $allocated);
            $safety = (float) $item['safety_stock'];
            $reorderPoint = (float) $item['reorder_point'];

            $item['physical_stock'] = $physical;
            $item['allocated_stock'] = $allocated;
            $item['available_stock'] = $available;

            if ($available <= $safety || $available < ($reorderPoint * 0.5)) {
                $item['stock_status'] = 'Kritis';
                $item['status_badge'] = 'danger';
                $item['status_note'] = 'Stok tersedia di bawah batas safety stock / minimum.';
            } elseif ($available <= $reorderPoint) {
                $item['stock_status'] = 'Waspada';
                $item['status_badge'] = 'warning text-dark';
                $item['status_note'] = 'Stok mendekati atau berada di bawah Reorder Point (ROP).';
            } else {
                $item['stock_status'] = 'Aman';
                $item['status_badge'] = 'success';
                $item['status_note'] = 'Stok masih mencukupi kebutuhan produksi.';
            }
        }
        unset($item);

        return $materialId ? ($rows[0] ?? []) : $rows;
    }

    public static function getBatchesByMaterial(int $materialId): array
    {
        return Database::select("
            SELECT b.*, w.name as warehouse_name
            FROM inventory_batches b
            JOIN warehouses w ON w.id = b.warehouse_id
            WHERE b.material_id = :material_id
            ORDER BY b.expired_date ASC, b.id ASC
        ", ['material_id' => $materialId]);
    }
}
