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
     * Evaluasi keterkaitan Forecast Sales 12 Bulan -> Cek Formula BOM -> Cek Raw Material & AI Insight.
     */
    public static function evaluateForecastToRawMaterials(int $year): array
    {
        $forecasts = Database::select("
            SELECT sf.*, p.code as product_code, p.name as product_name, p.unit as product_unit, p.standard_batch_size
            FROM sales_forecasts sf
            JOIN products p ON p.id = sf.product_id
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
            $curMonthCol = 'm' . (int) date('n');
            $monthlyDemand = max((float) ($fc[$curMonthCol] ?? 0), round(((float) $fc['total_12m_qty']) / 12, 2));

            $bom = Database::selectOne("
                SELECT * FROM boms WHERE product_id = :pid AND is_active = 1 ORDER BY id DESC LIMIT 1
            ", ['pid' => $pid]);

            if (!$bom) {
                $results[] = [
                    'forecast' => $fc,
                    'monthly_demand' => $monthlyDemand,
                    'has_formula' => false,
                    'bom' => null,
                    'rm_status' => 'no_formula',
                    'rm_requirements' => [],
                    'shortage_count' => 0,
                ];
                continue;
            }

            $batchSize = max(1.0, (float) ($bom['batch_size'] ?: $fc['standard_batch_size'] ?: 1000));
            $batchFactor = $monthlyDemand / $batchSize;
            $scrapFactor = 1 + (max(0.0, (float) ($bom['scrap_pct'] ?? 0)) / 100.0);

            $bomItems = Database::select("
                SELECT bi.*, m.code as material_code, m.name as material_name, m.unit as material_unit,
                       m.unit_cost, m.safety_stock, m.moq, m.lead_time_days
                FROM bom_items bi
                JOIN materials m ON m.id = bi.material_id
                WHERE bi.bom_id = :bid
            ", ['bid' => $bom['id']]);

            $reqs = [];
            $shortageCount = 0;
            foreach ($bomItems as $bi) {
                $mid = (int) $bi['material_id'];
                $st = $stockById[$mid] ?? [];
                $avail = (float) ($st['available_stock'] ?? 0);
                $needed = round((float) $bi['quantity_per_batch'] * $batchFactor * $scrapFactor, 1);
                $shortage = max(0.0, round($needed - $avail, 1));
                if ($shortage > 0) $shortageCount++;

                $buyQty = $shortage > 0 ? max($shortage + (float) ($bi['safety_stock'] ?? 0), (float) ($bi['moq'] ?? 0)) : 0.0;

                $reqs[] = [
                    'material_id' => $mid,
                    'material_code' => $bi['material_code'],
                    'material_name' => $bi['material_name'],
                    'unit' => $bi['material_unit'] ?: $bi['unit'],
                    'needed_monthly' => $needed,
                    'available_stock' => $avail,
                    'shortage_qty' => $shortage,
                    'recommended_buy_qty' => round($buyQty, 0),
                    'supplier_name' => $st['supplier_name'] ?? '-',
                    'lead_time_days' => (int) ($st['supplier_lead_time'] ?? $bi['lead_time_days'] ?? 5),
                ];
            }

            $results[] = [
                'forecast' => $fc,
                'monthly_demand' => $monthlyDemand,
                'has_formula' => true,
                'bom' => $bom,
                'rm_status' => ($shortageCount === 0 && !empty($reqs)) ? 'available' : 'shortage',
                'rm_requirements' => $reqs,
                'shortage_count' => $shortageCount,
            ];
        }

        return $results;
    }
}
