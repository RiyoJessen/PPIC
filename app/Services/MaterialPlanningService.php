<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use RuntimeException;

class MaterialPlanningService
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
        foreach ($rows as $row) {
            $totalUsed += (float) $row['total_qty'];
        }

        $dailyAverage = ($lookbackDays > 0 && $totalUsed > 0)
            ? round($totalUsed / $lookbackDays, 2)
            : 0.0;

        return [
            'periods' => $rows,
            'total_used' => round($totalUsed, 2),
            'lookback_days' => $lookbackDays,
            'daily_average' => $dailyAverage,
            'has_history' => $totalUsed > 0,
        ];
    }

    public static function calculateRecommendation(int $materialId, int $periodDays = 30, int $lookbackDays = 90): array
    {
        $summary = InventoryService::getMaterialStockSummary($materialId);
        if (empty($summary)) {
            throw new RuntimeException('Material tidak ditemukan.');
        }

        $availableStock = (float) $summary['available_stock'];
        $safetyStock = (float) $summary['safety_stock'];
        $moq = (float) $summary['moq'];
        $orderMultiple = (float) $summary['order_multiple'] ?: 1.0;
        $leadTime = (int) $summary['lead_time_days'];
        $history = self::getHistoricalConsumptionStats($materialId, $lookbackDays);

        if ($history['has_history'] && $history['daily_average'] > 0) {
            $dailyUsage = $history['daily_average'];
            $usageSource = "Historis Riil ({$history['lookback_days']} Hari Terakhir)";
        } else {
            $dailyUsage = ($summary['reorder_point'] - $safetyStock) > 0 && $leadTime > 0
                ? (($summary['reorder_point'] - $safetyStock) / $leadTime)
                : ($safetyStock / 10);
            $usageSource = 'Estimasi Model ROP & Safety Stock';
        }

        $periodDemand = $dailyUsage * $periodDays;
        $rawQty = max(0.0, $periodDemand + $safetyStock - $availableStock);
        $recommendedQty = 0.0;
        if ($rawQty > 0) {
            $recommendedQty = max($rawQty, $moq);
            if ($orderMultiple > 1) {
                $recommendedQty = ceil($recommendedQty / $orderMultiple) * $orderMultiple;
            }
        }

        return [
            'material' => $summary,
            'period_days' => $periodDays,
            'daily_usage' => round($dailyUsage, 2),
            'usage_source' => $usageSource,
            'historical_stats' => $history,
            'period_demand' => round($periodDemand, 2),
            'safety_stock' => $safetyStock,
            'available_stock' => $availableStock,
            'raw_recommended_qty' => round($rawQty, 2),
            'final_recommended_qty' => round($recommendedQty, 2),
            'estimated_cost' => $recommendedQty * (float) $summary['unit_cost'],
            'requires_replenishment' => $recommendedQty > 0,
            'lead_time_days' => $leadTime,
            'preferred_supplier' => $summary['supplier_name'] ?? 'Belum ditentukan',
        ];
    }
}
