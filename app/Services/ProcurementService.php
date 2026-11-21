<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use RuntimeException;

class ProcurementService
{
    public static function getHistoricalConsumptionStats(int $materialId, int $lookbackDays = 90): array
    {
        $driver = \App\Config\AppConfig::getDbDriver();
        $dateCond = ($driver === 'mysql')
            ? "created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"
            : "created_at >= datetime('now', '-' || :days || ' days')";

        $monthFormat = ($driver === 'mysql') ? "DATE_FORMAT(created_at, '%Y-%m')" : "strftime('%Y-%m', created_at)";

        $rows = Database::select("
            SELECT 
                {$monthFormat} as period_month,
                SUM(quantity) as total_qty,
                COUNT(*) as transaction_count
            FROM stock_movements
            WHERE material_id = :mid
              AND movement_type IN ('out_production', 'out_transfer', 'out_adjustment')
              AND {$dateCond}
            GROUP BY {$monthFormat}
            ORDER BY period_month ASC
        ", ['mid' => $materialId, 'days' => $lookbackDays]);

        $totalUsed = 0.0;
        foreach ($rows as $r) {
            $totalUsed += (float) $r['total_qty'];
        }

        $dailyAverage = ($lookbackDays > 0 && $totalUsed > 0) ? round($totalUsed / $lookbackDays, 2) : 0.0;

        return [
            'periods' => $rows,
            'total_used' => round($totalUsed, 2),
            'lookback_days' => $lookbackDays,
            'daily_average' => $dailyAverage,
            'has_history' => ($totalUsed > 0),
        ];
    }

    public static function calculateRecommendation(int $materialId, int $periodDays = 30, int $lookbackDays = 90): array
    {
        $summary = InventoryService::getMaterialStockSummary($materialId);
        if (empty($summary)) {
            throw new RuntimeException("Material tidak ditemukan.");
        }

        $availableStock = (float) $summary['available_stock'];
        $safetyStock = (float) $summary['safety_stock'];
        $incomingPo = (float) $summary['incoming_po_stock'];
        $moq = (float) $summary['moq'];
        $orderMultiple = (float) $summary['order_multiple'] ?: 1.0;
        $leadTime = (int) $summary['lead_time_days'];

        // 1. Hitung kebutuhan dari riwayat data konsumsi riil bulan-bulan sebelumnya
        $histStats = self::getHistoricalConsumptionStats($materialId, $lookbackDays);

        if ($histStats['has_history'] && $histStats['daily_average'] > 0) {
            $dailyUsage = $histStats['daily_average'];
            $usageSource = "Historis Riil ({$histStats['lookback_days']} Hari Terakhir)";
        } else {
            // Fallback rasio teoritis ROP & Safety Stock jika belum ada riwayat transaksi
            $dailyUsage = ($summary['reorder_point'] - $safetyStock) > 0 && $leadTime > 0
                ? (($summary['reorder_point'] - $safetyStock) / $leadTime)
                : ($safetyStock / 10);
            $usageSource = "Estimasi Model ROP & Safety Stock";
        }

        $periodDemand = $dailyUsage * $periodDays;

        // Formula Q:
        // Q = max(0, Kebutuhan periode + Safety Stock - Stok tersedia - PO yang akan datang)
        $rawQ = max(0.0, ($periodDemand + $safetyStock) - $availableStock - $incomingPo);

        $finalQ = 0.0;
        if ($rawQ > 0) {
            // Apply MOQ
            $finalQ = max($rawQ, $moq);
            // Apply Order Multiple rounding
            if ($orderMultiple > 1) {
                $finalQ = ceil($finalQ / $orderMultiple) * $orderMultiple;
            }
        }

        $cost = $finalQ * (float) $summary['unit_cost'];

        return [
            'material' => $summary,
            'period_days' => $periodDays,
            'daily_usage' => round($dailyUsage, 2),
            'usage_source' => $usageSource,
            'historical_stats' => $histStats,
            'period_demand' => round($periodDemand, 2),
            'safety_stock' => $safetyStock,
            'available_stock' => $availableStock,
            'incoming_po' => $incomingPo,
            'raw_recommended_qty' => round($rawQ, 2),
            'final_recommended_qty' => round($finalQ, 2),
            'estimated_cost' => $cost,
            'needs_purchase' => ($finalQ > 0),
            'lead_time_days' => $leadTime,
            'preferred_supplier' => $summary['supplier_name'] ?? 'Belum ditentukan',
        ];
    }

    public static function createPrFromRecommendation(int $materialId, float $qty, int $userId, string $reason = ''): int
    {
        Database::beginTransaction();
        try {
            $summary = InventoryService::getMaterialStockSummary($materialId);
            $prNumber = 'PR-' . date('Ym') . '-' . str_pad((string) (Database::selectOne("SELECT COUNT(*) as c FROM purchase_requisitions")['c'] + 1), 3, '0', STR_PAD_LEFT);

            $cost = $qty * (float) $summary['unit_cost'];
            $reqDate = date('Y-m-d', strtotime('+' . max(1, (int)$summary['lead_time_days']) . ' days'));

            $priority = ($summary['stock_status'] === 'Kritis') ? 'critical' : (($summary['stock_status'] === 'Waspada') ? 'urgent' : 'normal');

            $prId = Database::insert('purchase_requisitions', [
                'pr_number' => $prNumber,
                'source' => 'ai_recommendation',
                'status' => 'submitted',
                'priority' => $priority,
                'required_date' => $reqDate,
                'total_estimated_cost' => $cost,
                'notes' => $reason ?: "Generated otomatis dari rekomendasi PPIC untuk {$summary['name']}.",
                'requested_by' => $userId,
            ]);

            Database::insert('purchase_requisition_items', [
                'pr_id' => $prId,
                'material_id' => $materialId,
                'suggested_qty' => $qty,
                'approved_qty' => $qty,
                'unit' => $summary['unit'],
                'estimated_unit_price' => (float) $summary['unit_cost'],
                'reason' => "Status stok: {$summary['stock_status']} ({$summary['status_note']})",
            ]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'CREATE_PR_AI',
                'module' => 'Purchasing',
                'description' => "Membuat PR {$prNumber} dari rekomendasi AI untuk {$summary['name']} ({$qty} {$summary['unit']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $prId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function approvePr(int $prId, int $userId): void
    {
        Database::beginTransaction();
        try {
            $pr = Database::selectOne("SELECT * FROM purchase_requisitions WHERE id = :id", ['id' => $prId]);
            if (!$pr) throw new RuntimeException("PR tidak ditemukan.");

            Database::update('purchase_requisitions', [
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $prId]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'APPROVE_PR',
                'module' => 'Purchasing',
                'description' => "Manager menyetujui Purchase Requisition {$pr['pr_number']}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function generatePoFromPr(int $prId, int $userId): int
    {
        Database::beginTransaction();
        try {
            $pr = Database::selectOne("SELECT * FROM purchase_requisitions WHERE id = :id", ['id' => $prId]);
            if (!$pr) throw new RuntimeException("PR tidak ditemukan.");
            if ($pr['status'] !== 'approved') throw new RuntimeException("PR harus disetujui terlebih dahulu sebelum diterbitkan PO.");

            $items = Database::select("SELECT pri.*, m.preferred_supplier_id, m.lead_time_days FROM purchase_requisition_items pri JOIN materials m ON m.id = pri.material_id WHERE pri.pr_id = :pr_id", ['pr_id' => $prId]);
            if (empty($items)) throw new RuntimeException("Item PR kosong.");

            $supplierId = (int) ($items[0]['preferred_supplier_id'] ?? 1);
            $leadTime = (int) ($items[0]['lead_time_days'] ?? 5);

            $poNumber = 'PO-' . date('Ym') . '-' . str_pad((string) (Database::selectOne("SELECT COUNT(*) as c FROM purchase_orders")['c'] + 1), 3, '0', STR_PAD_LEFT);
            $eta = date('Y-m-d', strtotime("+{$leadTime} days"));

            $poId = Database::insert('purchase_orders', [
                'po_number' => $poNumber,
                'pr_id' => $prId,
                'supplier_id' => $supplierId,
                'order_date' => date('Y-m-d'),
                'expected_arrival_date' => $eta,
                'status' => 'issued',
                'total_amount' => (float) $pr['total_estimated_cost'],
                'payment_terms' => 'NET 30',
                'notes' => "Diterbitkan berdasarkan persetujuan {$pr['pr_number']}",
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (float) ($item['approved_qty'] ?: $item['suggested_qty']);
                $price = (float) $item['estimated_unit_price'];
                Database::insert('purchase_order_items', [
                    'po_id' => $poId,
                    'material_id' => $item['material_id'],
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit' => $item['unit'],
                    'unit_price' => $price,
                    'subtotal' => $qty * $price,
                ]);
            }

            Database::update('purchase_requisitions', ['status' => 'ordered'], 'id = :id', ['id' => $prId]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'ISSUE_PO',
                'module' => 'Purchasing',
                'description' => "Menerbitkan Purchase Order {$poNumber} untuk PR {$pr['pr_number']}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $poId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
