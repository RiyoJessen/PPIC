<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use RuntimeException;
use ZipArchive;

class RawMaterialWorkbookImportService
{
    public static function readWorkbookSheets(string $path): array
    {
        return self::readXlsxSheets($path);
    }

    public static function import(string $leadTimePath, ?string $usagePath, int $userId): array
    {
        $leadRows = self::readLeadTimeWorkbook($leadTimePath);
        if (empty($leadRows)) {
            throw new RuntimeException('Workbook lead time tidak memiliki data bahan baku yang valid.');
        }

        $warehouse = Database::selectOne('SELECT id FROM warehouses ORDER BY id ASC LIMIT 1');
        $warehouseId = (int) ($warehouse['id'] ?? 1);
        $categoryCache = [];
        $materialCount = 0;
        $supplierCount = 0;
        $seenMaterialCodes = [];
        $materialIds = [];
        $supplierIdsByName = [];
        $materialSpecifications = [];
        foreach ($leadRows as $leadRow) {
            $code = trim((string) $leadRow['code']);
            $specification = trim((string) $leadRow['material']);
            if ($code === '' || $specification === '') continue;
            $materialSpecifications[$code][$specification] = true;
        }

        Database::beginTransaction();
        try {
            foreach ($leadRows as $row) {
                $code = trim((string) $row['code']);
                $name = trim((string) $row['name']);
                if ($code === '' || $name === '') continue;
                if (isset($seenMaterialCodes[$code])) continue;
                $seenMaterialCodes[$code] = true;

                $categoryId = null;
                $categoryName = trim((string) ($row['category'] ?? ''));
                if ($categoryName !== '') {
                    if (!isset($categoryCache[$categoryName])) {
                        $category = Database::selectOne('SELECT id FROM categories WHERE name = :name', ['name' => $categoryName]);
                        if (!$category) {
                            $categoryId = Database::insert('categories', [
                                'code' => self::makeCode($categoryName),
                                'name' => $categoryName,
                                'description' => 'Kategori bahan baku hasil impor workbook lead time.',
                            ]);
                        } else {
                            $categoryId = (int) $category['id'];
                        }
                        $categoryCache[$categoryName] = $categoryId;
                    } else {
                        $categoryId = (int) $categoryCache[$categoryName];
                    }
                }

                $supplierId = null;
                $vendorName = trim((string) ($row['vendor'] ?? ''));
                if ($vendorName !== '') {
                    if (isset($supplierIdsByName[$vendorName])) {
                        $supplierId = $supplierIdsByName[$vendorName];
                    } else {
                        $supplier = Database::selectOne('SELECT id FROM suppliers WHERE name = :name', ['name' => $vendorName]);
                        if ($supplier) {
                            $supplierId = (int) $supplier['id'];
                        } else {
                            $supplierId = Database::insert('suppliers', [
                                'code' => self::makeCode($vendorName),
                                'name' => $vendorName,
                                'lead_time_days' => 7,
                                'rating' => 4.5,
                            ]);
                            $supplierCount++;
                        }
                        $supplierIdsByName[$vendorName] = $supplierId;
                    }
                }

                $minimum = (float) $row['minimum'];
                $leadTime = (int) $row['lead_time'];
                $specifications = array_keys($materialSpecifications[$code] ?? []);
                $materialName = !empty($specifications)
                    ? implode(' / ', array_unique($specifications))
                    : trim((string) $row['material']);
                $existing = Database::selectOne('SELECT id FROM materials WHERE code = :code', ['code' => $code]);
                $minimumStock = !empty($row['minimum_note']) ? null : $minimum;
                $values = [
                    'name' => $name,
                    'material_name' => $materialName,
                    'minimum_stock_note' => $row['minimum_note'] ?? null,
                    'category_id' => $categoryId,
                    'unit' => 'Kg',
                    'min_stock' => $minimumStock,
                    'lead_time_days' => $leadTime,
                    'moq' => 0,
                    'order_multiple' => 1,
                    'preferred_supplier_id' => $supplierId,
                    'warehouse_id' => $warehouseId,
                    'is_active' => 1,
                ];

                if ($existing) {
                    $materialId = (int) $existing['id'];
                    Database::update('materials', $values, 'id = :id', ['id' => $materialId]);
                } else {
                    $materialId = Database::insert('materials', array_merge(['code' => $code], $values));
                }
                $materialIds[$code] = $materialId;
                $materialCount++;
            }

            Database::statement('DELETE FROM material_suppliers');
            $vendorRowCount = 0;
            foreach ($leadRows as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                $materialId = $materialIds[$code] ?? 0;
                if ($materialId <= 0) continue;

                $vendorName = trim((string) ($row['vendor'] ?? ''));
                $supplierId = null;
                if ($vendorName !== '') {
                    if (isset($supplierIdsByName[$vendorName])) {
                        $supplierId = $supplierIdsByName[$vendorName];
                    } else {
                        $supplier = Database::selectOne('SELECT id FROM suppliers WHERE name = :name', ['name' => $vendorName]);
                        if ($supplier) {
                            $supplierId = (int) $supplier['id'];
                        } else {
                            $supplierId = Database::insert('suppliers', [
                                'code' => self::makeCode($vendorName),
                                'name' => $vendorName,
                                'lead_time_days' => 7,
                                'rating' => 4.5,
                            ]);
                            $supplierCount++;
                        }
                        $supplierIdsByName[$vendorName] = $supplierId;
                    }
                }

                Database::insert('material_suppliers', [
                    'material_id' => $materialId,
                    'supplier_id' => $supplierId,
                    'material_specification' => trim((string) ($row['material'] ?? '')),
                    'lead_time_days' => (int) ($row['lead_time'] ?? 0),
                ]);
                $vendorRowCount++;
            }

            $usageRows = $usagePath ? self::readUsageWorkbook($usagePath) : [];
            $usageCount = 0;
            if (!empty($usageRows)) {
                Database::statement(
                    "DELETE FROM stock_movements WHERE reference_type = 'MB51_IMPORT'"
                );

                foreach ($usageRows as $usage) {
                    $material = Database::selectOne(
                        'SELECT id, unit FROM materials WHERE code = :code',
                        ['code' => (string) $usage['code']]
                    );
                    if (!$material) continue;

                    $qty = abs((float) $usage['quantity']);
                    if ($qty <= 0) continue;
                    Database::insert('stock_movements', [
                        'material_id' => (int) $material['id'],
                        'warehouse_id' => $warehouseId,
                        'batch_id' => null,
                        'movement_type' => 'out_production',
                        'reference_type' => 'MB51_IMPORT',
                        'reference_number' => (string) ($usage['document'] ?: ('MB51-' . $usage['code'])),
                        'quantity' => $qty,
                        'unit' => (string) ($material['unit'] ?: 'Kg'),
                        'before_stock' => 0,
                        'after_stock' => 0,
                        'notes' => 'Pemakaian aktual hasil impor workbook MB51',
                        'user_id' => $userId,
                        'created_at' => (string) $usage['date'] . ' 08:00:00',
                    ]);
                    $usageCount++;
                }
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'IMPORT_RAW_MATERIAL_WORKBOOKS',
                'module' => 'Master Data',
                'description' => "Impor workbook raw material: {$materialCount} master bahan baku, {$supplierCount} supplier baru, {$usageCount} transaksi pemakaian.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return [
            'materials' => $materialCount,
            'suppliers' => $supplierCount,
            'vendor_rows' => $vendorRowCount,
            'usage_rows' => $usageCount,
        ];
    }

    private static function readLeadTimeWorkbook(string $path): array
    {
        $sheets = self::readXlsxSheets($path);
        $sheet = $sheets['Lead Time RM'] ?? reset($sheets);
        if (!is_array($sheet)) return [];

        $rows = [];
        $category = '';
        foreach ($sheet as $row) {
            $code = trim((string) ($row['NOMOR MATERIAL'] ?? ''));
            $name = trim((string) ($row['DISPLAY SAP'] ?? ''));
            $materialName = trim((string) ($row['MATERIAL'] ?? ''));
            $vendor = trim((string) ($row['VENDOR'] ?? ''));
            $leadText = trim((string) ($row['LEADTIME (HARI)'] ?? ''));
            $minimumText = trim((string) ($row['MINIMUM STOCK'] ?? ''));

            if ($code !== '' && $name === '' && $materialName === '' && $vendor === '' && $leadText === '' && $minimumText === '') {
                $category = $code;
                continue;
            }
            if ($code === '' || $name === '') continue;
            if (!preg_match('/^\d+$/', $code)) continue;

            preg_match('/\d+/', $leadText, $leadMatch);
            $leadTime = isset($leadMatch[0]) ? (int) $leadMatch[0] : 0;

            $minimum = 0.0;
            $minimumNote = strtolower($minimumText) === 'conditional' ? 'Conditional' : null;
            if ($minimumNote === null && is_numeric(str_replace([',', ' '], ['', ''], $minimumText))) {
                $minimum = (float) str_replace([',', ' '], ['', ''], $minimumText);
            }

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'material' => $materialName,
                'vendor' => $vendor,
                'lead_time' => $leadTime,
                'minimum' => $minimum,
                'minimum_note' => $minimumNote,
                'category' => $category,
            ];
        }
        return $rows;
    }

    private static function readUsageWorkbook(string $path): array
    {
        $sheets = self::readXlsxSheets($path);
        $rows = [];
        foreach ($sheets as $sheetName => $sheet) {
            foreach ($sheet as $row) {
                $code = trim((string) ($row['Material'] ?? $sheetName));
                $code = preg_replace('/\.0$/', '', $code);
                $postingDate = trim((string) ($row['Posting Date'] ?? ''));
                $document = trim((string) ($row['Material Document'] ?? ''));
                $qtyText = trim((string) ($row['Qty in Un. of Entry'] ?? ''));
                if ($postingDate === '' || $qtyText === '') continue;

                $qtyText = str_replace([',', ' '], ['', ''], $qtyText);
                if (!is_numeric($qtyText)) continue;
                $qty = (float) $qtyText;
                if ($qty >= 0) continue;

                $date = self::normalizeDate($postingDate);
                if ($date === null) continue;
                $rows[] = [
                    'code' => $code,
                    'date' => $date,
                    'document' => $document,
                    'quantity' => abs($qty),
                ];
            }
        }
        return $rows;
    }

    private static function readXlsxSheets(string $path): array
    {
        if (!is_file($path)) throw new RuntimeException('File workbook tidak ditemukan.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File workbook bukan XLSX yang dapat dibaca.');

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared) {
                foreach ($shared->si as $si) {
                    $text = '';
                    foreach ($si->t as $t) $text .= (string) $t;
                    if (isset($si->r)) foreach ($si->r as $run) $text .= (string) $run->t;
                    $sharedStrings[] = $text;
                }
            }
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            $zip->close();
            throw new RuntimeException('Struktur workbook XLSX tidak valid.');
        }
        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        $relationshipTargets = [];
        if ($rels) {
            foreach ($rels->Relationship as $relationship) {
                $relationshipTargets[(string) $relationship['Id']] = ltrim((string) $relationship['Target'], '/');
            }
        }

        $sheets = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $sheetName = (string) $sheet['name'];
            $relId = (string) $sheet->attributes('r', true)['id'];
            $target = $relationshipTargets[$relId] ?? '';
            if ($target === '' && $relId !== '') $target = 'xl/worksheets/sheet1.xml';
            if (!str_starts_with($target, 'xl/')) $target = 'xl/' . $target;

            $sheetXml = $zip->getFromName($target);
            if ($sheetXml === false) continue;
            $sheets[$sheetName] = self::parseWorksheetXml($sheetXml, $sharedStrings);
        }

        $zip->close();
        return $sheets;
    }

    private static function parseWorksheetXml(string $xml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($xml);
        if (!$sheet || !isset($sheet->sheetData)) return [];

        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)/', $ref, $match);
                $col = isset($match[1]) ? self::columnIndex($match[1]) : count($values);
                $type = (string) $cell['t'];
                $value = '';
                if ($type === 's') {
                    $index = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$index] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $values[$col] = $value;
            }
            $rowNumber = (int) ($row['r'] ?? 0);
            $matrix[$rowNumber > 0 ? $rowNumber - 1 : count($matrix)] = $values;
        }
        if (empty($matrix)) return [];

        if (isset($sheet->mergeCells)) {
            foreach ($sheet->mergeCells->mergeCell as $mergeCell) {
                $range = (string) $mergeCell['ref'];
                if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $parts)) continue;
                $startCol = self::columnIndex($parts[1]);
                $startRow = (int) $parts[2] - 1;
                $endCol = self::columnIndex($parts[3]);
                $endRow = (int) $parts[4] - 1;
                $value = $matrix[$startRow][$startCol] ?? '';
                if ($value === '') continue;
                for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                    for ($colIndex = $startCol; $colIndex <= $endCol; $colIndex++) {
                        $matrix[$rowIndex][$colIndex] = $value;
                    }
                }
            }
        }

        $headers = [];
        foreach ($matrix[0] ?? [] as $col => $header) {
            $header = trim((string) $header);
            if ($header !== '') $headers[$col] = $header;
        }

        $result = [];
        foreach ($matrix as $rowIndex => $values) {
            if ($rowIndex === 0) continue;
            $row = [];
            foreach ($headers as $col => $header) {
                $row[$header] = isset($values[$col]) ? (string) $values[$col] : '';
            }
            $result[] = $row;
        }
        return $result;
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return $index - 1;
    }

    private static function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if (is_numeric($date) && (float) $date > 20000) {
            $serial = (int) round((float) $date);
            $base = new \DateTimeImmutable('1899-12-30');
            return $base->modify("+{$serial} days")->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $date, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    private static function makeCode(string $name): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)));
        $code = trim((string) $code, '-');
        return substr($code !== '' ? $code : 'CATEGORY', 0, 50);
    }
}
