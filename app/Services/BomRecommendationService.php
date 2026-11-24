<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

class BomRecommendationService
{
    public static function recommendForProduct(int $productId): array
    {
        $product = Database::selectOne('SELECT * FROM products WHERE id = :id', ['id' => $productId]);
        if (!$product) {
            throw new \RuntimeException('Produk tidak ditemukan.');
        }

        $stockById = [];
        foreach (InventoryService::getMaterialStockSummary() as $material) {
            $stockById[(int) $material['id']] = $material;
        }

        $boms = Database::select(
            'SELECT * FROM boms WHERE product_id = :product_id ORDER BY is_active DESC, id DESC',
            ['product_id' => $productId]
        );
        $activeBom = null;
        foreach ($boms as $candidate) {
            if ((int) $candidate['is_active'] === 1) {
                $activeBom = $candidate;
                break;
            }
        }

        $standardItems = $activeBom
            ? self::getBomItemsWithStock($activeBom, $stockById)
            : [];
        $standardTotalCost = array_sum(array_map(
            static fn(array $item): float => (float) $item['item_cost'],
            $standardItems
        ));
        $depletedItems = array_values(array_filter(
            $standardItems,
            static fn(array $item): bool => $item['is_shortage']
        ));

        $options = [];
        foreach ($boms as $candidate) {
            if ($activeBom && (int) $candidate['id'] === (int) $activeBom['id']) {
                continue;
            }

            $items = self::getBomItemsWithStock($candidate, $stockById);
            $cost = array_sum(array_map(
                static fn(array $item): float => (float) $item['item_cost'],
                $items
            ));
            $shortageCount = count(array_filter(
                $items,
                static fn(array $item): bool => $item['is_shortage']
            ));

            $options[] = [
                'bom_id' => (int) $candidate['id'],
                'code' => 'BOM-' . (int) $candidate['id'],
                'title' => 'Formula tersimpan ' . ($candidate['version'] ?: 'tanpa versi'),
                'version_code' => (string) $candidate['version'],
                'strategy' => 'Versi BOM yang sudah tersimpan; rekomendasi ini tidak mengubah komposisi.',
                'reaction_parameters' => 'Ikuti parameter proses yang disahkan oleh tim R&D.',
                'coa_prediction' => 'Belum diverifikasi oleh rekomendasi sistem; pemeriksaan R&D/QC tetap wajib.',
                'estimated_cost' => $cost,
                'cost_diff_pct' => $standardTotalCost > 0
                    ? round((($cost - $standardTotalCost) / $standardTotalCost) * 100, 1)
                    : 0,
                'shortage_count' => $shortageCount,
                'items' => array_map(static function (array $item): array {
                    return [
                        'material_id' => (int) $item['material_id'],
                        'material_name' => $item['material_name'],
                        'quantity_per_batch' => (float) $item['quantity_per_batch'],
                        'unit' => $item['unit'],
                        'unit_cost' => (float) $item['unit_cost'],
                        'subtotal' => (float) $item['item_cost'],
                        'status_note' => $item['is_shortage']
                            ? 'Stok saat ini belum cukup untuk 1 batch.'
                            : 'Stok saat ini cukup untuk 1 batch.',
                    ];
                }, $items),
            ];
        }

        usort($options, static function (array $a, array $b): int {
            return [$a['shortage_count'], $a['estimated_cost']]
                <=> [$b['shortage_count'], $b['estimated_cost']];
        });

        if (!$activeBom && empty($boms)) {
            $narrative = 'Belum ada formula BOM tersimpan untuk produk ini. Sistem tidak membuat komposisi kimia baru; tim R&D perlu memasukkan dan memvalidasi formula berdasarkan spesifikasi produk dan hasil pengujian.';
        } elseif (!$activeBom) {
            $narrative = 'Belum ada formula BOM aktif. Sistem menemukan formula versi tersimpan sebagai bahan evaluasi, tetapi tim R&D perlu memeriksa dan mengaktifkan versi yang sesuai sebelum perhitungan produksi.';
        } elseif (empty($options)) {
            $narrative = 'Formula BOM aktif tersedia. Belum ada versi formula alternatif tersimpan untuk dibandingkan; masukkan versi tervalidasi melalui modul BOM bila ada alternatif yang disetujui R&D.';
        } else {
            $best = $options[0];
            $fit = $best['shortage_count'] === 0
                ? 'stok cukup untuk 1 batch'
                : $best['shortage_count'] . ' bahan masih kurang untuk 1 batch';
            $narrative = "Formula aktif {$activeBom['version']} dipertahankan sebagai formula standar. Versi alternatif tersimpan yang paling sesuai dengan stok saat ini adalah {$best['version_code']} ({$fit}); sistem hanya membandingkan formula yang sudah tersimpan dan tidak memvalidasi reaksi atau mutu produk.";
        }

        return [
            'product' => $product,
            'bom' => $activeBom,
            'has_active_bom' => $activeBom !== null,
            'standard_items' => $standardItems,
            'standard_total_cost' => $standardTotalCost,
            'depleted_items' => $depletedItems,
            'has_shortage' => !empty($depletedItems),
            'ai_analysis' => [
                'narrative' => $narrative,
                'source' => 'Rekomendasi kecocokan stok dan biaya formula tersimpan',
                'options' => $options,
            ],
        ];
    }

    private static function getBomItemsWithStock(array $bom, array $stockById): array
    {
        $items = Database::select("
            SELECT bi.*, m.name as material_name, m.code as material_code, m.unit_cost
            FROM bom_items bi
            JOIN materials m ON m.id = bi.material_id
            WHERE bi.bom_id = :bom_id
        ", ['bom_id' => $bom['id']]);

        foreach ($items as &$item) {
            $stock = $stockById[(int) $item['material_id']] ?? [];
            $item['physical_stock'] = (float) ($stock['physical_stock'] ?? 0);
            $item['available_stock'] = (float) ($stock['available_stock'] ?? 0);
            $item['is_shortage'] = $item['available_stock'] < (float) $item['quantity_per_batch'];
            $item['item_cost'] = (float) $item['quantity_per_batch'] * (float) $item['unit_cost'];
        }
        unset($item);

        return $items;
    }
}
