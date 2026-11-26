<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Database\Database;
use App\Services\FormulaCatalogService;
use App\Services\FormulaRequirementCalculator;
use App\Services\InventoryService;

class MasterController extends Controller
{
    public static function materialOverview(): array
    {
        return [
            'materials' => self::materials()['materials'],
            'products' => self::products()['products'],
            'boms' => self::boms()['boms'],
            'sales_forecasts' => Database::select("
                SELECT sf.*, p.code as product_code, p.name as product_name, p.unit as product_unit
                FROM sales_forecasts sf
                JOIN products p ON p.id = sf.product_id
                ORDER BY sf.forecast_year DESC, p.name ASC
            "),
            'sales_histories' => Database::select("
                SELECT sh.*, p.code as product_code, p.name as product_name, p.unit as product_unit
                FROM sales_histories sh
                JOIN products p ON p.id = sh.product_id
                ORDER BY sh.sale_date DESC, sh.id DESC
                LIMIT 100
            "),
        ];
    }

    public static function materials(): array
    {
        $materials = InventoryService::getMaterialStockSummary();
        $vendorRowsCount = array_sum(array_map(
            static fn(array $material): int => max(1, count($material['vendor_options'] ?? [])),
            $materials
        ));

        return [
            'materials' => $materials,
            'vendor_rows_count' => $vendorRowsCount,
        ] + self::materialFormOptions();
    }

    public static function materialFormOptions(): array
    {
        return [
            'categories' => Database::select("SELECT * FROM categories ORDER BY name ASC"),
            'warehouses' => Database::select("SELECT * FROM warehouses ORDER BY name ASC"),
            'suppliers' => Database::select("SELECT * FROM suppliers ORDER BY name ASC"),
        ];
    }

    public static function materialDetail(int $materialId): array
    {
        $material = InventoryService::getMaterialStockSummary($materialId);
        if (empty($material)) {
            throw new \RuntimeException('Bahan baku tidak ditemukan.');
        }

        $arrivals = Database::select("
            SELECT b.*, w.name as warehouse_name,
                   sm.reference_number, sm.notes as movement_notes
            FROM inventory_batches b
            LEFT JOIN warehouses w ON w.id = b.warehouse_id
            LEFT JOIN stock_movements sm
                ON sm.batch_id = b.id
                AND sm.movement_type IN ('in_manual', 'in_purchase')
            WHERE b.material_id = :material_id
            ORDER BY b.received_date DESC, b.id DESC
        ", ['material_id' => $materialId]);

        $products = Database::select("
            SELECT p.name as product_name, p.code as product_code,
                   b.version, b.batch_size, b.unit as batch_unit,
                   b.is_active, bi.quantity_per_batch, bi.unit as material_unit
            FROM bom_items bi
            JOIN boms b ON b.id = bi.bom_id
            JOIN products p ON p.id = b.product_id
            WHERE bi.material_id = :material_id
            ORDER BY p.name ASC, b.id DESC
        ", ['material_id' => $materialId]);

        return [
            'material' => $material,
            'arrivals' => $arrivals,
            'products' => $products,
        ];
    }

    public static function storeMaterial(array $data, int $userId): int
    {
        $id = Database::insert('materials', [
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'material_name' => trim($data['material_name'] ?? ''),
            'category_id' => (int) $data['category_id'] ?: null,
            'unit' => trim($data['unit']),
            'min_stock' => (float) ($data['min_stock'] ?? 0),
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 7),
            'moq' => (float) ($data['moq'] ?? 0),
            'order_multiple' => (float) ($data['order_multiple'] ?? 1),
            'unit_cost' => (float) ($data['unit_cost'] ?? 0),
            'preferred_supplier_id' => (int) $data['preferred_supplier_id'] ?: null,
            'warehouse_id' => (int) $data['warehouse_id'] ?: null,
            'storage_condition' => trim($data['storage_condition'] ?? ''),
            'is_active' => 1,
        ]);

        // Jika diinput stok awal, langsung catat ke inventory_batches & stock_movements
        $initialStock = max(0.0, (float) ($data['initial_stock'] ?? 0));
        if ($initialStock > 0) {
            $warehouseId = (int) ($data['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                $firstWh = Database::selectOne("SELECT id FROM warehouses ORDER BY id ASC LIMIT 1");
                $warehouseId = (int) ($firstWh['id'] ?? 1);
            }
            $batchNo = 'LOT-INIT-' . date('ymd') . '-' . rand(100, 999);
            $batchId = Database::insert('inventory_batches', [
                'material_id' => $id,
                'warehouse_id' => $warehouseId,
                'batch_number' => $batchNo,
                'coa_number' => 'INIT-' . date('ymd'),
                'expired_date' => date('Y-m-d', strtotime('+365 days')),
                'initial_qty' => $initialStock,
                'current_qty' => $initialStock,
                'allocated_qty' => 0,
                'status' => 'active',
                'received_date' => date('Y-m-d'),
            ]);
            Database::insert('stock_movements', [
                'material_id' => $id,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'movement_type' => 'in_manual',
                'reference_type' => 'INITIAL_STOCK',
                'reference_number' => $batchNo,
                'quantity' => $initialStock,
                'unit' => trim($data['unit']),
                'before_stock' => 0,
                'after_stock' => $initialStock,
                'notes' => 'Stok awal saat pendaftaran master bahan baku',
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'CREATE_MATERIAL',
            'module' => 'Master Data',
            'description' => "Menambahkan master bahan baku: {$data['name']} ({$data['code']}).",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $userName = $_SESSION['user_name'] ?? 'R&D Specialist';
        \App\Services\NotificationService::record(
            'RD',
            'MATERIAL_CREATED',
            "Master Bahan Baku Baru: {$data['name']}",
            "Divisi R&D ({$userName}) mendaftarkan master bahan baku baru: {$data['name']} ({$data['code']}). Estimasi harga Rp " . number_format((float)$data['unit_cost'], 0, ',', '.') . "/{$data['unit']}. Siap digunakan sebagai referensi perencanaan material.",
            "/master/materials",
            $userId,
            $userName,
            true,
            'PPIC'
        );

        return $id;
    }

    public static function updateMaterialStock(array $data, int $userId): void
    {
        Database::beginTransaction();
        try {
            $materialId = (int) ($data['material_id'] ?? 0);
            $qty = max(0.0, (float) ($data['quantity'] ?? 0));
            $type = (($data['stock_action'] ?? 'in') === 'out') ? 'out' : 'in';
            $notes = trim((string) ($data['notes'] ?? ($type === 'in' ? 'Penambahan stok bahan baku' : 'Pengurangan / pemakaian stok bahan baku')));

            if ($materialId <= 0 || $qty <= 0) {
                throw new \InvalidArgumentException("Bahan baku dan jumlah stok harus lebih dari 0.");
            }

            $mat = Database::selectOne("SELECT * FROM materials WHERE id = :id", ['id' => $materialId]);
            if (!$mat) {
                throw new \RuntimeException("Bahan baku tidak ditemukan.");
            }

            $warehouseId = (int) ($data['warehouse_id'] ?? $mat['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                $firstWh = Database::selectOne("SELECT id FROM warehouses ORDER BY id ASC LIMIT 1");
                $warehouseId = (int) ($firstWh['id'] ?? 1);
            }

            $summaryBefore = InventoryService::getMaterialStockSummary($materialId);
            $beforeStock = (float) ($summaryBefore['physical_stock'] ?? 0);

            if ($type === 'in') {
                $batchNo = trim((string) ($data['batch_number'] ?? ''));
                if ($batchNo === '') {
                    $batchNo = 'LOT-' . date('ymd') . '-' . rand(100, 999);
                }
                $coaNo = trim((string) ($data['coa_number'] ?? '')) ?: ('COA-' . date('ymd') . '-' . rand(10, 99));
                $expDate = !empty($data['expired_date']) ? $data['expired_date'] : date('Y-m-d', strtotime('+365 days'));

                $batchId = Database::insert('inventory_batches', [
                    'material_id' => $materialId,
                    'warehouse_id' => $warehouseId,
                    'batch_number' => $batchNo,
                    'coa_number' => $coaNo,
                    'expired_date' => $expDate,
                    'initial_qty' => $qty,
                    'current_qty' => $qty,
                    'allocated_qty' => 0,
                    'status' => 'active',
                    'received_date' => date('Y-m-d'),
                ]);

                $afterStock = $beforeStock + $qty;
                Database::insert('stock_movements', [
                    'material_id' => $materialId,
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $batchId,
                    'movement_type' => 'in_manual',
                    'reference_type' => 'STOCK_UPDATE_IN',
                    'reference_number' => $batchNo,
                    'quantity' => $qty,
                    'unit' => $mat['unit'],
                    'before_stock' => $beforeStock,
                    'after_stock' => $afterStock,
                    'notes' => $notes,
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                // Kurangi stok secara FEFO dari batch aktif
                $batches = Database::select(
                    "SELECT * FROM inventory_batches WHERE material_id = :mid AND status = 'active' AND current_qty > 0 ORDER BY expired_date ASC, id ASC",
                    ['mid' => $materialId]
                );
                $remainingToDeduct = $qty;
                $lastBatchId = null;
                foreach ($batches as $b) {
                    if ($remainingToDeduct <= 0) break;
                    $deduct = min((float) $b['current_qty'], $remainingToDeduct);
                    $newQty = max(0.0, (float) $b['current_qty'] - $deduct);
                    Database::update('inventory_batches', [
                        'current_qty' => $newQty,
                        'status' => ($newQty <= 0) ? 'depleted' : 'active',
                    ], 'id = :id', ['id' => $b['id']]);
                    $remainingToDeduct -= $deduct;
                    $lastBatchId = (int) $b['id'];
                }

                $afterStock = max(0.0, $beforeStock - $qty);
                Database::insert('stock_movements', [
                    'material_id' => $materialId,
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $lastBatchId,
                    'movement_type' => 'out_adjustment',
                    'reference_type' => 'STOCK_UPDATE_OUT',
                    'reference_number' => 'OUT-' . date('ymd') . '-' . rand(100, 999),
                    'quantity' => $qty,
                    'unit' => $mat['unit'],
                    'before_stock' => $beforeStock,
                    'after_stock' => $afterStock,
                    'notes' => $notes,
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => $type === 'in' ? 'MATERIAL_STOCK_IN' : 'MATERIAL_STOCK_OUT',
                'module' => 'Master Material',
                'description' => "Update stok {$mat['name']} (" . ($type === 'in' ? '+' : '-') . "{$qty} {$mat['unit']}). Catatan: {$notes}",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function storeProduct(array $data, int $userId): int
    {
        $initialStock = $data['initial_stock'] ?? 0;
        $standardBatchSize = $data['standard_batch_size'] ?? 1000;
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? 'Kg'));
        if ($code === '' || $name === '' || $unit === '') {
            throw new \InvalidArgumentException('Kode, nama, dan satuan produk wajib diisi.');
        }
        if (!is_numeric($initialStock) || !is_finite((float) $initialStock) || (float) $initialStock < 0) {
            throw new \InvalidArgumentException('Stok awal Finish Good harus berupa angka nol atau lebih.');
        }
        if (!is_numeric($standardBatchSize) || !is_finite((float) $standardBatchSize) || (float) $standardBatchSize <= 0) {
            throw new \InvalidArgumentException('Batch standar produk harus berupa angka lebih dari nol.');
        }

        Database::beginTransaction();
        try {
            $id = Database::insert('products', [
                'code' => $code,
                'name' => $name,
                'category' => trim($data['category'] ?? 'Koagulan Cair'),
                'unit' => $unit,
                'standard_batch_size' => (float) $standardBatchSize,
                'description' => trim($data['description'] ?? ''),
                'is_active' => 1,
            ]);

            Database::insert('product_stock_balances', [
                'product_id' => $id,
                'current_qty' => (float) $initialStock,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'CREATE_PRODUCT',
                'module' => 'Master Data',
                'description' => "Menambahkan master produk jadi: {$data['name']} ({$data['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function storeSupplier(array $data, int $userId): int
    {
        $id = Database::insert('suppliers', [
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'contact_person' => trim($data['contact_person'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 7),
            'rating' => (float) ($data['rating'] ?? 4.5),
        ]);

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'CREATE_SUPPLIER',
            'module' => 'Master Data',
            'description' => "Menambahkan supplier rekanan baru: {$data['name']} ({$data['code']}).",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $userName = $_SESSION['user_name'] ?? 'PPIC Officer';
        \App\Services\NotificationService::record(
            'PPIC',
            'SUPPLIER_CREATED',
            "Supplier Rekanan Baru: {$data['name']}",
            "PPIC ({$userName}) menambahkan supplier sebagai referensi analisis: {$data['name']} ({$data['code']}) dengan Lead Time {$data['lead_time_days']} hari.",
            "/master/suppliers",
            $userId,
            $userName,
            true,
            'PPIC'
        );

        return $id;
    }

    public static function storeBom(array $data, int $userId): int
    {
        Database::beginTransaction();
        try {
            $productId = (int) ($data['product_id'] ?? 0);
            $version = trim($data['version'] ?? 'v1.0');
            $batchSize = (float) ($data['batch_size'] ?? 1000);
            $unit = trim($data['unit'] ?? 'Kg');
            $yieldPct = (float) ($data['yield_pct'] ?? 98.0);
            $scrapPct = (float) ($data['scrap_pct'] ?? 2.0);
            $notes = trim($data['notes'] ?? '');
            $product = Database::selectOne('SELECT id FROM products WHERE id = :id', ['id' => $productId]);
            if (!$product || $version === '' || !is_finite($batchSize) || $batchSize <= 0
                || !is_finite($yieldPct) || $yieldPct <= 0 || $yieldPct > 100
                || !is_finite($scrapPct) || $scrapPct < 0) {
                throw new \InvalidArgumentException('Data produk, versi, ukuran batch, yield, atau scrap formula tidak valid.');
            }

            $rawItems = $data['items'] ?? [];
            if (!is_array($rawItems) || empty($rawItems)) {
                throw new \InvalidArgumentException('Formula harus memiliki minimal satu bahan baku.');
            }
            $validItems = [];
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Format item formula tidak valid.');
                }
                $materialId = (int) ($item['material_id'] ?? 0);
                $quantity = $item['quantity_per_batch'] ?? null;
                $isBlankQuantity = $quantity === null || trim((string) $quantity) === '';
                if ($materialId <= 0 && $isBlankQuantity) {
                    continue;
                }
                if ($materialId <= 0 || !is_numeric($quantity) || !is_finite((float) $quantity) || (float) $quantity <= 0) {
                    throw new \InvalidArgumentException('Setiap bahan formula harus memiliki material dan jumlah per batch yang valid.');
                }
                $material = Database::selectOne('SELECT id, unit FROM materials WHERE id = :id', ['id' => $materialId]);
                if (!$material) {
                    throw new \InvalidArgumentException('Formula berisi bahan baku yang tidak terdaftar.');
                }
                $validItems[] = [
                    'material_id' => $materialId,
                    'quantity_per_batch' => (float) $quantity,
                    'unit' => $material['unit'],
                    'notes' => trim((string) ($item['notes'] ?? '')),
                ];
            }
            if (empty($validItems)) {
                throw new \InvalidArgumentException('Formula harus memiliki minimal satu bahan baku dan jumlah per batch yang valid.');
            }

            Database::update('boms', ['is_active' => 0], 'product_id = :product_id', ['product_id' => $productId]);
            $bomId = Database::insert('boms', [
                'product_id' => $productId,
                'version' => $version,
                'batch_size' => $batchSize,
                'unit' => $unit,
                'yield_pct' => $yieldPct,
                'scrap_pct' => $scrapPct,
                'is_active' => 1,
                'notes' => $notes,
            ]);

            foreach ($validItems as $item) {
                Database::insert('bom_items', [
                    'bom_id' => $bomId,
                    'material_id' => $item['material_id'],
                    'quantity_per_batch' => $item['quantity_per_batch'],
                    'unit' => $item['unit'],
                    'notes' => $item['notes'],
                ]);
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'CREATE_BOM',
                'module' => 'Master Data',
                'description' => "Membuat formula BOM baru {$version} untuk produk ID #{$productId}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();

            $userName = $_SESSION['user_name'] ?? 'R&D Specialist';
            $prod = Database::selectOne("SELECT name, code FROM products WHERE id = :id", ['id' => $productId]);
            $prodName = $prod['name'] ?? 'Produk';
            \App\Services\NotificationService::record(
                'RD',
                'BOM_CREATED',
                "Formula BOM Kimia Baru: {$version} ({$prodName})",
                "Divisi R&D ({$userName}) merancang formula BOM {$version} baru untuk produk {$prodName} (Batch Standar: " . number_format($batchSize, 0, ',', '.') . " {$unit}). PPIC siap menggunakannya pada MPS.",
                "/master/boms",
                $userId,
                $userName,
                true,
                'PPIC'
            );

            return $bomId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function products(): array
    {
        $products = Database::select("
            SELECT p.*,
                   (SELECT COUNT(*) FROM boms WHERE product_id = p.id AND is_active = 1) as has_active_bom,
                   (SELECT version FROM boms WHERE product_id = p.id AND is_active = 1 ORDER BY id DESC LIMIT 1) as active_bom_version,
                   (SELECT fc.formula_code
                    FROM boms active_bom
                    LEFT JOIN formula_catalog fc ON fc.id = active_bom.source_formula_id
                    WHERE active_bom.product_id = p.id AND active_bom.is_active = 1
                    ORDER BY active_bom.id DESC LIMIT 1) as source_formula_code,
                   COALESCE(psb.current_qty, 0) as finished_good_stock
            FROM products p
            LEFT JOIN product_stock_balances psb ON psb.product_id = p.id
            ORDER BY p.id ASC
        ");

        return ['products' => $products];
    }

    public static function updateProductStock(array $data, int $userId): void
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $quantityValue = $data['quantity'] ?? null;
        if ($productId <= 0 || !is_numeric($quantityValue) || !is_finite((float) $quantityValue) || (float) $quantityValue < 0) {
            throw new \InvalidArgumentException('Produk dan stok Finish Good harus valid (stok tidak boleh negatif).');
        }

        Database::beginTransaction();
        try {
            $product = Database::selectOne('SELECT id, name, code, unit FROM products WHERE id = :id', ['id' => $productId]);
            if (!$product) {
                throw new \RuntimeException('Produk jadi tidak ditemukan.');
            }

            $existing = Database::selectOne(
                'SELECT current_qty FROM product_stock_balances WHERE product_id = :product_id',
                ['product_id' => $productId]
            );
            $oldQuantity = (float) ($existing['current_qty'] ?? 0);
            $newQuantity = (float) $quantityValue;

            if ($existing) {
                Database::update('product_stock_balances', [
                    'current_qty' => $newQuantity,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'product_id = :product_id', ['product_id' => $productId]);
            } else {
                Database::insert('product_stock_balances', [
                    'product_id' => $productId,
                    'current_qty' => $newQuantity,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'UPDATE_FINISHED_GOOD_STOCK',
                'module' => 'Master Produk Jadi',
                'description' => "Memperbarui stok Finish Good {$product['name']} ({$product['code']}) dari {$oldQuantity} menjadi {$newQuantity} {$product['unit']}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function productFormulaDetail(int $productId): array
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Produk jadi tidak valid.');
        }

        $product = Database::selectOne("
            SELECT p.*, COALESCE(psb.current_qty, 0) as finished_good_stock
            FROM products p
            LEFT JOIN product_stock_balances psb ON psb.product_id = p.id
            WHERE p.id = :id
        ", ['id' => $productId]);
        if (!$product) {
            throw new \RuntimeException('Produk jadi tidak ditemukan.');
        }

        $bom = Database::selectOne("
            SELECT b.*, fc.formula_code as source_formula_code,
                   fc.product_name as source_formula_product_name
            FROM boms b
            LEFT JOIN formula_catalog fc ON fc.id = b.source_formula_id
            WHERE b.product_id = :product_id AND b.is_active = 1
            ORDER BY b.id DESC
            LIMIT 1
        ", ['product_id' => $productId]);

        $calculation = null;
        if ($bom) {
            $items = Database::select("
                SELECT bi.*, m.code as material_code, m.name as material_name,
                       m.unit as material_unit, fci.fraction as source_fraction,
                       COALESCE((
                           SELECT SUM(ib.current_qty)
                           FROM inventory_batches ib
                           WHERE ib.material_id = m.id AND ib.status = 'active'
                       ), 0) as physical_stock,
                       COALESCE((
                           SELECT SUM(ib.allocated_qty)
                           FROM inventory_batches ib
                           WHERE ib.material_id = m.id AND ib.status = 'active'
                       ), 0) as allocated_stock
                FROM bom_items bi
                JOIN materials m ON m.id = bi.material_id
                LEFT JOIN formula_catalog_items fci ON fci.id = bi.source_formula_item_id
                WHERE bi.bom_id = :bom_id
                ORDER BY bi.id ASC
            ", ['bom_id' => $bom['id']]);

            $totalPercentage = 0.0;
            foreach ($items as &$item) {
                $item['available_stock'] = max(
                    0.0,
                    (float) $item['physical_stock'] - (float) $item['allocated_stock']
                );
                $item['formula_percentage'] = $item['source_fraction'] !== null
                    ? (float) $item['source_fraction'] * 100
                    : ((float) $bom['batch_size'] > 0
                        ? ((float) $item['quantity_per_batch'] / (float) $bom['batch_size']) * 100
                        : 0.0);
                $totalPercentage += $item['formula_percentage'];
            }
            unset($item);

            $targetOutput = (float) $product['standard_batch_size'];
            if ($targetOutput <= 0) {
                $targetOutput = (float) $bom['batch_size'] * ((float) $bom['yield_pct'] / 100);
            }
            $calculation = FormulaRequirementCalculator::calculate(
                $targetOutput,
                (float) $bom['batch_size'],
                (float) $bom['yield_pct'],
                $items
            );
            $bom['items'] = $calculation['items'];
            $bom['total_formula_percentage'] = $totalPercentage;
            $bom['expected_batch_output'] = $calculation['expected_batch_output'];
            $bom['default_target_output'] = $targetOutput;
        }

        return [
            'product' => $product,
            'bom' => $bom,
            'materials' => Database::select('SELECT id, code, name, unit FROM materials WHERE is_active = 1 ORDER BY name ASC'),
            'formula_catalog' => FormulaCatalogService::getCatalog(),
        ];
    }

    public static function storeManualProductFormula(array $data, int $userId): int
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $version = trim((string) ($data['version'] ?? 'manual-v1'));
        $rawItems = $data['items'] ?? null;
        if ($productId <= 0 || !\is_array($rawItems) || $rawItems === []) {
            throw new \InvalidArgumentException('Pilih produk jadi dan masukkan minimal satu bahan formula.');
        }
        if ($version === '' || \strlen($version) > 20) {
            throw new \InvalidArgumentException('Versi formula wajib diisi dan maksimal 20 karakter.');
        }

        $product = Database::selectOne(
            'SELECT id, code, name, unit, standard_batch_size FROM products WHERE id = :id',
            ['id' => $productId]
        );
        if (!$product) {
            throw new \InvalidArgumentException('Produk jadi tidak ditemukan.');
        }
        $batchSize = (float) $product['standard_batch_size'];
        if (!is_finite($batchSize) || $batchSize <= 0) {
            throw new \InvalidArgumentException('Ukuran batch standar produk harus lebih dari nol.');
        }

        $activeBom = Database::selectOne(
            'SELECT id, version FROM boms WHERE product_id = :product_id AND is_active = 1 ORDER BY id DESC LIMIT 1',
            ['product_id' => $productId]
        );
        if ($activeBom && (string) ($data['replace_active_bom'] ?? '') !== '1') {
            throw new \InvalidArgumentException(
                'Produk ini sudah memiliki BOM aktif ' . $activeBom['version']
                . '. Konfirmasikan penggantian BOM aktif sebelum menyimpan formula manual.'
            );
        }

        $validItems = [];
        foreach ($rawItems as $item) {
            if (!\is_array($item)) {
                throw new \InvalidArgumentException('Format baris bahan formula tidak valid.');
            }
            $materialId = (int) ($item['material_id'] ?? 0);
            $percentageValue = $item['percentage'] ?? null;
            $isBlankPercentage = $percentageValue === null || trim((string) $percentageValue) === '';
            if ($materialId <= 0 && $isBlankPercentage) {
                continue;
            }
            if ($materialId <= 0 || !is_numeric($percentageValue)
                || !is_finite((float) $percentageValue) || (float) $percentageValue <= 0
                || (float) $percentageValue > 100) {
                throw new \InvalidArgumentException('Setiap baris formula harus memiliki material dan persentase 0-100 yang valid.');
            }

            $material = Database::selectOne(
                'SELECT id, code, unit FROM materials WHERE id = :id AND is_active = 1',
                ['id' => $materialId]
            );
            if (!$material) {
                throw new \InvalidArgumentException('Pilih material aktif yang terdaftar untuk setiap bahan formula.');
            }
            if (strcasecmp(trim((string) $material['unit']), trim((string) $product['unit'])) !== 0) {
                throw new \InvalidArgumentException(
                    'Satuan material ' . $material['code'] . ' (' . $material['unit']
                    . ') harus sama dengan satuan produk (' . $product['unit'] . ') untuk kalkulasi persentase.'
                );
            }

            $percentage = (float) $percentageValue;
            $quantity = $batchSize * ($percentage / 100);
            if (!is_finite($quantity) || $quantity <= 0) {
                throw new \InvalidArgumentException('Hasil perhitungan bahan ' . $material['code'] . ' tidak valid.');
            }
            $validItems[] = [
                'material_id' => $materialId,
                'quantity_per_batch' => $quantity,
                'unit' => $material['unit'],
                'notes' => 'Persentase manual: ' . $percentage . '%',
            ];
        }
        if ($validItems === []) {
            throw new \InvalidArgumentException('Formula harus memiliki minimal satu bahan dan persentase yang valid.');
        }

        Database::beginTransaction();
        try {
            Database::update('boms', ['is_active' => 0], 'product_id = :product_id', ['product_id' => $productId]);
            $bomId = Database::insert('boms', [
                'product_id' => $productId,
                'version' => $version,
                'batch_size' => $batchSize,
                'unit' => $product['unit'],
                'yield_pct' => 100,
                'scrap_pct' => 0,
                'is_active' => 1,
                'notes' => 'Formula dimasukkan manual dari halaman Finish Good; persentase tidak dinormalisasi.',
            ]);

            foreach ($validItems as $item) {
                Database::insert('bom_items', [
                    'bom_id' => $bomId,
                    'material_id' => $item['material_id'],
                    'quantity_per_batch' => $item['quantity_per_batch'],
                    'unit' => $item['unit'],
                    'notes' => $item['notes'],
                ]);
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'CREATE_MANUAL_FINISHED_GOOD_BOM',
                'module' => 'Finish Good',
                'description' => "Membuat BOM manual {$version} untuk produk jadi {$product['name']} ({$product['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $productId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function linkFormulaToProduct(array $data, int $userId): int
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $formulaId = (int) ($data['formula_id'] ?? 0);
        $materialIds = $data['material_ids'] ?? null;
        if ($productId <= 0 || $formulaId <= 0 || !\is_array($materialIds)) {
            throw new \InvalidArgumentException('Produk, formula, dan pemetaan bahan harus dipilih.');
        }

        $formula = FormulaCatalogService::getFormulaDetail($formulaId);
        if (!$formula) {
            throw new \InvalidArgumentException('Varian formula katalog tidak ditemukan.');
        }
        $product = Database::selectOne(
            'SELECT id, code, name, unit, standard_batch_size FROM products WHERE id = :id',
            ['id' => $productId]
        );
        if (!$product) {
            throw new \InvalidArgumentException('Produk jadi yang dipilih tidak ditemukan.');
        }

        $batchSize = (float) $product['standard_batch_size'];
        if (!is_finite($batchSize) || $batchSize <= 0) {
            throw new \InvalidArgumentException('Ukuran batch standar produk harus lebih dari nol.');
        }
        if (\strlen((string) $formula['formula_code']) > 20) {
            throw new \InvalidArgumentException('Kode formula terlalu panjang untuk menjadi versi BOM.');
        }
        $existingLinkedBom = Database::selectOne(
            'SELECT id FROM boms WHERE product_id = :product_id AND source_formula_id = :formula_id LIMIT 1',
            ['product_id' => $productId, 'formula_id' => $formulaId]
        );
        $activeBom = Database::selectOne(
            'SELECT id, version FROM boms WHERE product_id = :product_id AND is_active = 1 ORDER BY id DESC LIMIT 1',
            ['product_id' => $productId]
        );
        if ($existingLinkedBom && $activeBom
            && (int) $existingLinkedBom['id'] === (int) $activeBom['id']) {
            return $productId;
        }
        if ($activeBom && (string) ($data['replace_active_bom'] ?? '') !== '1') {
            throw new \InvalidArgumentException(
                'Produk ini sudah memiliki BOM aktif ' . $activeBom['version']
                . '. Konfirmasikan penggantian BOM aktif sebelum menghubungkan formula baru.'
            );
        }
        if ($existingLinkedBom) {
            Database::beginTransaction();
            try {
                Database::update('boms', ['is_active' => 0], 'product_id = :product_id', ['product_id' => $productId]);
                Database::update('boms', ['is_active' => 1], 'id = :id', ['id' => $existingLinkedBom['id']]);
                Database::insert('audit_logs', [
                    'user_id' => $userId,
                    'action' => 'ACTIVATE_CATALOG_FORMULA',
                    'module' => 'Finish Good',
                    'description' => "Mengaktifkan kembali formula {$formula['formula_code']} untuk produk jadi {$product['name']} ({$product['code']}).",
                    'ip_address' => '127.0.0.1',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                Database::commit();
                return $productId;
            } catch (\Throwable $e) {
                Database::rollBack();
                throw $e;
            }
        }

        $validItems = [];
        foreach ($formula['items'] as $formulaItem) {
            $formulaItemId = (int) $formulaItem['id'];
            $materialId = (int) ($materialIds[$formulaItemId] ?? 0);
            $material = $materialId > 0
                ? Database::selectOne('SELECT id, code, unit FROM materials WHERE id = :id AND is_active = 1', ['id' => $materialId])
                : null;
            if (!$material) {
                throw new \InvalidArgumentException('Pilih material terdaftar untuk bahan ' . $formulaItem['material_code'] . '.');
            }
            if (strcasecmp(trim((string) $material['unit']), trim((string) $product['unit'])) !== 0) {
                throw new \InvalidArgumentException(
                    'Satuan material ' . $material['code'] . ' (' . $material['unit']
                    . ') harus sama dengan satuan produk (' . $product['unit'] . ') untuk kalkulasi persentase.'
                );
            }

            $quantity = $batchSize * (float) $formulaItem['fraction'];
            if (!is_finite($quantity) || $quantity <= 0) {
                throw new \InvalidArgumentException('Hasil perhitungan bahan ' . $formulaItem['material_code'] . ' tidak valid.');
            }
            $validItems[] = [
                'source_formula_item_id' => $formulaItemId,
                'material_id' => $materialId,
                'quantity_per_batch' => $quantity,
                'unit' => $material['unit'],
                'notes' => 'Sumber: ' . $formulaItem['material_code'],
            ];
        }
        if (\count($validItems) !== \count($formula['items'])) {
            throw new \InvalidArgumentException('Seluruh bahan formula harus dipetakan ke material.');
        }

        Database::beginTransaction();
        try {
            Database::update('boms', ['is_active' => 0], 'product_id = :product_id', ['product_id' => $productId]);
            $bomId = Database::insert('boms', [
                'product_id' => $productId,
                'version' => $formula['formula_code'],
                'batch_size' => $batchSize,
                'unit' => $product['unit'],
                'yield_pct' => 100,
                'scrap_pct' => 0,
                'is_active' => 1,
                'source_formula_id' => $formulaId,
                'notes' => 'Terhubung dari katalog formula Vaslina; komposisi asli tidak dinormalisasi.',
            ]);

            foreach ($validItems as $item) {
                Database::insert('bom_items', [
                    'bom_id' => $bomId,
                    'material_id' => $item['material_id'],
                    'quantity_per_batch' => $item['quantity_per_batch'],
                    'unit' => $item['unit'],
                    'notes' => $item['notes'],
                    'source_formula_item_id' => $item['source_formula_item_id'],
                ]);
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'LINK_CATALOG_FORMULA',
                'module' => 'Finish Good',
                'description' => "Menghubungkan formula {$formula['formula_code']} ke produk jadi {$product['name']} ({$product['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $productId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function boms(int $selectedProductId = 0): array
    {
        $boms = Database::select("
            SELECT b.*, p.name as product_name, p.code as product_code
            FROM boms b
            JOIN products p ON p.id = b.product_id
            ORDER BY p.name ASC, b.product_id ASC, b.id DESC
        ");

        foreach ($boms as &$b) {
            $b['items'] = Database::select("
                SELECT bi.*, m.name as material_name, m.code as material_code
                FROM bom_items bi
                JOIN materials m ON m.id = bi.material_id
                WHERE bi.bom_id = :bom_id
            ", ['bom_id' => $b['id']]);
        }

        $products = Database::select("SELECT * FROM products ORDER BY name ASC");
        $materials = Database::select("SELECT * FROM materials ORDER BY name ASC");

        return [
            'boms' => $boms,
            'products' => $products,
            'materials' => $materials,
            'formula_catalog' => FormulaCatalogService::getCatalog(),
            'selected_product_id' => $selectedProductId,
        ];
    }

    public static function formulaCatalogDetail(int $formulaId, int $selectedProductId = 0): array
    {
        if ($formulaId <= 0) {
            throw new \InvalidArgumentException('Varian formula tidak valid.');
        }

        $formula = FormulaCatalogService::getFormulaDetail($formulaId);
        if (!$formula) {
            throw new \RuntimeException('Varian formula tidak ditemukan.');
        }

        $products = Database::select("
            SELECT p.*,
                   (SELECT version FROM boms WHERE product_id = p.id AND is_active = 1 ORDER BY id DESC LIMIT 1) as active_bom_version
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.name ASC
        ");

        return [
            'formula' => $formula,
            'products' => $products,
            'materials' => Database::select('SELECT id, code, name, unit FROM materials WHERE is_active = 1 ORDER BY name ASC'),
            'selected_product_id' => $selectedProductId,
        ];
    }

    public static function suppliers(): array
    {
        $suppliers = Database::select("
            SELECT s.*, 
                   (SELECT COUNT(DISTINCT material_id) FROM material_suppliers WHERE supplier_id = s.id) as material_count
            FROM suppliers s
            ORDER BY s.id ASC
        ");

        return ['suppliers' => $suppliers];
    }

    public static function deleteMaterial(int $id, int $userId): void
    {
        $mat = Database::selectOne("SELECT name, code FROM materials WHERE id = :id", ['id' => $id]);
        if (!$mat) throw new \RuntimeException("Bahan baku tidak ditemukan.");

        $bomUsage = Database::selectOne("SELECT COUNT(*) as count FROM bom_items WHERE material_id = :id", ['id' => $id]);
        if (($bomUsage['count'] ?? 0) > 0) {
            throw new \RuntimeException("Tidak dapat menghapus bahan baku '{$mat['name']}' karena terdaftar dalam formula BOM aktif.");
        }

        Database::beginTransaction();
        try {
            Database::statement("DELETE FROM inventory_batches WHERE material_id = :id", ['id' => $id]);
            Database::statement("DELETE FROM stock_movements WHERE material_id = :id", ['id' => $id]);
            Database::statement("DELETE FROM materials WHERE id = :id", ['id' => $id]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'DELETE_MATERIAL',
                'module' => 'R&D / Master',
                'description' => "Menghapus master bahan baku: {$mat['name']} ({$mat['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function deleteProduct(int $id, int $userId): void
    {
        $p = Database::selectOne("SELECT name, code FROM products WHERE id = :id", ['id' => $id]);
        if (!$p) throw new \RuntimeException("Produk jadi tidak ditemukan.");

        Database::beginTransaction();
        try {
            $boms = Database::select("SELECT id FROM boms WHERE product_id = :id", ['id' => $id]);
            foreach ($boms as $b) {
                Database::statement("DELETE FROM bom_items WHERE bom_id = :bom_id", ['bom_id' => $b['id']]);
            }
            Database::statement("DELETE FROM boms WHERE product_id = :id", ['id' => $id]);
            Database::statement("DELETE FROM products WHERE id = :id", ['id' => $id]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'DELETE_PRODUCT',
                'module' => 'R&D / Master',
                'description' => "Menghapus master produk jadi: {$p['name']} ({$p['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function deleteBom(int $id, int $userId): void
    {
        $b = Database::selectOne("SELECT b.version, p.name as product_name FROM boms b JOIN products p ON p.id = b.product_id WHERE b.id = :id", ['id' => $id]);
        if (!$b) throw new \RuntimeException("Formula BOM tidak ditemukan.");

        Database::beginTransaction();
        try {
            Database::statement("DELETE FROM bom_items WHERE bom_id = :id", ['id' => $id]);
            Database::statement("DELETE FROM boms WHERE id = :id", ['id' => $id]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'DELETE_BOM',
                'module' => 'R&D / Master',
                'description' => "Menghapus formula BOM {$b['version']} untuk produk {$b['product_name']}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function deleteSupplier(int $id, int $userId): void
    {
        $s = Database::selectOne("SELECT name, code FROM suppliers WHERE id = :id", ['id' => $id]);
        if (!$s) throw new \RuntimeException("Supplier tidak ditemukan.");

        Database::beginTransaction();
        try {
            Database::statement("UPDATE materials SET preferred_supplier_id = NULL WHERE preferred_supplier_id = :id", ['id' => $id]);
            Database::statement("DELETE FROM suppliers WHERE id = :id", ['id' => $id]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'DELETE_SUPPLIER',
                'module' => 'PPIC / Master',
                'description' => "Menghapus supplier rekanan: {$s['name']} ({$s['code']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function aiFormulationAdvisor(?int $productId = null): array
    {
        $products = Database::select("SELECT id, code, name, category, standard_batch_size, unit FROM products ORDER BY id ASC");
        if (empty($products)) {
            return [
                'products' => [],
                'selected_product_id' => 0,
                'product' => ['id' => 0, 'name' => 'Belum Ada Produk Jadi', 'code' => '-', 'standard_batch_size' => 0, 'unit' => 'Kg'],
                'bom' => null,
                'has_active_bom' => false,
                'standard_items' => [],
                'standard_total_cost' => 0.0,
                'depleted_items' => [],
                'has_shortage' => false,
                'ai_analysis' => [
                    'narrative' => 'Belum ada master produk jadi terdaftar. Silakan tambahkan Produk Jadi (Finish Good) terlebih dahulu.',
                    'source' => 'Rekomendasi formula tersimpan',
                    'options' => [],
                ],
            ];
        }

        if (!$productId || $productId <= 0) {
            $productId = (int) $products[0]['id'];
        }

        $recommendationData = \App\Services\BomRecommendationService::recommendForProduct($productId);

        return array_merge([
            'products' => $products,
            'selected_product_id' => $productId,
        ], $recommendationData);
    }

    public static function applyAiFormulation(array $data, int $userId): int
    {
        Database::beginTransaction();
        try {
            $productId = (int) ($data['product_id'] ?? 0);
            $sourceBomId = (int) ($data['source_bom_id'] ?? 0);
            $product = Database::selectOne('SELECT id, name, code FROM products WHERE id = :id', ['id' => $productId]);
            $sourceBom = Database::selectOne(
                'SELECT * FROM boms WHERE id = :id AND product_id = :product_id',
                ['id' => $sourceBomId, 'product_id' => $productId]
            );
            if (!$product || !$sourceBom) {
                throw new \InvalidArgumentException('Formula alternatif tersimpan tidak valid untuk produk ini.');
            }

            $sourceItems = Database::select('SELECT * FROM bom_items WHERE bom_id = :bom_id', ['bom_id' => $sourceBomId]);
            if (empty($sourceItems)) {
                throw new \RuntimeException('Formula alternatif tidak memiliki komposisi bahan baku.');
            }

            Database::update('boms', ['is_active' => 0], 'product_id = :product_id', ['product_id' => $productId]);
            Database::update('boms', ['is_active' => 1], 'id = :id', ['id' => $sourceBomId]);

            $prodName = $product['name'];
            $version = (string) $sourceBom['version'];

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'ACTIVATE_SAVED_BOM',
                'module' => 'R&D / BOM',
                'description' => "R&D mengaktifkan kembali salinan formula BOM tersimpan {$version} untuk {$prodName}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();

            $userName = $_SESSION['user_name'] ?? 'R&D Specialist';
            \App\Services\NotificationService::record(
                'RD',
                'BOM_AI_APPROVED',
                "Formula BOM Diaktifkan: {$version}",
                "Tim R&D ({$userName}) mengaktifkan formula BOM tersimpan {$version} untuk {$prodName}. Pastikan validasi R&D/QC telah dilakukan sebelum produksi.",
                "/master/boms",
                $userId,
                $userName,
                true,
                'PPIC'
            );

            return $sourceBomId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
