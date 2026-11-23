<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Services\SalesForecastService;

class SalesForecastController extends Controller
{
    public static function index(
        ?int $year = null,
        ?int $selectedProductId = null,
        ?string $needsStartDate = null,
        ?int $needsProductId = null
    ): array
    {
        $year = $year ?: (int) date('Y');
        if ($needsStartDate !== null) {
            $dateParts = [];
            if (
                !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $needsStartDate, $dateParts)
                || !checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])
            ) {
                $needsStartDate = null;
            }
        }
        $needsProductId = $needsProductId !== null && $needsProductId > 0 ? $needsProductId : null;
        $products = Database::select("SELECT * FROM products ORDER BY id ASC");

        $salesHistories = Database::select("
            SELECT sh.*, p.code as product_code, p.name as product_name, p.unit as product_unit
            FROM sales_histories sh
            JOIN products p ON p.id = sh.product_id
            ORDER BY sh.sale_month DESC, sh.id DESC
            LIMIT 50
        ");

        $forecasts = Database::select("
            SELECT sf.*, p.code as product_code, p.name as product_name, p.unit as product_unit
            FROM sales_forecasts sf
            JOIN products p ON p.id = sf.product_id
            WHERE sf.forecast_year = :yr
            ORDER BY sf.id DESC
        ", ['yr' => $year]);

        $mrpEvaluations = SalesForecastService::evaluateForecastToRawMaterials($year);

        // Data grafik 12 bulan (Jan-Des): Total Forecast Tahun $year vs Riwayat Penjualan Aktual
        $monthlyForecastTotals = array_fill(1, 12, 0.0);
        foreach ($forecasts as $f) {
            for ($m = 1; $m <= 12; $m++) {
                $monthlyForecastTotals[$m] += (float) ($f['m' . $m] ?? 0);
            }
        }

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

        $needsSummary = Database::selectOne("
            SELECT
                COUNT(*) as total_requests,
                COUNT(DISTINCT product_id) as product_types,
                COUNT(DISTINCT customer_name) as customers
            FROM sales_histories
            WHERE status IN ('quotation', 'confirmed_so')
        ") ?? [];

        $total12mForecast = array_sum($monthlyForecastTotals);
        $shortageProductsCount = count(array_filter($mrpEvaluations, fn($e) => $e['rm_status'] === 'shortage' || !$e['has_formula']));

        return [
            'year' => $year,
            'selected_product_id' => $selectedProductId,
            'products' => $products,
            'sales_histories' => $salesHistories,
            'needs_histories' => $needsHistories,
            'needs_start_date' => $needsStartDate,
            'needs_product_id' => $needsProductId,
            'forecasts' => $forecasts,
            'mrp_evaluations' => $mrpEvaluations,
            'monthly_forecast_totals' => array_values(array_slice($monthlyForecastTotals, 1)),
            'monthly_actual_totals' => array_values(array_slice($monthlyActualTotals, 1)),
            'total_sales_records' => (int) ($salesSummary['total_sales_records'] ?? 0),
            'total_sales_qty' => (float) ($salesSummary['total_sales_qty'] ?? 0),
            'total_actual_sales_qty' => (float) ($salesSummary['total_actual_sales_qty'] ?? 0),
            'needs_summary' => [
                'total_requests' => (int) ($needsSummary['total_requests'] ?? 0),
                'product_types' => (int) ($needsSummary['product_types'] ?? 0),
                'customers' => (int) ($needsSummary['customers'] ?? 0),
            ],
            'total_12m_forecast' => $total12mForecast,
            'shortage_products_count' => $shortageProductsCount,
            'open_quotation_so' => (int) ($salesSummary['open_quotation_so'] ?? 0),
        ];
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
