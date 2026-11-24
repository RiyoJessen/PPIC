<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Database\Database;
use App\Services\InventoryService;

class MasterController extends Controller
{
    public static function materials(): array
    {
        $materials = InventoryService::getMaterialStockSummary();
        $categories = Database::select("SELECT * FROM categories ORDER BY name ASC");
        $warehouses = Database::select("SELECT * FROM warehouses ORDER BY name ASC");
        $suppliers = Database::select("SELECT * FROM suppliers ORDER BY name ASC");

        return [
            'materials' => $materials,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
        ];
    }

    public static function storeMaterial(array $data, int $userId): int
    {
        $id = Database::insert('materials', [
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'category_id' => (int) $data['category_id'] ?: null,
            'unit' => trim($data['unit']),
            'min_stock' => (float) ($data['min_stock'] ?? 0),
            'safety_stock' => (float) ($data['safety_stock'] ?? 0),
            'reorder_point' => (float) ($data['reorder_point'] ?? 0),
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
        if (!is_numeric($initialStock) || !is_finite((float) $initialStock) || (float) $initialStock < 0) {
            throw new \InvalidArgumentException('Stok awal Finish Good harus berupa angka nol atau lebih.');
        }

        Database::beginTransaction();
        try {
            $id = Database::insert('products', [
                'code' => trim($data['code']),
                'name' => trim($data['name']),
                'category' => trim($data['category'] ?? 'Koagulan Cair'),
                'unit' => trim($data['unit'] ?? 'Kg'),
                'standard_batch_size' => (float) ($data['standard_batch_size'] ?? 1000),
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

    public static function boms(): array
    {
        $boms = Database::select("
            SELECT b.*, p.name as product_name, p.code as product_code
            FROM boms b
            JOIN products p ON p.id = b.product_id
            ORDER BY b.id ASC
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
        ];
    }

    public static function suppliers(): array
    {
        $suppliers = Database::select("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM materials WHERE preferred_supplier_id = s.id) as material_count
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
