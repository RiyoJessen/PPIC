@php
$pageTitle = 'Material';
$materialCount = count($materials);
$productCount = count($products);
$bomCount = count($boms);
$forecastCount = count($sales_forecasts);
$salesCount = count($sales_histories);
$criticalCount = 0;
$totalProductStock = 0.0;
$criticalMaterial = null;
$productUnit = '';
foreach ($materials as $material) {
    if (($material['stock_status'] ?? '') === 'Kritis') {
        $criticalCount++;
        if (!$criticalMaterial) {
            $criticalMaterial = $material;
        }
    } elseif (($material['stock_status'] ?? '') === 'Waspada' && !$criticalMaterial) {
        $criticalMaterial = $material;
    }
}
foreach ($products as $product) {
    $totalProductStock += (float) ($product['finished_good_stock'] ?? 0);
    if ($productUnit === '') {
        $productUnit = (string) ($product['unit'] ?? '');
    }
}

$attentionItems = [];
if ($criticalMaterial) {
    $attentionItems[] = [
        'text' => htmlspecialchars($criticalMaterial['name']) . ' (' . htmlspecialchars($criticalMaterial['code']) . ') berstatus ' . strtolower($criticalMaterial['stock_status']) . ' dengan stok ' . number_format((float) $criticalMaterial['available_stock'], 0, ',', '.') . ' ' . htmlspecialchars($criticalMaterial['unit']) . '.',
        'link' => '/master/storage/raw-materials',
        'label' => 'Lihat bahan',
    ];
}
if (!empty($products)) {
    $attentionProduct = $products[0];
    foreach ($products as $product) {
        if ((float) ($product['finished_good_stock'] ?? 0) < (float) ($attentionProduct['finished_good_stock'] ?? 0)) {
            $attentionProduct = $product;
        }
    }
    $attentionItems[] = [
        'text' => 'Stok ' . htmlspecialchars($attentionProduct['name']) . ' hanya ' . number_format((float) ($attentionProduct['finished_good_stock'] ?? 0), 0, ',', '.') . ' ' . htmlspecialchars($attentionProduct['unit'] ?? '') . ', batch standarnya ' . number_format((float) ($attentionProduct['standard_batch_size'] ?? 0), 0, ',', '.') . ' ' . htmlspecialchars($attentionProduct['unit'] ?? '') . '.',
        'link' => '/master/storage/finish-goods',
        'label' => 'Lihat produk',
    ];
}
@endphp
@include('layouts.header')

<div class="material-overview-page">
    <div class="material-overview-heading">
        <div>
            <h1>Material</h1>
            <p>Gudang semua data master. Klik kotak untuk membuka datanya secara lengkap.</p>
        </div>
        <span class="material-overview-updated">Diperbarui <?= date('d F Y') ?></span>
    </div>

    <?php if (!empty($attentionItems)): ?>
        <div class="material-overview-alert">
            <span class="material-alert-dot"></span>
            <div>
                <strong>Perlu perhatian</strong>
                <span>
                    <?= $criticalCount ?> bahan baku berstatus kritis.
                    <?php if (!empty($attentionItems) && count($attentionItems) > 1): ?>
                        Ada stok produk jadi yang perlu ditinjau.
                    <?php endif; ?>
                </span>
            </div>
            <a href="/master/storage/raw-materials">Lihat bahan</a>
        </div>
    <?php endif; ?>

    <?php
    $safeCount = 0;
    $warningCount = 0;
    foreach ($materials as $material) {
        $status = $material['stock_status'] ?? 'Aman';
        if ($status === 'Aman') $safeCount++;
        if ($status === 'Waspada') $warningCount++;
    }

    $topMaterials = $materials;
    usort($topMaterials, function ($a, $b) {
        $aRatio = (float) ($a['minimum_stock'] ?? 1) > 0
            ? (float) $a['available_stock'] / (float) $a['minimum_stock']
            : 1;
        $bRatio = (float) ($b['minimum_stock'] ?? 1) > 0
            ? (float) $b['available_stock'] / (float) $b['minimum_stock']
            : 1;
        return $aRatio <=> $bRatio;
    });
    $topMaterials = array_slice($topMaterials, 0, 3);

    $activeBomCount = 0;
    $draftBomCount = 0;
    foreach ($boms as $bom) {
        if ((int) ($bom['is_active'] ?? 0) === 1) $activeBomCount++;
        else $draftBomCount++;
    }
    ?>

    <div class="material-bento">
        <a class="material-bento-card material-bento-blue" href="/master/storage/raw-materials">
            <div class="material-bento-head">
                <span class="material-bento-icon"><i class="fas fa-layer-group"></i></span>
                <div class="material-bento-title">
                    <small>Data Raw Material</small>
                    <strong><?= $materialCount ?></strong>
                </div>
                <span class="material-bento-arrow"><i class="fas fa-arrow-right"></i></span>
            </div>
            <p class="material-bento-subtitle">Bahan baku dan kondisi stoknya</p>
            <div class="material-status-bar">
                <?php if ($criticalCount > 0): ?><i class="material-status-critical" style="flex:<?= $criticalCount ?>"></i><?php endif; ?>
                <?php if ($warningCount > 0): ?><i class="material-status-warning" style="flex:<?= $warningCount ?>"></i><?php endif; ?>
                <?php if ($safeCount > 0): ?><i class="material-status-safe" style="flex:<?= $safeCount ?>"></i><?php endif; ?>
            </div>
            <div class="material-status-legend">
                <span><i class="material-status-critical"></i><?= $criticalCount ?> kritis</span>
                <span><i class="material-status-warning"></i><?= $warningCount ?> waspada</span>
                <span><i class="material-status-safe"></i><?= $safeCount ?> aman</span>
            </div>
            <?php foreach ($topMaterials as $material):
                $status = $material['stock_status'] ?? 'Aman';
                $progress = (float) ($material['minimum_stock'] ?? 0) > 0
                    ? min(100, ((float) $material['available_stock'] / (float) $material['minimum_stock']) * 100)
                    : 100;
            ?>
                <div class="material-bento-row">
                    <div class="material-bento-row-name">
                        <b><?= htmlspecialchars($material['name']) ?></b>
                        <small><?= htmlspecialchars($material['code']) ?></small>
                    </div>
                    <div class="material-bento-progress"><i style="width:<?= $progress ?>%"></i></div>
                    <span class="material-pill pill-<?= strtolower($status) ?>"><?= htmlspecialchars($status) ?></span>
                </div>
            <?php endforeach; ?>
        </a>

        <a class="material-bento-card material-bento-teal" href="/master/storage/finish-goods">
            <div class="material-bento-head">
                <span class="material-bento-icon"><i class="fas fa-cube"></i></span>
                <div class="material-bento-title">
                    <small>Data Finish Good</small>
                    <strong><?= $productCount ?></strong>
                </div>
                <span class="material-bento-arrow"><i class="fas fa-arrow-right"></i></span>
            </div>
            <p class="material-bento-subtitle">Produk jadi terhadap batch standar</p>
            <?php
            $lowStockCount = 0;
            foreach ($products as $product) {
                $batch = (float) ($product['standard_batch_size'] ?? 0);
                if ($batch > 0 && (float) $product['finished_good_stock'] / $batch < 0.25) $lowStockCount++;
            }
            ?>
            <div class="material-product-legend">
                <span><i class="material-status-critical"></i><?= $lowStockCount ?> rendah</span>
                <span><i class="material-status-safe"></i><?= max(0, $productCount - $lowStockCount) ?> cukup</span>
            </div>
            <?php foreach (array_slice($products, 0, 4) as $product):
                $batch = (float) ($product['standard_batch_size'] ?? 0);
                $progress = $batch > 0 ? min(100, ((float) $product['finished_good_stock'] / $batch) * 100) : 0;
            ?>
                <div class="material-bento-row">
                    <div class="material-bento-row-name">
                        <b><?= htmlspecialchars($product['name']) ?></b>
                        <small><?= number_format((float) $product['finished_good_stock'], 0, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?> dari <?= number_format($batch, 0, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?></small>
                    </div>
                    <div class="material-bento-progress"><i style="width:<?= $progress ?>%"></i></div>
                </div>
            <?php endforeach; ?>
        </a>

        <a class="material-bento-card material-bento-purple" href="/master/storage/formulas">
            <div class="material-bento-head">
                <span class="material-bento-icon"><i class="fas fa-flask"></i></span>
                <div class="material-bento-title">
                    <small>Data Formula</small>
                    <strong><?= $bomCount ?></strong>
                </div>
                <span class="material-bento-arrow"><i class="fas fa-arrow-right"></i></span>
            </div>
            <p class="material-bento-subtitle">Versi formula tersimpan</p>
            <div class="material-product-legend">
                <span><i class="material-status-safe"></i><?= $activeBomCount ?> aktif</span>
                <span><i class="material-status-warning"></i><?= $draftBomCount ?> draf</span>
            </div>
            <?php foreach (array_slice($boms, 0, 3) as $bom): ?>
                <div class="material-bento-row">
                    <div class="material-bento-row-name">
                        <b><?= htmlspecialchars($bom['product_name']) ?></b>
                        <small><?= htmlspecialchars($bom['product_code']) ?></small>
                    </div>
                    <span class="material-version"><?= htmlspecialchars($bom['version']) ?></span>
                    <span class="material-pill <?= (int) $bom['is_active'] === 1 ? 'pill-aman' : 'pill-waspada' ?>"><?= (int) $bom['is_active'] === 1 ? 'Aktif' : 'Draf' ?></span>
                </div>
            <?php endforeach; ?>
        </a>

        <a class="material-bento-card material-bento-orange" href="/master/storage/forecast">
            <div class="material-bento-head">
                <span class="material-bento-icon"><i class="fas fa-chart-line"></i></span>
                <div class="material-bento-title">
                    <small>Data Forecast</small>
                    <strong><?= $forecastCount ?></strong>
                </div>
                <span class="material-bento-arrow"><i class="fas fa-arrow-right"></i></span>
            </div>
            <p class="material-bento-subtitle">Hasil perhitungan kebutuhan</p>
            <?php if ($forecastCount === 0): ?>
                <div class="material-bento-empty">
                    <i class="fas fa-chart-line"></i>
                    <b>Belum ada data forecast</b>
                    <span>Muncul setelah forecast disimpan.</span>
                </div>
            <?php endif; ?>
        </a>

        <a class="material-bento-card material-bento-pink" href="/master/storage/sales">
            <div class="material-bento-head">
                <span class="material-bento-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="material-bento-title">
                    <small>Data Penjualan</small>
                    <strong><?= $salesCount ?></strong>
                </div>
                <span class="material-bento-arrow"><i class="fas fa-arrow-right"></i></span>
            </div>
            <p class="material-bento-subtitle">Riwayat penjualan produk</p>
            <?php if ($salesCount === 0): ?>
                <div class="material-bento-empty">
                    <i class="fas fa-file-invoice"></i>
                    <b>Belum ada riwayat penjualan</b>
                    <span>Muncul setelah data penjualan diimpor.</span>
                </div>
            <?php endif; ?>
        </a>
    </div>
</div>

@include('layouts.footer')
