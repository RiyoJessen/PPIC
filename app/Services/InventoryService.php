<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

class InventoryService
{
    public static function getMaterialStockSummary(?int $materialId = null): array
    {
        $where = $materialId ? "WHERE m.id = :material_id" : "";
        $params = $materialId ? ['material_id' => $materialId] : [];

        $sql = "
            SELECT 
                m.*,
                c.name as category_name,
                w.name as warehouse_name,
                w.code as warehouse_code,
                s.name as supplier_name,
                s.lead_time_days as supplier_lead_time,
                COALESCE((SELECT SUM(b.current_qty) FROM inventory_batches b WHERE b.material_id = m.id AND b.status = 'active'), 0) as physical_stock,
                COALESCE((SELECT SUM(b.allocated_qty) FROM inventory_batches b WHERE b.material_id = m.id AND b.status = 'active'), 0) as allocated_stock,
                COALESCE((
                    SELECT SUM(poi.quantity_ordered - poi.quantity_received)
                    FROM purchase_order_items poi
                    JOIN purchase_orders po ON po.id = poi.po_id
                    WHERE poi.material_id = m.id
                    AND po.status IN ('issued', 'partially_received')
                ), 0) as incoming_po_stock
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN warehouses w ON w.id = m.warehouse_id
            LEFT JOIN suppliers s ON s.id = m.preferred_supplier_id
            {$where}
            ORDER BY m.id ASC
        ";

        $rows = Database::select($sql, $params);

        foreach ($rows as &$item) {
            $physical = (float) $item['physical_stock'];
            $allocated = (float) $item['allocated_stock'];
            $available = max(0.0, $physical - $allocated);
            $safety = (float) $item['safety_stock'];
            $rop = (float) $item['reorder_point'];
            $incoming = (float) $item['incoming_po_stock'];

            $item['physical_stock'] = $physical;
            $item['allocated_stock'] = $allocated;
            $item['available_stock'] = $available;
            $item['incoming_po_stock'] = $incoming;

            // Status indicator: Kritis, Waspada, Aman (sesuai dokumen konsep hal. 5)
            if ($available <= $safety || $available < ($rop * 0.5)) {
                $item['stock_status'] = 'Kritis';
                $item['status_badge'] = 'danger';
                $item['status_note'] = 'Stok tersedia di bawah batas safety stock / minimum.';
            } elseif ($available <= $rop) {
                $item['stock_status'] = 'Waspada';
                $item['status_badge'] = 'warning text-dark';
                $item['status_note'] = 'Stok mendekati atau berada di bawah Reorder Point (ROP).';
            } else {
                $item['stock_status'] = 'Aman';
                $item['status_badge'] = 'success';
                $item['status_note'] = 'Stok masih mencukupi kebutuhan produksi.';
            }
        }

        return $materialId ? ($rows[0] ?? []) : $rows;
    }

    public static function getBatchesByMaterial(int $materialId): array
    {
        $sql = "
            SELECT b.*, w.name as warehouse_name
            FROM inventory_batches b
            JOIN warehouses w ON w.id = b.warehouse_id
            WHERE b.material_id = :mid
            ORDER BY b.expired_date ASC, b.id ASC
        ";
        return Database::select($sql, ['mid' => $materialId]);
    }

    public static function receivePurchaseOrder(int $poId, array $itemsData, int $userId): void
    {
        Database::beginTransaction();
        try {
            $po = Database::selectOne("SELECT * FROM purchase_orders WHERE id = :id", ['id' => $poId]);
            if (!$po) {
                throw new \RuntimeException("Purchase Order tidak ditemukan.");
            }

            foreach ($itemsData as $itemId => $data) {
                $qtyReceived = (float) ($data['qty'] ?? 0);
                if ($qtyReceived <= 0) continue;

                $poItem = Database::selectOne("SELECT * FROM purchase_order_items WHERE id = :id", ['id' => $itemId]);
                if (!$poItem) continue;

                $newTotalReceived = (float) $poItem['quantity_received'] + $qtyReceived;
                Database::update('purchase_order_items', [
                    'quantity_received' => $newTotalReceived
                ], 'id = :id', ['id' => $itemId]);

                $material = Database::selectOne("SELECT * FROM materials WHERE id = :id", ['id' => $poItem['material_id']]);
                $warehouseId = (int) ($data['warehouse_id'] ?? $material['warehouse_id'] ?? 1);
                $batchNo = trim((string) ($data['batch_number'] ?? ('LOT-' . date('ymd') . '-' . rand(100, 999))));
                $coaNo = trim((string) ($data['coa_number'] ?? ('COA-' . date('ymd') . '-' . rand(10, 99))));
                $expDate = $data['expired_date'] ?: date('Y-m-d', strtotime('+365 days'));

                // Create inventory batch
                $batchId = Database::insert('inventory_batches', [
                    'material_id' => $material['id'],
                    'warehouse_id' => $warehouseId,
                    'batch_number' => $batchNo,
                    'coa_number' => $coaNo,
                    'expired_date' => $expDate,
                    'initial_qty' => $qtyReceived,
                    'current_qty' => $qtyReceived,
                    'allocated_qty' => 0,
                    'status' => 'active',
                    'received_date' => date('Y-m-d'),
                ]);

                // Record stock movement
                Database::insert('stock_movements', [
                    'material_id' => $material['id'],
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $batchId,
                    'movement_type' => 'in_purchase',
                    'reference_type' => 'PO',
                    'reference_number' => $po['po_number'],
                    'quantity' => $qtyReceived,
                    'unit' => $material['unit'],
                    'before_stock' => 0,
                    'after_stock' => $qtyReceived,
                    'notes' => "Penerimaan barang PO {$po['po_number']} (Batch: {$batchNo}, COA: {$coaNo})",
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Check if PO is completely received
            $pendingItems = Database::selectOne("
                SELECT COUNT(*) as cnt 
                FROM purchase_order_items 
                WHERE po_id = :poid AND quantity_received < quantity_ordered
            ", ['poid' => $poId]);

            $newStatus = ((int) $pendingItems['cnt'] === 0) ? 'received' : 'partially_received';
            Database::update('purchase_orders', ['status' => $newStatus], 'id = :id', ['id' => $poId]);

            // Audit log
            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'RECEIVE_PO',
                'module' => 'Warehouse',
                'description' => "Penerimaan fisik barang untuk {$po['po_number']} dengan status {$newStatus}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
