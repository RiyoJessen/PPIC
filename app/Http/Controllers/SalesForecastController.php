<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Services\SalesForecastService;

class SalesForecastController extends Controller
{
    public static function detail(string $source, int $id, string $period): array
    {
        if ($id < 1 || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new \RuntimeException('Data detail tidak valid.');
        }

        if ($source === 'forecast') {
            $monthColumn = 'm' . (int) substr($period, 5, 2);
            $record = Database::selectOne(
                "SELECT sf.method, sf.notes, sf.{$monthColumn} AS forecast_quantity,
                        p.id AS product_id, p.code AS product_code, p.unit AS product_unit
                 FROM sales_forecasts sf
                 JOIN products p ON p.id = sf.product_id
                 WHERE sf.id = :id AND sf.forecast_year = :year",
                ['id' => $id, 'year' => (int) substr($period, 0, 4)]
            );
            if (!$record) {
                throw new \RuntimeException('Data forecast tidak ditemukan.');
            }
            $forecastQuantity = (float) ($record['forecast_quantity'] ?? 0);
            $realization = Database::selectOne(
                "SELECT COALESCE(SUM(quantity), 0) AS quantity
                 FROM sales_histories
                 WHERE product_id = :product_id AND sale_month = :period
                   AND status IN ('confirmed_so', 'fulfilled')",
                ['product_id' => (int) $record['product_id'], 'period' => $period]
            );
            $realizationQuantity = (float) ($realization['quantity'] ?? 0);
            return [
                'source' => 'forecast',
                'evaluation' => array_merge([
                    'forecast_quantity' => $forecastQuantity,
                    'realization_quantity' => $realizationQuantity,
                    'product_unit' => $record['product_unit'],
                ], self::calculateEvaluationMetrics($forecastQuantity, $realizationQuantity)),
                'record' => [
                    'product_code' => $record['product_code'],
                    'period' => $period,
                    'method' => $record['method'],
                    'notes' => $record['notes'] ?? '',
                ],
            ];
        }

        if ($source === 'sales') {
            $record = Database::selectOne(
                "SELECT sh.doc_number, sh.doc_type, sh.sale_month, sh.customer_name,
                        sh.total_amount, sh.notes, p.code AS product_code
                 FROM sales_histories sh
                 JOIN products p ON p.id = sh.product_id
                 WHERE sh.id = :id AND sh.sale_month = :period",
                ['id' => $id, 'period' => $period]
            );
            if (!$record) {
                throw new \RuntimeException('Data dokumen sales tidak ditemukan.');
            }
            return ['source' => 'sales', 'record' => $record];
        }

        throw new \RuntimeException('Jenis data tidak valid.');
    }

    public static function index(
        ?int $year = null,
        ?int $selectedProductId = null,
        ?string $needsStartDate = null,
        ?int $needsProductId = null,
        ?string $historyStartDate = null,
        ?int $historyProductId = null,
        ?string $sourceFilter = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?string $search = null,
        ?int $page = null
    ): array
    {
        $year = $year ?: (int) date('Y');
        $validateDateFilter = static function (?string $date): ?string {
            $dateParts = [];
            if (
                $date === null
                || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dateParts)
                || !checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])
            ) {
                return null;
            }
            return $date;
        };
        $needsStartDate = $validateDateFilter($needsStartDate);
        $historyStartDate = $validateDateFilter($historyStartDate);
        $periodStart = self::validateDateFilter($periodStart);
        $periodEnd = self::validateDateFilter($periodEnd);
        if ($periodStart !== null && $periodEnd !== null && $periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }
        $needsProductId = $needsProductId !== null && $needsProductId > 0 ? $needsProductId : null;
        $historyProductId = $historyProductId !== null && $historyProductId > 0 ? $historyProductId : null;
        $selectedProductId = $selectedProductId !== null && $selectedProductId > 0 ? $selectedProductId : null;
        $sourceFilter = in_array($sourceFilter, ['all', 'sales', 'forecast'], true) ? $sourceFilter : 'forecast';
        if ($periodStart === null && $periodEnd === null) {
            $periodStart = sprintf('%04d-01', $year);
            $periodEnd = sprintf('%04d-12', $year);
        }
        $search = trim((string) $search);
        $search = $search !== '' ? substr($search, 0, 100) : '';
        $page = max(1, $page ?? 1);
        $perPage = 40;
        $products = Database::select("SELECT * FROM products ORDER BY id ASC");

        $historyConditions = ["sh.status = 'fulfilled'"];
        $historyParams = [];
        if ($historyStartDate !== null) {
            $historyConditions[] = 'sh.sale_date >= :history_start_date';
            $historyParams['history_start_date'] = $historyStartDate;
        }
        if ($historyProductId !== null) {
            $historyConditions[] = 'sh.product_id = :history_product_id';
            $historyParams['history_product_id'] = $historyProductId;
        }
        $historyWhere = implode(' AND ', $historyConditions);
        $salesHistories = Database::select("
            SELECT sh.*, p.code as product_code, p.name as product_name, p.unit as product_unit
            FROM sales_histories sh
            JOIN products p ON p.id = sh.product_id
            WHERE {$historyWhere}
            ORDER BY sh.sale_date DESC, sh.id DESC
            LIMIT 50
        ", $historyParams);

        $monthlyActualTotals = array_fill(1, 12, 0.0);
        $actualRows = Database::select("
            SELECT sale_month, SUM(quantity) as qty
            FROM sales_histories
            WHERE status = 'fulfilled' AND sale_month LIKE :yr
            GROUP BY sale_month
        ", ['yr' => $year . '-%']);
        foreach ($actualRows as $ar) {
            $mNum = (int) substr((string) $ar['sale_month'], 5, 2);
            if ($mNum >= 1 && $mNum <= 12) {
                $monthlyActualTotals[$mNum] += (float) $ar['qty'];
            }
        }

        $salesSummary = Database::selectOne("
            SELECT
                COUNT(*) as total_sales_records,
                COALESCE(SUM(quantity), 0) as total_sales_qty,
                COALESCE(SUM(CASE WHEN status = 'fulfilled' AND sale_month LIKE :yr THEN quantity ELSE 0 END), 0) as total_actual_sales_qty,
                COALESCE(SUM(CASE WHEN status IN ('quotation', 'confirmed_so') THEN 1 ELSE 0 END), 0) as open_quotation_so
            FROM sales_histories
        ", ['yr' => $year . '-%']) ?? [];

        $historySummary = Database::selectOne("
            SELECT
                COUNT(*) as transaction_count,
                COALESCE(SUM(quantity), 0) / 1000.0 as total_sales_tons
            FROM sales_histories
            WHERE status = 'fulfilled' AND sale_month LIKE :year_month
        ", ['year_month' => $year . '-%']) ?? [];

        $needsConditions = ["sh.status IN ('quotation', 'confirmed_so')"];
        $needsParams = [];
        if ($needsStartDate !== null) {
            $needsConditions[] = 'sh.sale_date >= :needs_start_date';
            $needsParams['needs_start_date'] = $needsStartDate;
        }
        if ($needsProductId !== null) {
            $needsConditions[] = 'sh.product_id = :needs_product_id';
            $needsParams['needs_product_id'] = $needsProductId;
        }
        $needsWhere = implode(' AND ', $needsConditions);
        $needsHistories = Database::select("
            SELECT sh.*, p.code as product_code, p.name as product_name, p.unit as product_unit
            FROM sales_histories sh
            JOIN products p ON p.id = sh.product_id
            WHERE {$needsWhere}
            ORDER BY sh.sale_date DESC, sh.id DESC
            LIMIT 50
        ", $needsParams);

        $combinedRows = [];
        $salesConditions = ["(sh.doc_type IN ('quotation', 'sales_order') OR sh.status IN ('quotation', 'confirmed_so'))"];
        $salesParams = [];
        if ($periodStart !== null) {
            $salesConditions[] = 'sh.sale_month >= :combined_period_start';
            $salesParams['combined_period_start'] = $periodStart;
        }
        if ($periodEnd !== null) {
            $salesConditions[] = 'sh.sale_month <= :combined_period_end';
            $salesParams['combined_period_end'] = $periodEnd;
        }
        if ($selectedProductId !== null) {
            $salesConditions[] = 'sh.product_id = :combined_product_id';
            $salesParams['combined_product_id'] = $selectedProductId;
        }
        if ($sourceFilter !== 'forecast') {
            $salesRows = Database::select("
                SELECT sh.*, p.code as product_code, p.name as product_name, p.unit as product_unit
                FROM sales_histories sh
                JOIN products p ON p.id = sh.product_id
                WHERE " . implode(' AND ', $salesConditions) . "
                ORDER BY sh.sale_month DESC, sh.id DESC
            ", $salesParams);
            foreach ($salesRows as $row) {
                $combinedRows[] = [
                    'source' => 'sales',
                    'id' => (int) $row['id'],
                    'display_id' => $row['doc_number'],
                    'product_id' => (int) $row['product_id'],
                    'product_code' => $row['product_code'],
                    'product_name' => $row['product_name'],
                    'product_unit' => $row['product_unit'],
                    'quantity' => (float) $row['quantity'],
                    'period' => $row['sale_month'],
                    'date' => $row['sale_date'],
                    'customer_name' => $row['customer_name'],
                    'doc_type' => $row['doc_type'],
                    'status' => $row['status'],
                    'total_amount' => (float) $row['total_amount'],
                    'notes' => $row['notes'] ?? '',
                ];
            }
        }

        $allForecasts = Database::select("
            SELECT sf.*, p.code as product_code, p.name as product_name, p.unit as product_unit
            FROM sales_forecasts sf
            JOIN products p ON p.id = sf.product_id
            ORDER BY sf.forecast_year DESC, sf.id DESC
        ");
        $availableForecastMonths = [];
        if ($sourceFilter !== 'sales') {
            foreach ($allForecasts as $forecast) {
                if ((int) $forecast['forecast_year'] !== $year) {
                    continue;
                }
                for ($month = 1; $month <= 12; $month++) {
                    if ((float) ($forecast['m' . $month] ?? 0) > 0) {
                        $availableForecastMonths[sprintf('%04d-%02d', $year, $month)] = true;
                    }
                }
            }
        }
        if ($sourceFilter !== 'forecast') {
            $availableSalesMonths = Database::select("
                SELECT DISTINCT sh.sale_month
                FROM sales_histories sh
                WHERE (sh.doc_type IN ('quotation', 'sales_order') OR sh.status IN ('quotation', 'confirmed_so'))
                  AND sh.sale_month >= :year_start
                  AND sh.sale_month <= :year_end
            ", ['year_start' => sprintf('%04d-01', $year), 'year_end' => sprintf('%04d-12', $year)]);
            foreach ($availableSalesMonths as $availableSalesMonth) {
                $period = (string) $availableSalesMonth['sale_month'];
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                    $availableForecastMonths[$period] = true;
                }
            }
        }
        ksort($availableForecastMonths);
        $comparisonChartTotals = [];
        foreach ($allForecasts as $forecast) {
            if ($selectedProductId !== null && (int) $forecast['product_id'] !== $selectedProductId) {
                continue;
            }
            if ($periodStart !== null && (int) $forecast['forecast_year'] < (int) substr($periodStart, 0, 4)) {
                continue;
            }
            if ($periodEnd !== null && (int) $forecast['forecast_year'] > (int) substr($periodEnd, 0, 4)) {
                continue;
            }
            for ($month = 1; $month <= 12; $month++) {
                $period = sprintf('%04d-%02d', (int) $forecast['forecast_year'], $month);
                if (($periodStart !== null && $period < $periodStart) || ($periodEnd !== null && $period > $periodEnd)) {
                    continue;
                }
                $forecastRow = [
                    'forecast_id' => (int) $forecast['id'],
                    'product_id' => (int) $forecast['product_id'],
                    'product_code' => $forecast['product_code'],
                    'product_name' => $forecast['product_name'],
                    'product_unit' => $forecast['product_unit'],
                    'period' => $period,
                    'quantity' => (float) ($forecast['m' . $month] ?? 0),
                    'method' => $forecast['method'],
                    'notes' => $forecast['notes'] ?? '',
                ];
                if (!isset($comparisonChartTotals[$period])) {
                    $comparisonChartTotals[$period] = ['forecast' => 0.0, 'realization' => 0.0];
                }
                $comparisonChartTotals[$period]['forecast'] += $forecastRow['quantity'];

                if ($forecastRow['quantity'] > 0 && $sourceFilter !== 'sales' && (
                    $search === ''
                    || stripos((string) $forecastRow['product_code'], $search) !== false
                    || stripos((string) $forecastRow['product_name'], $search) !== false
                    || stripos((string) $forecastRow['method'], $search) !== false
                    || stripos((string) $forecastRow['notes'], $search) !== false
                )) {
                    $combinedRows[] = [
                        'source' => 'forecast',
                        'id' => $forecastRow['forecast_id'],
                        'display_id' => 'FC-' . $forecastRow['forecast_id'] . '-' . $period,
                        'product_id' => $forecastRow['product_id'],
                        'product_code' => $forecastRow['product_code'],
                        'product_name' => $forecastRow['product_name'],
                        'product_unit' => $forecastRow['product_unit'],
                        'quantity' => $forecastRow['quantity'],
                        'period' => $period,
                        'date' => null,
                        'customer_name' => null,
                        'doc_type' => null,
                        'status' => 'forecast',
                        'total_amount' => null,
                        'method' => $forecastRow['method'],
                        'notes' => $forecastRow['notes'],
                    ];
                }
            }
        }

        $realizationConditions = ["status IN ('confirmed_so', 'fulfilled')"];
        $realizationParams = [];
        if ($periodStart !== null) {
            $realizationConditions[] = 'sale_month >= :chart_period_start';
            $realizationParams['chart_period_start'] = $periodStart;
        }
        if ($periodEnd !== null) {
            $realizationConditions[] = 'sale_month <= :chart_period_end';
            $realizationParams['chart_period_end'] = $periodEnd;
        }
        if ($selectedProductId !== null) {
            $realizationConditions[] = 'product_id = :chart_product_id';
            $realizationParams['chart_product_id'] = $selectedProductId;
        }
        $chartRealizations = Database::select(
            "SELECT sale_month, SUM(quantity) AS quantity
             FROM sales_histories
             WHERE " . implode(' AND ', $realizationConditions) . "
             GROUP BY sale_month",
            $realizationParams
        );
        foreach ($chartRealizations as $chartRealization) {
            $chartPeriod = (string) $chartRealization['sale_month'];
            if (!isset($comparisonChartTotals[$chartPeriod])) {
                $comparisonChartTotals[$chartPeriod] = ['forecast' => 0.0, 'realization' => 0.0];
            }
            $comparisonChartTotals[$chartPeriod]['realization'] = (float) $chartRealization['quantity'];
        }
        ksort($comparisonChartTotals);
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $comparisonChartLabels = array_map(
            static fn(string $period): string => $monthNames[(int) substr($period, 5, 2) - 1] . ' ' . substr($period, 0, 4),
            array_keys($comparisonChartTotals)
        );

        if ($search !== '' && $sourceFilter !== 'forecast') {
            $combinedRows = array_values(array_filter($combinedRows, static function (array $row) use ($search): bool {
                return stripos((string) $row['display_id'], $search) !== false
                    || stripos((string) $row['product_code'], $search) !== false
                    || stripos((string) $row['product_name'], $search) !== false
                    || stripos((string) ($row['customer_name'] ?? ''), $search) !== false
                    || stripos((string) ($row['method'] ?? ''), $search) !== false
                    || stripos((string) ($row['notes'] ?? ''), $search) !== false;
            }));
        }
        usort($combinedRows, static function (array $left, array $right): int {
            $periodOrder = strcmp($right['period'], $left['period']);
            if ($periodOrder !== 0) {
                return $periodOrder;
            }
            return strcmp($left['source'], $right['source']);
        });
        $combinedTotal = count($combinedRows);
        $combinedLastPage = max(1, (int) ceil($combinedTotal / $perPage));
        $page = min($page, $combinedLastPage);
        $paginatedCombinedRows = array_slice($combinedRows, ($page - 1) * $perPage, $perPage);

        return [
            'year' => $year,
            'selected_product_id' => $selectedProductId,
            'products' => $products,
            'sales_histories' => $salesHistories,
            'needs_histories' => $needsHistories,
            'needs_start_date' => $needsStartDate,
            'needs_product_id' => $needsProductId,
            'history_start_date' => $historyStartDate,
            'history_product_id' => $historyProductId,
            'monthly_actual_totals' => array_values(array_slice($monthlyActualTotals, 1)),
            'total_sales_records' => (int) ($salesSummary['total_sales_records'] ?? 0),
            'total_sales_qty' => (float) ($salesSummary['total_sales_qty'] ?? 0),
            'total_actual_sales_qty' => (float) ($salesSummary['total_actual_sales_qty'] ?? 0),
            'history_summary' => [
                'transaction_count' => (int) ($historySummary['transaction_count'] ?? 0),
                'total_sales_tons' => (float) ($historySummary['total_sales_tons'] ?? 0),
            ],
            'open_quotation_so' => (int) ($salesSummary['open_quotation_so'] ?? 0),
            'combined_rows' => $paginatedCombinedRows,
            'combined_total' => $combinedTotal,
            'combined_page' => $page,
            'combined_per_page' => $perPage,
            'combined_last_page' => $combinedLastPage,
            'available_forecast_months' => array_keys($availableForecastMonths),
            'source_filter' => $sourceFilter,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'search' => $search,
            'comparison_chart_labels' => $comparisonChartLabels,
            'comparison_chart_forecast' => array_values(array_map(
                static fn(array $totals): float => $totals['forecast'],
                $comparisonChartTotals
            )),
            'comparison_chart_realization' => array_values(array_map(
                static fn(array $totals): float => $totals['realization'],
                $comparisonChartTotals
            )),

        ];
    }

    public static function calculateEvaluationMetrics(float $forecast, float $realization): array
    {
        $difference = round($forecast - $realization, 2);
        $denominator = max($forecast, $realization);
        $errorPercentage = $denominator > 0 ? round(abs($difference) / $denominator * 100, 2) : 0.0;

        if ($difference > 0) {
            $evaluation = 'Overforecast';
            $insight = 'Prediksi lebih tinggi daripada realisasi. Tinjau kembali pola tren dan asumsi musiman.';
        } elseif ($difference < 0) {
            $evaluation = 'Underforecast';
            $insight = 'Realisasi melampaui prediksi. Tinjau kapasitas dan perbarui proyeksi periode berikutnya.';
        } else {
            $evaluation = 'Sesuai';
            $insight = 'Prediksi sesuai dengan realisasi. Pertahankan pemantauan pola permintaan.';
        }

        return [
            'difference' => $difference,
            'error_percentage' => $errorPercentage,
            'evaluation' => $evaluation,
            'insight' => $insight,
        ];
    }

    private static function validateDateFilter(?string $date): ?string
    {
        if ($date === null || !preg_match('/^(\d{4})-(\d{2})$/', $date, $parts)) {
            return null;
        }
        return (int) $parts[2] >= 1 && (int) $parts[2] <= 12 ? $date : null;
    }

    public static function storeSalesHistory(array $post, int $userId): int
    {
        $productId = (int) ($post['product_id'] ?? 0);
        $customer = trim((string) ($post['customer_name'] ?? '')) ?: 'Pelanggan Umum';
        $saleMonth = trim((string) ($post['sale_month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $saleMonth)) {
            $saleMonth = date('Y-m');
        }
        $qty = max(0.0, (float) ($post['quantity'] ?? 0));
        $unitPrice = max(0.0, (float) ($post['unit_price'] ?? 0));
        $docType = in_array($post['doc_type'] ?? 'history', ['history', 'quotation', 'sales_order'], true)
            ? (string) $post['doc_type']
            : 'history';
        $notes = trim((string) ($post['notes'] ?? ''));

        if ($productId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException("Produk dan kuantitas penjualan harus diisi (> 0).");
        }

        $prefix = match($docType) {
            'quotation' => 'QT',
            'sales_order' => 'SO',
            default => 'SLS',
        };
        $status = match($docType) {
            'quotation' => 'quotation',
            'sales_order' => 'confirmed_so',
            default => 'fulfilled',
        };
        $seq = (int) (Database::selectOne("SELECT COUNT(*) as c FROM sales_histories")['c'] ?? 0) + 1;
        $docNumber = $prefix . '-' . str_replace('-', '', $saleMonth) . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $id = Database::insert('sales_histories', [
            'doc_number' => $docNumber,
            'doc_type' => $docType,
            'customer_name' => $customer,
            'product_id' => $productId,
            'sale_month' => $saleMonth,
            'sale_date' => $saleMonth . '-15',
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'total_amount' => round($qty * $unitPrice, 2),
            'status' => $status,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'CREATE_SALES_DATA',
            'module' => 'Forecast Sales',
            'description' => "Mencatat {$docNumber} ({$customer}) periode {$saleMonth} sebesar {$qty}.",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public static function updateSalesStatus(int $id, string $newStatus, int $userId): void
    {
        if (!in_array($newStatus, ['quotation', 'confirmed_so', 'fulfilled'], true)) {
            throw new \InvalidArgumentException("Status tidak valid.");
        }
        Database::update('sales_histories', ['status' => $newStatus], 'id = :id', ['id' => $id]);
    }

    public static function deleteSalesHistory(int $id, int $userId): void
    {
        Database::statement("DELETE FROM sales_histories WHERE id = :id", ['id' => $id]);
    }

    public static function generateForecast(array $post, int $userId): int
    {
        $year = (int) ($post['forecast_year'] ?? date('Y'));
        $productId = (int) ($post['product_id'] ?? 0);

        if ($productId > 0) {
            SalesForecastService::generateForecast12Months($productId, $year, $userId);
            return 1;
        }

        // Generate otomatis untuk semua produk yang memiliki riwayat penjualan
        $prodRows = Database::select("
            SELECT DISTINCT product_id FROM sales_histories WHERE status IN ('fulfilled', 'confirmed_so')
        ");
        if (empty($prodRows)) {
            throw new \RuntimeException("Belum ada data riwayat penjualan / SO untuk di-forecast. Silakan input atau impor CSV data sales terlebih dahulu.");
        }

        $count = 0;
        foreach ($prodRows as $pr) {
            SalesForecastService::generateForecast12Months((int) $pr['product_id'], $year, $userId);
            $count++;
        }
        return $count;
    }

    public static function storeForecastManual(array $post, int $userId): int
    {
        $productId = (int) ($post['product_id'] ?? 0);
        $year = (int) ($post['forecast_year'] ?? date('Y'));
        if ($productId <= 0) {
            throw new \InvalidArgumentException("Silakan pilih produk terlebih dahulu.");
        }

        $months = [];
        $total = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $val = max(0.0, (float) ($post['m' . $m] ?? 0));
            $months['m' . $m] = $val;
            $total += $val;
        }

        $notes = trim((string) ($post['notes'] ?? 'Input Forecast Sales 12 Bulan manual'));
        $existing = Database::selectOne(
            "SELECT id FROM sales_forecasts WHERE product_id = :pid AND forecast_year = :yr",
            ['pid' => $productId, 'yr' => $year]
        );

        $payload = array_merge($months, [
            'product_id' => $productId,
            'forecast_year' => $year,
            'method' => 'manual',
            'total_12m_qty' => round($total, 2),
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($existing) {
            Database::update('sales_forecasts', $payload, 'id = :id', ['id' => $existing['id']]);
            return (int) $existing['id'];
        }

        $payload['created_by'] = $userId;
        $payload['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('sales_forecasts', $payload);
    }

    public static function deleteForecast(int $id, int $userId): void
    {
        Database::statement("DELETE FROM sales_forecasts WHERE id = :id", ['id' => $id]);
    }
}
