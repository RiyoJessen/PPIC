<?php

namespace App\Services;

use App\Config\AppConfig;
use Google\Cloud\BigQuery\BigQueryClient;

class BigQueryService
{
    protected BigQueryClient $client;
    protected string $projectId;
    protected string $dataset;

    const BQ_TABLE = '`lautan-luas-big-data-project1.shared_view.VW_COPA_DIST_LTL`';

    public function __construct()
    {
        $this->projectId = (string) AppConfig::get('BIGQUERY_PROJECT_ID', '');
        $this->dataset = (string) AppConfig::get('BIGQUERY_DATASET', 'shared_view');

        if ($this->projectId === '') {
            throw new \RuntimeException(
                'BIGQUERY_PROJECT_ID belum dikonfigurasi di file .env.'
            );
        }

        $keyFile = (string) AppConfig::get(
            'GOOGLE_CLOUD_KEY_FILE',
            'storage/app/credentials/bigquery-key.json'
        );

        // Ubah path relatif menjadi path absolut dari root project
        if (!str_starts_with($keyFile, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $keyFile)) {
            $keyFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $keyFile);
        }

        if (!file_exists($keyFile)) {
            throw new \RuntimeException(
                'File kredensial BigQuery tidak ditemukan: ' . $keyFile
            );
        }

        $this->client = new BigQueryClient([
            'projectId' => $this->projectId,
            'keyFilePath' => $keyFile,
        ]);
    }

    // =========================================================
    // RAW QUERY HELPER
    // =========================================================
    public function query(string $sql, array $params = []): array
    {
        $queryJobConfig = $this->client->query($sql);
        $queryJobConfig->useLegacySql(false);
        $queryJobConfig->useQueryCache(true);

        if (!empty($params)) {
            $queryJobConfig->parameters($params);
        }

        $queryResults = $this->client->runQuery($queryJobConfig, [
            'maxResults' => 10000,
            'timeoutMs'  => 120000,
        ]);

        $rows    = [];
        $skipped = 0;

        foreach ($queryResults as $row) {
            try {
                $safeRow = [];
                foreach ($row as $key => $value) {
                    try {
                        if ($value === null) {
                            $safeRow[$key] = null;
                        } elseif (is_object($value) && method_exists($value, 'get')) {
                            $safeRow[$key] = (string) $value->get();
                        } elseif (is_object($value)) {
                            $safeRow[$key] = (string) $value;
                        } else {
                            $safeRow[$key] = (string) $value;
                        }
                    } catch (\Throwable $cellErr) {
                        $safeRow[$key] = null;
                        error_log("BigQuery cell parse error [{$key}]: " . $cellErr->getMessage());
                    }
                }
                $rows[] = $safeRow;
            } catch (\Throwable $e) {
                $skipped++;
                error_log("BigQuery row skipped: " . $e->getMessage());
                continue;
            }
        }

        return $rows;
    }

    private function buildPeriodClause(array &$params): string
{
    $startYear  = $_SESSION['period_start_year'] ?? null;;
    $startMonth = $_SESSION['period_start_month'] ?? null;
    $endYear    = $_SESSION['period_end_year'] ?? null;
    $endMonth   = $_SESSION['period_end_month'] ?? null;

    if (!$startYear || !$startMonth || !$endYear || !$endMonth) {
        return '';
    }

    // PERIOD_YM is stored as STRING '202502' — compare directly as strings.
    // Lexicographic order works correctly for YYYYMM format.
    $startYM = sprintf('%04d%02d', $startYear, $startMonth);
    $endYM   = sprintf('%04d%02d', $endYear,   $endMonth);

    // Use literal values in the query — NOT named parameters with type structs.
    // Named parameters with ['value'=>..., 'type'=>...] cause the STRUCT cast error.
    return "PERIOD_YM >= '{$startYM}' AND PERIOD_YM <= '{$endYM}'";
}

    // =========================================================
    // GET SALES DATA
    // ─────────────────────────────────────────────────────────
    // All filters including period range from session are applied here.
    // The caller (SalesController) can also pass explicit year/month
    // filters to further narrow the result (e.g. for sync operations).
    // =========================================================
    public function getSalesData(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        // ── Session-based period range (primary filter) ──
        $periodClause = $this->buildPeriodClause($params);
        if ($periodClause !== '') {
            $where[] = $periodClause;
        }

        // ── Optional explicit filters (can further narrow period range) ──
        if (!empty($filters['year'])) {
            $where[]        = 'FISCAL_YEAR = @year';
            $params['year'] = ['value' => (string) $filters['year'], 'type' => 'STRING'];
        }
        if (!empty($filters['month'])) {
            $where[]         = 'PERIOD_MONTH = @month';
            $params['month'] = ['value' => str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT), 'type' => 'STRING'];
        }
        if (!empty($filters['dc_id'])) {
            $where[]         = 'DIST_CHAN_CODE = @dc_id';
            $params['dc_id'] = ['value' => (string) $filters['dc_id'], 'type' => 'STRING'];
        }
        if (!empty($filters['company_code'])) {
            $where[]                = 'COMPANY_CODE = @company_code';
            $params['company_code'] = ['value' => (string) $filters['company_code'], 'type' => 'STRING'];
        }
        if (!empty($filters['customer_id'])) {
            $where[]               = 'CUSTOMER = @customer_id';
            $params['customer_id'] = ['value' => (string) $filters['customer_id'], 'type' => 'STRING'];
        }
        if (!empty($filters['supplier_id'])) {
            $where[]               = 'SUPPLIER = @supplier_id';
            $params['supplier_id'] = ['value' => (string) $filters['supplier_id'], 'type' => 'STRING'];
        }
        if (empty($filters['all_value_types'])) {
            $where[] = "VALUE_TYPE = 10";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // ── Dynamic LIMIT: derived from period span if no explicit limit ──
        // This prevents accidentally pulling millions of rows if no session is set
        $dynamicLimit = $this->calculateDynamicLimit();
        $effectiveLimit = ($limit > 0) ? $limit : $dynamicLimit;

        $offsetClause = $offset > 0 ? "OFFSET {$offset}" : '';

        $sql = "
            SELECT
                -- String columns
                IFNULL(CAST(PRODUCT_HIERARCHY  AS STRING), '')  AS prod_hierarchy_id,
                IFNULL(CAST(PH_DESC            AS STRING), '')  AS prod_hierarchy,
                IFNULL(CAST(DIST_CHAN_CODE      AS STRING), '')  AS dc_id,
                IFNULL(CAST(DIST_CHAN_NAME      AS STRING), '')  AS dc,
                IFNULL(CAST(DIVISION_CODE       AS STRING), '')  AS division_id,
                IFNULL(CAST(DIVISION_NAME       AS STRING), '')  AS division,
                IFNULL(CAST(FISCAL_YEAR        AS STRING), '')  AS fiscal_year,
                IFNULL(CAST(PERIOD_MONTH       AS STRING), '')  AS month_num,
                IFNULL(CAST(PERIOD_YM          AS STRING), '')  AS period_ym,
                IFNULL(CAST(COMPANY_CODE       AS STRING), '')  AS company_code,
                IFNULL(CAST(PLANT              AS STRING), '')  AS plant,
                IFNULL(CAST(PROFIT_CENTER_CODE AS STRING), '')  AS profit_center,
                IFNULL(CAST(SALES_OFFICE       AS STRING), '')  AS sales_office,
                IFNULL(CAST(SALES_GROUP        AS STRING), '')  AS sales_group,
                IFNULL(CAST(SALES_EMPLOYEE     AS STRING), '')  AS salesman_id,
                IFNULL(CAST(SALESMAN_NAME      AS STRING), '')  AS salesman,
                IFNULL(CAST(CUSTOMER           AS STRING), '')  AS customer_id,
                IFNULL(CAST(CUSTOMER_NAME      AS STRING), '')  AS customer,
                IFNULL(CAST(PRODUCT_NUMBER     AS STRING), '')  AS material_id,
                IFNULL(CAST(PRODUCT_NAME       AS STRING), '')  AS material,
                IFNULL(CAST(WGBEZ              AS STRING), '')  AS mapping_material,
                IFNULL(CAST(FORM_CODE          AS STRING), '')  AS form_code,
                IFNULL(CAST(FORM_DESC          AS STRING), '')  AS form_desc,
                IFNULL(CAST(FUNCTION_CODE      AS STRING), '')  AS function_code,
                IFNULL(CAST(FUNCTION_DESC      AS STRING), '')  AS function_desc,
                IFNULL(CAST(SPECIAL_COMO_CODE  AS STRING), '')  AS special_como_code,
                IFNULL(CAST(SPECIAL_COMO_DESC  AS STRING), '')  AS special_como_desc,
                IFNULL(CAST(SUPPLIER           AS STRING), '')  AS supplier_id,
                IFNULL(CAST(SUPPLIER_NAME      AS STRING), '')  AS supplier_name,
                IFNULL(CAST(USER_ID            AS STRING), '')  AS user_id,

                -- Numeric columns
                IFNULL(FORMAT('%.0f', SAFE_CAST(QTY_PA_KG           AS FLOAT64)), '0') AS qty,
                IFNULL(FORMAT('%.4f', SAFE_CAST(GROSS_REVENUE        AS FLOAT64)), '0') AS gross_revenue,
                IFNULL(FORMAT('%.4f', SAFE_CAST(LOGISTIC_EXP         AS FLOAT64)), '0') AS logistic_expense,
                IFNULL(FORMAT('%.4f', SAFE_CAST(NET_REVENUE          AS FLOAT64)), '0') AS net_revenue,
                IFNULL(FORMAT('%.4f', SAFE_CAST(COGS                 AS FLOAT64)), '0') AS cogs,
                IFNULL(FORMAT('%.4f', SAFE_CAST(NET_GP               AS FLOAT64)), '0') AS net_gp,
                IFNULL(FORMAT('%.4f', SAFE_CAST(GROSS_PROFIT         AS FLOAT64)), '0') AS gross_gp,
                IFNULL(FORMAT('%.4f', SAFE_CAST(LTL_FLOOR_PRICE      AS FLOAT64)), '0') AS floor_price,
                IFNULL(FORMAT('%.4f', SAFE_CAST(LTL_GP_MARGIN_2      AS FLOAT64)), '0') AS net_gp_fp,
                IFNULL(FORMAT('%.4f', SAFE_CAST(LTL_INTERNAL_PRICE_2 AS FLOAT64)), '0') AS internal_price_2,
                IFNULL(FORMAT('%.4f', SAFE_CAST(LTL_REC_SELL_PRICE   AS FLOAT64)), '0') AS rsp,
                IFNULL(FORMAT('%.4f', SAFE_CAST(REPRESENTATION_SP    AS FLOAT64)), '0') AS representation_sp,
                IFNULL(FORMAT('%.4f', SAFE_CAST(SALES_PROMOTION_EXP  AS FLOAT64)), '0') AS sales_promotion_exp

            FROM " . self::BQ_TABLE . "
            {$whereClause}
            ORDER BY CAST(FISCAL_YEAR AS INT64) DESC, CAST(PERIOD_MONTH AS INT64) DESC, CUSTOMER
            LIMIT {$effectiveLimit}
            {$offsetClause}
        ";

        return $this->query($sql, $params);
    }

    // =========================================================
    // DYNAMIC LIMIT — based on period span in session
    // ─────────────────────────────────────────────────────────
    // Prevents runaway queries if the period session is set to a
    // wide range. Adds 20% headroom over the row estimate.
    // Hard-capped at 500,000 rows.
    // =========================================================
    private function calculateDynamicLimit(): int
    {
        $startYear  = $_SESSION['period_start_year'];
        $startMonth = $_SESSION['period_start_month'];
        $endYear    = $_SESSION['period_end_year'];
        $endMonth   = $_SESSION['period_end_month'];

        if (!$startYear || !$startMonth || !$endYear || !$endMonth) {
            return 10000; // Default safe limit when no period is set
        }

        $spanMonths  = ($endYear - $startYear) * 12 + ($endMonth - $startMonth) + 1;
        $rowsPerMonth = 7656;
        $estimated   = $spanMonths * $rowsPerMonth;
        $withHeadroom = (int) round($estimated * 1.20); // 20% buffer

        return min($withHeadroom, 500000);
    }

    // =========================================================
    // GET ALL SALES DATA — batched fetch across full period
    // =========================================================
    public function getAllSalesData(array $filters = []): array
    {
        $BATCH_SIZE = 2000;
        $offset     = 0;
        $allRows    = [];
        $maxRows    = $this->calculateDynamicLimit();

        do {
            try {
                $batch = $this->getSalesData($filters, $BATCH_SIZE, $offset);
            } catch (\Throwable $e) {
                error_log("BigQuery batch error at offset {$offset}: " . $e->getMessage());
                break; // <-- was: $offset += $BATCH_SIZE; continue; — $batch never set, causes crash
            }

            if (empty($batch)) break;

            $allRows = array_merge($allRows, $batch);
            $offset += $BATCH_SIZE;

            if (count($allRows) >= $maxRows) {
                error_log("BigQuery: reached dynamic limit of {$maxRows} rows at offset {$offset}");
                break;
            }

        } while (count($batch) === $BATCH_SIZE);

        return $allRows;
    }

    // =========================================================
    // COUNT — for pagination / progress
    // =========================================================
    public function countSalesData(array $filters = []): int
    {
        $where  = [];
        $params = [];

        // Include period clause in count too
        $periodClause = $this->buildPeriodClause($params);
        if ($periodClause !== '') {
            $where[] = $periodClause;
        }

        if (!empty($filters['year'])) {
            $where[]        = 'FISCAL_YEAR = @year';
            $params['year'] = ['value' => (string) $filters['year'], 'type' => 'STRING'];
        }
        if (!empty($filters['month'])) {
            $where[]         = 'PERIOD_MONTH = @month';
            $params['month'] = ['value' => str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT), 'type' => 'STRING'];
        }
        if (!empty($filters['dc_id'])) {
            $where[]         = 'DIST_CHAN_CODE = @dc_id';
            $params['dc_id'] = ['value' => (string) $filters['dc_id'], 'type' => 'STRING'];
        }
        if (!empty($filters['company_code'])) {
            $where[]                = 'COMPANY_CODE = @company_code';
            $params['company_code'] = ['value' => (string) $filters['company_code'], 'type' => 'STRING'];
        }
        if (empty($filters['all_value_types'])) {
            $where[] = "VALUE_TYPE = 10";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql         = "SELECT COUNT(*) as total FROM " . self::BQ_TABLE . " {$whereClause}";
        $rows        = $this->query($sql, $params);

        return (int) ($rows[0]['total'] ?? 0);
    }

    // =========================================================
    // AVAILABLE YEARS — for dropdown filters in frontend
    // =========================================================
    public function getAvailableYears(): array
    {
        $where  = [];
        $params = [];

        $periodClause = $this->buildPeriodClause($params);
        if ($periodClause !== '') {
            $where[] = $periodClause;
        }
        $where[] = 'FISCAL_YEAR IS NOT NULL';
        $where[] = 'VALUE_TYPE = 10';

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT DISTINCT CAST(FISCAL_YEAR AS STRING) AS year
            FROM " . self::BQ_TABLE . "
            {$whereClause}
            ORDER BY year DESC
        ";

        $rows = $this->query($sql, $params);
        return array_column($rows, 'year');
    }

    // =========================================================
    // AVAILABLE MONTHS for a given year (within period)
    // =========================================================
    public function getAvailableMonths(string $year): array
    {
        $where  = ['FISCAL_YEAR = @year', 'VALUE_TYPE = 10'];
        $params = ['year' => ['value' => $year, 'type' => 'STRING']];

        $periodClause = $this->buildPeriodClause($params);
        if ($periodClause !== '') {
            $where[] = $periodClause;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT DISTINCT CAST(PERIOD_MONTH AS STRING) AS month_num
            FROM " . self::BQ_TABLE . "
            {$whereClause}
            ORDER BY month_num
        ";

        $rows = $this->query($sql, $params);
        return array_column($rows, 'month_num');
    }

    // =========================================================
    // MEMORY ESTIMATE — utility for controllers/API
    // ─────────────────────────────────────────────────────────
    // Returns estimated memory usage based on the active session period.
    // Controllers can include this in API responses so the frontend
    // can display a loading indicator with realistic expectations.
    // =========================================================
    public function estimateMemoryUsage(): array
    {
        $startYear  = $_SESSION['period_start_year'];
        $startMonth = $_SESSION['period_start_month'];
        $endYear    = $_SESSION['period_end_year'];
        $endMonth   = $_SESSION['period_end_month'];

        if (!$startYear || !$startMonth || !$endYear || !$endMonth) {
            return [
                'span_months'    => 0,
                'estimated_rows' => 0,
                'estimated_mb'   => 0,
                'php_limit_mb'   => 1024,
                'pct_of_limit'   => 0,
            ];
        }

        $spanMonths    = ($endYear - $startYear) * 12 + ($endMonth - $startMonth) + 1;
        $rowsPerMonth  = 7656;
        $bytesPerRow   = 1400;  // average after PHP transform
        $estimatedRows = $spanMonths * $rowsPerMonth;
        $estimatedMB   = ($estimatedRows * $bytesPerRow) / (1024 * 1024);
        $phpLimitMB    = (int) ini_get('memory_limit'); // will be overridden by ini_set in controller
        $phpLimitMB    = $phpLimitMB > 0 ? $phpLimitMB : 1024;

        return [
            'period_start'    => sprintf('%04d-%02d', $startYear, $startMonth),
            'period_end'      => sprintf('%04d-%02d', $endYear, $endMonth),
            'span_months'     => $spanMonths,
            'estimated_rows'  => $estimatedRows,
            'dynamic_limit'   => $this->calculateDynamicLimit(),
            'estimated_mb'    => round($estimatedMB, 1),
            'php_limit_mb'    => $phpLimitMB,
            'pct_of_limit'    => $phpLimitMB > 0 ? round(($estimatedMB / $phpLimitMB) * 100, 1) : 0,
        ];
    }

    // =========================================================
    // LEGACY: getFbl5nGl — kept for backward compatibility
    // =========================================================
    public function getFbl5nGl(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        $periodClause = $this->buildPeriodClause($params);
        if ($periodClause !== '') {
            $where[] = $periodClause;
        }

        if (!empty($filters['year'])) {
            $where[]        = 'FISCAL_YEAR = @year';
            $params['year'] = ['value' => (string) $filters['year'], 'type' => 'STRING'];
        }
        if (!empty($filters['month'])) {
            $where[]         = 'PERIOD_MONTH = @month';
            $params['month'] = ['value' => str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT), 'type' => 'STRING'];
        }
        if (!empty($filters['comp_code'])) {
            $where[]             = 'COMPANY_CODE = @comp_code';
            $params['comp_code'] = ['value' => (string) $filters['comp_code'], 'type' => 'STRING'];
        }
        if (!empty($filters['customer_id'])) {
            $where[]               = 'CUSTOMER = @customer_id';
            $params['customer_id'] = ['value' => (string) $filters['customer_id'], 'type' => 'STRING'];
        }

        $whereClause  = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limitClause  = "LIMIT {$limit}";
        $offsetClause = $offset > 0 ? "OFFSET {$offset}" : '';

        $sql = "
            SELECT
                IFNULL(CAST(FISCAL_YEAR    AS STRING), '') AS Year,
                IFNULL(CAST(PERIOD_MONTH   AS STRING), '') AS Month,
                IFNULL(CAST(DATA_DATE      AS STRING), '') AS As_Of_Date,
                IFNULL(CAST(COMPANY_CODE   AS STRING), '') AS CompCode,
                IFNULL(CAST(BUSINESS_AREA  AS STRING), '') AS BA,
                IFNULL(CAST(DIST_CHAN_CODE  AS STRING), '') AS DistChannel,
                IFNULL(CAST(DIVISION_CODE  AS STRING), '') AS Division,
                IFNULL(CAST(CUSTOMER_NAME  AS STRING), '') AS CustomerName,
                IFNULL(CAST(CUSTOMER       AS STRING), '') AS CustomerID,
                IFNULL(CAST(DIST_CHAN_NAME  AS STRING), '') AS Reference,
                IFNULL(CAST(PRODUCT_NUMBER AS STRING), '') AS DocNo,
                IFNULL(CAST(DATA_DATE      AS STRING), '') AS DocDate,
                IFNULL(CAST(DATA_DATE      AS STRING), '') AS PostingDate,
                IFNULL(CAST(DATA_DATE      AS STRING), '') AS DueDateReal,
                IFNULL(CAST(DATA_DATE      AS STRING), '') AS BaselineDate,
                IFNULL(CAST(VALUE_TYPE     AS STRING), '') AS DT
            FROM " . self::BQ_TABLE . "
            {$whereClause}
            ORDER BY DATA_DATE DESC
            {$limitClause}
            {$offsetClause}
        ";

        return $this->query($sql, $params);
    }

    public function countFbl5nGl(array $filters = []): int
    {
        return $this->countSalesData($filters);
    }
}
