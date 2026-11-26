<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use RuntimeException;

class FormulaCatalogService
{
    public static function ensureSeeded(): void
    {
        $count = (int) (Database::selectOne('SELECT COUNT(*) AS formula_count FROM formula_catalog')['formula_count'] ?? 0);
        if ($count > 0) {
            return;
        }

        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR
            . 'data' . DIRECTORY_SEPARATOR . 'vaslina_formulas.json';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Data katalog formula Vaslina tidak dapat dibaca.');
        }

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $formulas = $data['formulas'] ?? null;
        $assignments = $data['assignments'] ?? null;
        if (!is_array($formulas) || $formulas === [] || !is_array($assignments)) {
            throw new RuntimeException('Format data katalog formula Vaslina tidak valid.');
        }

        $formulaIds = [];
        Database::beginTransaction();
        try {
            foreach ($formulas as $formula) {
                $code = trim((string) ($formula['code'] ?? ''));
                $productName = trim((string) ($formula['product'] ?? ''));
                $items = $formula['items'] ?? null;
                if ($code === '' || $productName === '' || !is_array($items) || $items === []) {
                    throw new RuntimeException('Data formula katalog memiliki kode, nama produk, atau komposisi yang kosong.');
                }

                $formulaId = Database::insert('formula_catalog', [
                    'formula_code' => $code,
                    'product_name' => $productName,
                    'source' => (string) ($data['source'] ?? 'Workbook formula'),
                ]);
                $formulaIds[$code] = $formulaId;

                foreach ($items as $item) {
                    $material = trim((string) ($item['material'] ?? ''));
                    $fraction = $item['fraction'] ?? null;
                    if ($material === '' || !is_numeric($fraction) || !is_finite((float) $fraction)
                        || (float) $fraction <= 0 || (float) $fraction > 1) {
                        throw new RuntimeException("Komposisi bahan pada formula {$code} tidak valid.");
                    }

                    Database::insert('formula_catalog_items', [
                        'formula_id' => $formulaId,
                        'material_code' => $material,
                        'fraction' => (float) $fraction,
                        'sort_order' => (int) ($item['sort_order'] ?? 0),
                    ]);
                }
            }

            foreach ($assignments as $assignment) {
                $code = trim((string) ($assignment['formula_code'] ?? ''));
                if ($code === '' || !isset($formulaIds[$code])) {
                    throw new RuntimeException('Produk Vaslina merujuk kode formula yang tidak ditemukan.');
                }

                $productName = trim((string) ($assignment['product'] ?? ''));
                if ($productName === '') {
                    throw new RuntimeException('Nama produk pada relasi formula Vaslina tidak boleh kosong.');
                }

                Database::insert('formula_products', [
                    'formula_id' => $formulaIds[$code],
                    'customer_name' => trim((string) ($assignment['customer'] ?? '')),
                    'product_name' => $productName,
                    'nomat' => trim((string) ($assignment['nomat'] ?? '')),
                ]);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function getCatalog(): array
    {
        $formulas = Database::select('SELECT * FROM formula_catalog ORDER BY product_name ASC, formula_code ASC');
        if ($formulas === []) {
            return [];
        }

        $items = Database::select('SELECT * FROM formula_catalog_items ORDER BY formula_id ASC, sort_order ASC, id ASC');
        $products = Database::select('SELECT * FROM formula_products ORDER BY formula_id ASC, product_name ASC, customer_name ASC');
        $itemsByFormula = [];
        $productsByFormula = [];

        foreach ($items as $item) {
            $itemsByFormula[(int) $item['formula_id']][] = $item;
        }
        foreach ($products as $product) {
            $productsByFormula[(int) $product['formula_id']][] = $product;
        }

        foreach ($formulas as &$formula) {
            $id = (int) $formula['id'];
            $formula['items'] = $itemsByFormula[$id] ?? [];
            $formula['products'] = $productsByFormula[$id] ?? [];
            $formula['total_fraction'] = array_sum(array_map(
                static fn(array $item): float => (float) $item['fraction'],
                $formula['items']
            ));
        }
        unset($formula);

        return $formulas;
    }

    public static function getFormulaDetail(int $formulaId): ?array
    {
        $formula = Database::selectOne(
            'SELECT * FROM formula_catalog WHERE id = :id',
            ['id' => $formulaId]
        );
        if (!$formula) {
            return null;
        }

        $formula['items'] = Database::select(
            'SELECT * FROM formula_catalog_items WHERE formula_id = :formula_id ORDER BY sort_order ASC, id ASC',
            ['formula_id' => $formulaId]
        );
        $formula['products'] = Database::select(
            'SELECT * FROM formula_products WHERE formula_id = :formula_id ORDER BY product_name ASC, customer_name ASC',
            ['formula_id' => $formulaId]
        );
        $formula['total_fraction'] = array_sum(array_map(
            static fn(array $item): float => (float) $item['fraction'],
            $formula['items']
        ));

        return $formula;
    }
}
