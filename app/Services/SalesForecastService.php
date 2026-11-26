<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

class SalesForecastService
{
    /**
     * Hitung dan simpan Forecast Sales 12 Bulan secara otomatis berdasarkan data riwayat penjualan
     * menggunakan metode Hybrid OLS Linear Trend + Seasonal Index (fallback rata-rata jika < 2 bulan).
     */
    public static function generateForecast12Months(int $productId, int $targetYear, int $userId): array
    {
        $product = Database::selectOne("SELECT * FROM products WHERE id = :id", ['id' => $productId]);
        if (!$product) {
            throw new \RuntimeException("Produk tidak ditemukan.");
        }

        // Ambil agregasi penjualan per bulan (YYYY-MM) dari sales_histories (fulfilled & confirmed_so)
        $rows = Database::select("
            SELECT sale_month, SUM(quantity) as qty
            FROM sales_histories
            WHERE product_id = :pid AND status IN ('fulfilled', 'confirmed_so')
            GROUP BY sale_month
            ORDER BY sale_month ASC
        ", ['pid' => $productId]);

        if (empty($rows)) {
            throw new \RuntimeException("Belum ada data riwayat penjualan untuk produk '{$product['name']}'. Silakan input data penjualan terlebih dahulu.");
        }

        $n = count($rows);
        $ys = array_map(fn($r) => (float) $r['qty'], $rows);
        $meanY = array_sum($ys) / $n;

        // Hitung OLS Linear Trend (slope & intercept)
        $slope = 0.0;
        $intercept = $meanY;
        if ($n >= 2) {
            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumXX = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $x = (float) ($i + 1);
                $y = $ys[$i];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumXX += $x * $x;
            }
            $denom = ($n * $sumXX) - ($sumX * $sumX);
            if (abs($denom) > 0.0001) {
                $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
                // Redam lonjakan ekstrem agar proyeksi 12 bulan tetap realistis (maks +-8% per bulan)
                $maxSlope = max(1.0, $meanY * 0.08);
                $slope = max(-$maxSlope, min($maxSlope, $slope));
                $intercept = ($sumY - $slope * $sumX) / $n;
            }
        }

        // Hitung indeks musiman (seasonal index) per bulan 1..12 jika tersedia data pada bulan tsb
        $monthBuckets = [];
        foreach ($rows as $r) {
            $mNum = (int) substr((string) $r['sale_month'], 5, 2);
            if ($mNum >= 1 && $mNum <= 12) {
                $monthBuckets[$mNum][] = (float) $r['qty'];
            }
        }
        $seasonalIndex = [];
        for ($m = 1; $m <= 12; $m++) {
            if (!empty($monthBuckets[$m]) && $meanY > 0) {
                $mAvg = array_sum($monthBuckets[$m]) / count($monthBuckets[$m]);
                // Blend 60% musiman aktual + 40% baseline 1.0 agar halus
                $rawIdx = $mAvg / $meanY;
                $seasonalIndex[$m] = max(0.70, min(1.35, 0.6 * $rawIdx + 0.4));
            } else {
                $seasonalIndex[$m] = 1.0;
            }
        }

        // Proyeksikan 12 bulan (m1..m12) untuk $targetYear
        $months = [];
        $total = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $xFuture = (float) ($n + $m);
            $trendBase = max($meanY * 0.35, $intercept + ($slope * $xFuture));
            $projected = round(max(0.0, $trendBase * $seasonalIndex[$m]), 0);
            $months['m' . $m] = $projected;
            $total += $projected;
        }

        $methodLabel = $n >= 3 ? 'auto_trend' : 'auto_average';
        $note = "Dihitung otomatis dari {$n} bulan riwayat penjualan aktual (rata-rata " . number_format($meanY, 0, ',', '.') . " {$product['unit']}/bln).";

        $existing = Database::selectOne(
            "SELECT id FROM sales_forecasts WHERE product_id = :pid AND forecast_year = :yr",
            ['pid' => $productId, 'yr' => $targetYear]
        );

        $payload = array_merge($months, [
            'product_id' => $productId,
            'forecast_year' => $targetYear,
            'method' => $methodLabel,
            'total_12m_qty' => round($total, 2),
            'notes' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($existing) {
            Database::update('sales_forecasts', $payload, 'id = :id', ['id' => $existing['id']]);
            $fcId = (int) $existing['id'];
        } else {
            $payload['created_by'] = $userId;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $fcId = Database::insert('sales_forecasts', $payload);
        }

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'GENERATE_SALES_FORECAST',
            'module' => 'Forecast Sales',
            'description' => "Generate Forecast Sales 12 Bulan ({$targetYear}) otomatis untuk {$product['name']}: total " . number_format($total, 0, ',', '.') . " {$product['unit']}.",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return array_merge($payload, ['id' => $fcId, 'product_name' => $product['name']]);
    }

    /**
     * Impor data riwayat penjualan aktual dari CSV:
     * Format: product_code, sale_month (YYYY-MM), quantity, unit_price, customer_name, notes
     */
    public static function importSalesCsv(string $csvContent, int $userId): int
    {
        $lines = explode("\n", str_replace("\r", "", trim($csvContent)));
        if (count($lines) < 2) {
            return 0;
        }

        array_shift($lines); // buang header
        $count = 0;

        Database::beginTransaction();
        try {
            $seqBase = (int) (Database::selectOne("SELECT COUNT(*) as c FROM sales_histories")['c'] ?? 0);

            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $row = str_getcsv($line);
                if (count($row) < 3) continue;

                $prodCode = trim((string) ($row[0] ?? ''));
                $saleMonth = trim((string) ($row[1] ?? ''));
                $qty = max(0.0, (float) ($row[2] ?? 0));
                $unitPrice = max(0.0, (float) ($row[3] ?? 0));
                $customer = trim((string) ($row[4] ?? '')) ?: 'Pelanggan Aktual';
                $notes = trim((string) ($row[5] ?? 'Impor CSV Riwayat Penjualan'));

                if ($prodCode === '' || $qty <= 0) continue;
                if (!preg_match('/^\d{4}-\d{2}$/', $saleMonth)) {
                    // Coba parsing tanggal YYYY-MM-DD
                    if (preg_match('/^(\d{4}-\d{2})-\d{2}$/', $saleMonth, $mm)) {
                        $saleMonth = $mm[1];
                    } else {
                        $saleMonth = date('Y-m');
                    }
                }

                // Cari produk berdasarkan kode; jika belum ada, buat otomatis agar impor lancar
                $product = Database::selectOne("SELECT id FROM products WHERE code = :code", ['code' => $prodCode]);
                if (!$product) {
                    $prodId = Database::insert('products', [
                        'code' => $prodCode,
                        'name' => $prodCode,
                        'category' => 'Produk Jadi',
                        'unit' => 'Kg',
                        'standard_batch_size' => 1000,
                        'description' => 'Dibuat otomatis saat impor CSV data penjualan',
                        'is_active' => 1,
                    ]);
                } else {
                    $prodId = (int) $product['id'];
                }

                $seqBase++;
                $docNumber = 'SLS-' . str_replace('-', '', $saleMonth) . '-' . str_pad((string) $seqBase, 4, '0', STR_PAD_LEFT);
                $saleDate = $saleMonth . '-15';

                Database::insert('sales_histories', [
                    'doc_number' => $docNumber,
                    'doc_type' => 'history',
                    'customer_name' => $customer,
                    'product_id' => $prodId,
                    'sale_month' => $saleMonth,
                    'sale_date' => $saleDate,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => round($qty * $unitPrice, 2),
                    'status' => 'fulfilled',
                    'notes' => $notes,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'IMPORT_SALES_CSV',
                'module' => 'Forecast Sales',
                'description' => "Mengimpor {$count} baris data riwayat penjualan aktual dari CSV.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $count;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Evaluasi kebutuhan produksi bersih, formula BOM, dan bahan baku untuk forecast 12 bulan.
     */
    public static function evaluateForecastToRawMaterials(int $year): array
    {
        $forecasts = Database::select("
            SELECT sf.*, p.code as product_code, p.name as product_name, p.unit as product_unit,
                   p.standard_batch_size, COALESCE(psb.current_qty, 0) as finished_good_stock
            FROM sales_forecasts sf
            JOIN products p ON p.id = sf.product_id
            LEFT JOIN product_stock_balances psb ON psb.product_id = p.id
            WHERE sf.forecast_year = :yr
            ORDER BY sf.id DESC
        ", ['yr' => $year]);

        $stockSummary = InventoryService::getMaterialStockSummary();
        $stockById = [];
        foreach ($stockSummary as $s) {
            $stockById[(int) $s['id']] = $s;
        }

        $results = [];
        foreach ($forecasts as $fc) {
            $pid = (int) $fc['product_id'];
            $forecastDemand = 0.0;
            for ($month = 1; $month <= 12; $month++) {
                $forecastDemand += max(0.0, (float) ($fc['m' . $month] ?? 0));
            }
            if ($forecastDemand <= 0) {
                $forecastDemand = max(0.0, (float) ($fc['total_12m_qty'] ?? 0));
            }
            $finishedGoodStock = max(0.0, (float) $fc['finished_good_stock']);
            $netProduction = max(0.0, $forecastDemand - $finishedGoodStock);

            $bom = Database::selectOne("
                SELECT * FROM boms WHERE product_id = :pid AND is_active = 1 ORDER BY id DESC LIMIT 1
            ", ['pid' => $pid]);

            $allBoms = Database::select(
                'SELECT * FROM boms WHERE product_id = :pid ORDER BY id DESC',
                ['pid' => $pid]
            );
            $candidateEvaluations = [];
            foreach ($allBoms as $candidateBom) {
                $candidateEvaluations[] = self::evaluateBomForProduction($candidateBom, $netProduction, $fc, $stockById);
            }

            $activeEvaluation = null;
            $alternativeBoms = [];
            foreach ($candidateEvaluations as $candidate) {
                if ($bom && (int) $candidate['bom']['id'] === (int) $bom['id']) {
                    $activeEvaluation = $candidate;
                } else {
                    $alternativeBoms[] = $candidate;
                }
            }
            usort($alternativeBoms, static function (array $a, array $b): int {
                return [$a['shortage_count'], -$a['covered_item_count'], $a['estimated_replenishment_cost']]
                    <=> [$b['shortage_count'], -$b['covered_item_count'], $b['estimated_replenishment_cost']];
            });

            $hasFormula = $activeEvaluation !== null && !empty($activeEvaluation['rm_requirements']);
            $shortageCount = $activeEvaluation['shortage_count'] ?? 0;

            $results[] = [
                'forecast' => $fc,
                'forecast_demand' => round($forecastDemand, 2),
                'finished_good_stock' => $finishedGoodStock,
                'net_production' => round($netProduction, 2),
                'monthly_demand' => round($forecastDemand / 12, 2),
                'required_batches' => $activeEvaluation['required_batches'] ?? 0,
                'has_formula' => $hasFormula,
                'bom' => $activeEvaluation['bom'] ?? null,
                'rm_status' => !$hasFormula
                    ? 'no_formula'
                    : ($netProduction <= 0
                        ? 'not_required'
                        : ($shortageCount === 0 ? 'available' : 'shortage')),
                'rm_requirements' => $activeEvaluation['rm_requirements'] ?? [],
                'shortage_count' => $shortageCount,
                'alternative_boms' => $alternativeBoms,
            ];
        }

        return $results;
    }

    private static function evaluateBomForProduction(array $bom, float $netProduction, array $forecast, array $stockById): array
    {
        $bomItems = Database::select("
            SELECT bi.*, m.code as material_code, m.name as material_name, m.unit as material_unit,
                   m.unit_cost, m.moq, m.order_multiple, m.lead_time_days
            FROM bom_items bi
            JOIN materials m ON m.id = bi.material_id
            WHERE bi.bom_id = :bid
        ", ['bid' => $bom['id']]);

        $batchSize = max(1.0, (float) ($bom['batch_size'] ?: $forecast['standard_batch_size'] ?: 1000));
        $requiredBatches = $netProduction > 0 ? (int) ceil($netProduction / $batchSize) : 0;
        $scrapFactor = 1 + (max(0.0, (float) ($bom['scrap_pct'] ?? 0)) / 100.0);
        $requirements = [];
        $shortageCount = 0;
        $coveredItemCount = 0;
        $estimatedReplenishmentCost = 0.0;

        foreach ($bomItems as $item) {
            $materialId = (int) $item['material_id'];
            $stock = $stockById[$materialId] ?? [];
            $available = max(0.0, (float) ($stock['available_stock'] ?? 0));
            $needed = round((float) $item['quantity_per_batch'] * $requiredBatches * $scrapFactor, 2);
            $shortage = max(0.0, round($needed - $available, 2));
            if ($shortage > 0) {
                $shortageCount++;
            } else {
                $coveredItemCount++;
            }

            $moq = max(0.0, (float) ($stock['moq'] ?? $item['moq'] ?? 0));
            $multiple = max(1.0, (float) ($stock['order_multiple'] ?? $item['order_multiple'] ?? 1));
            $buyQty = 0.0;
            if ($shortage > 0) {
                $buyQty = max($shortage, $moq);
                $buyQty = ceil($buyQty / $multiple) * $multiple;
            }
            $unitCost = (float) ($stock['unit_cost'] ?? $item['unit_cost'] ?? 0);
            $estimatedReplenishmentCost += $buyQty * $unitCost;

            $requirements[] = [
                'material_id' => $materialId,
                'material_code' => $item['material_code'],
                'material_name' => $item['material_name'],
                'unit' => $item['material_unit'] ?: $item['unit'],
                'needed_12m' => $needed,
                'available_stock' => $available,
                'shortage_qty' => $shortage,
                'recommended_qty' => $buyQty,
                'supplier_name' => $stock['supplier_name'] ?? '-',
                'lead_time_days' => (int) ($stock['supplier_lead_time'] ?? $item['lead_time_days'] ?? 5),
            ];
        }

        return [
            'bom' => $bom,
            'required_batches' => $requiredBatches,
            'rm_requirements' => $requirements,
            'shortage_count' => $shortageCount,
            'covered_item_count' => $coveredItemCount,
            'estimated_replenishment_cost' => $estimatedReplenishmentCost,
        ];
    }
}
