<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use InvalidArgumentException;
use RuntimeException;

class SalesForecastWorkbookImportService
{
    private const MONTH_ALIASES = [
        1 => ['jan', 'januari', 'january'],
        2 => ['feb', 'februari', 'february'],
        3 => ['mar', 'maret', 'march'],
        4 => ['apr', 'april'],
        5 => ['mei', 'may'],
        6 => ['jun', 'juni', 'june'],
        7 => ['jul', 'juli', 'july'],
        8 => ['agu', 'agt', 'agustus', 'aug', 'august'],
        9 => ['sep', 'september'],
        10 => ['okt', 'oct', 'oktober', 'october'],
        11 => ['nov', 'november'],
        12 => ['des', 'dec', 'desember', 'december'],
    ];

    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function import(string $filePath, int $year, int $userId): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Tahun forecast harus antara 2000 dan 2100.');
        }

        try {
            $sheets = RawMaterialWorkbookImportService::readWorkbookSheets($filePath);
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException('File harus berupa workbook Excel .xlsx yang valid.', 0, $e);
        }
        $monthRows = self::parseWorkbookSheets($sheets);

        $productsByCode = [];
        $missingCodes = [];
        foreach ($monthRows as $rows) {
            foreach ($rows as $row) {
                $code = $row['code'];
                if (! array_key_exists($code, $productsByCode)) {
                    $productsByCode[$code] = Database::selectOne(
                        'SELECT id FROM products WHERE code = :code',
                        ['code' => $code]
                    );
                }
                if (! $productsByCode[$code]) {
                    $missingCodes[$code] = true;
                }
            }
        }
        if (! empty($missingCodes)) {
            $codes = array_slice(array_keys($missingCodes), 0, 10);
            $suffix = count($missingCodes) > count($codes) ? ', dan lainnya' : '';
            throw new InvalidArgumentException(
                'Nomor material berikut tidak ditemukan di master Produk Jadi: '.implode(', ', $codes).$suffix.'.'
            );
        }

        $valuesByProduct = [];
        foreach ($monthRows as $month => $rows) {
            foreach ($rows as $row) {
                $productId = (int) $productsByCode[$row['code']]['id'];
                $valuesByProduct[$productId]['code'] = $row['code'];
                $valuesByProduct[$productId]['months'][$month] = $row['quantity'];
            }
        }

        $monthNumbers = array_map('intval', array_keys($monthRows));
        sort($monthNumbers);

        Database::beginTransaction();
        try {
            foreach ($valuesByProduct as $productId => $productValues) {
                $existing = Database::selectOne(
                    'SELECT * FROM sales_forecasts WHERE product_id = :product_id AND forecast_year = :forecast_year',
                    ['product_id' => $productId, 'forecast_year' => $year]
                );
                $payload = self::mergeForecastMonths($existing ?? [], $productValues['months']);
                $monthLabels = array_map(
                    static fn (int $month): string => self::MONTH_NAMES[$month],
                    array_keys($productValues['months'])
                );
                $payload = array_merge($payload, [
                    'product_id' => (int) $productId,
                    'forecast_year' => $year,
                    'method' => 'excel_import',
                    'notes' => ! empty($existing['notes'])
                        ? $existing['notes']
                        : 'Diimpor dari Excel: '.implode(', ', $monthLabels),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($existing) {
                    Database::update('sales_forecasts', $payload, 'id = :id', ['id' => $existing['id']]);
                } else {
                    $payload['created_by'] = $userId;
                    $payload['created_at'] = date('Y-m-d H:i:s');
                    Database::insert('sales_forecasts', $payload);
                }
            }

            $importedMonths = array_map(
                static fn (int $month): string => self::MONTH_NAMES[$month],
                $monthNumbers
            );
            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'IMPORT_SALES_FORECAST_XLSX',
                'module' => 'Forecast Sales',
                'description' => 'Mengimpor forecast '.$year.' untuk '.count($valuesByProduct)
                    .' produk pada bulan '.implode(', ', $importedMonths).'.',
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return [
            'products' => count($valuesByProduct),
            'months' => $importedMonths,
        ];
    }

    public static function mergeForecastMonths(array $existing, array $updates): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $key = 'm'.$month;
            $months[$key] = (float) ($existing[$key] ?? 0);
        }
        foreach ($updates as $month => $quantity) {
            if ($month < 1 || $month > 12) {
                throw new InvalidArgumentException('Nomor bulan forecast tidak valid.');
            }
            $months['m'.$month] = (float) $quantity;
        }
        $months['total_12m_qty'] = round(array_sum($months), 2);

        return $months;
    }

    public static function parseWorkbookSheets(array $sheets): array
    {
        $monthlyRows = [];
        foreach ($sheets as $sheetName => $matrix) {
            $month = self::monthFromSheetName((string) $sheetName);
            if ($month === null) {
                continue;
            }
            if (isset($monthlyRows[$month])) {
                throw new InvalidArgumentException('Workbook memiliki lebih dari satu sheet untuk bulan '.self::MONTH_NAMES[$month].'.');
            }

            $header = self::findHeader($matrix);
            if ($header === null) {
                throw new InvalidArgumentException(
                    'Sheet '.$sheetName.' harus memiliki kolom Nomor Material, Nama Material, dan Forecast.'
                );
            }

            $columns = $header;
            $rows = [];
            $seenCodes = [];
            foreach ($matrix as $rowNumber => $cells) {
                $code = trim((string) ($cells[$columns['code']] ?? ''));
                $name = trim((string) ($cells[$columns['name']] ?? ''));
                $quantityText = trim((string) ($cells[$columns['quantity']] ?? ''));
                if ($code === '' && $name === '' && $quantityText === '') {
                    continue;
                }
                if ($code === '' || $name === '') {
                    throw new InvalidArgumentException('Data material pada sheet '.$sheetName.', baris '.((int) $rowNumber + 2).' belum lengkap.');
                }
                if (! is_numeric($quantityText) || ! is_finite((float) $quantityText) || (float) $quantityText < 0) {
                    throw new InvalidArgumentException(
                        'Nilai Forecast pada sheet '.$sheetName.', baris '.((int) $rowNumber + 2).' harus berupa angka nol atau lebih.'
                    );
                }
                if (isset($seenCodes[$code])) {
                    throw new InvalidArgumentException('Nomor material '.$code.' tercatat lebih dari sekali pada sheet '.$sheetName.'.');
                }

                $seenCodes[$code] = true;
                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'quantity' => (float) $quantityText,
                ];
            }
            if (! empty($rows)) {
                $monthlyRows[$month] = $rows;
            }
        }

        if (empty($monthlyRows)) {
            throw new InvalidArgumentException(
                'Tidak ada data forecast yang terbaca. Gunakan nama sheet bulan (mis. Mei) dan kolom Nomor Material, Nama Material, Forecast.'
            );
        }
        ksort($monthlyRows);

        return $monthlyRows;
    }

    private static function findHeader(array $matrix): ?array
    {
        $aliases = [
            'code' => ['nomormaterial', 'nomormat', 'kodematerial', 'kodeproduk', 'productcode', 'code'],
            'name' => ['namamaterial', 'namaproduk', 'namabarang', 'productname', 'name'],
            'quantity' => ['forecast', 'jumlahforecast', 'qtyforecast', 'quantityforecast'],
        ];
        $firstRow = reset($matrix);
        if (! is_array($firstRow)) {
            return null;
        }
        $columns = [];
        foreach (array_keys($firstRow) as $column) {
            $header = self::normalizeToken((string) $column);
            foreach ($aliases as $key => $acceptedValues) {
                if (in_array($header, $acceptedValues, true)) {
                    $columns[$key] = $column;
                }
            }
        }

        return isset($columns['code'], $columns['name'], $columns['quantity']) ? $columns : null;
    }

    private static function monthFromSheetName(string $sheetName): ?int
    {
        $tokens = preg_split('/[^a-z]+/i', mb_strtolower($sheetName, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        foreach (self::MONTH_ALIASES as $month => $aliases) {
            if (array_intersect($tokens ?: [], $aliases) !== []) {
                return $month;
            }
        }

        return null;
    }

    private static function normalizeToken(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($value), 'UTF-8')) ?? '';
    }
}
