@php
$pageTitle = 'Detail Formula - ' . $formula['formula_code'];
$totalPercentage = (float) $formula['total_fraction'] * 100;
$compositionComplete = abs($totalPercentage - 100) <= 0.01;
@endphp
@include('layouts.header')

<div class="material-detail-page">
    <a href="/master/boms" class="material-detail-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Data Formula
    </a>

    <header class="material-detail-heading">
        <div>
            <span class="material-detail-code"><?= htmlspecialchars($formula['formula_code']) ?></span>
            <h1><?= htmlspecialchars($formula['product_name']) ?></h1>
            <p>Rincian komposisi formula Vaslina berdasarkan workbook.</p>
            <?php if ($formula['formula_code'] === 'YB01'): ?>
                <small class="formula-product-meta">Label sementara; kode formula tidak dicantumkan di workbook.</small>
            <?php endif; ?>
        </div>
        <span class="formula-status-badge <?= $compositionComplete ? 'is-active' : 'is-draft' ?>">
            <?= $compositionComplete ? 'Total mendekati 100%' : 'Total sumber tidak mencapai 100%' ?>
        </span>
    </header>

    <section class="material-detail-summary">
        <div>
            <span>Kode formula</span>
            <strong><?= htmlspecialchars($formula['formula_code']) ?></strong>
        </div>
        <div>
            <span>Total komposisi</span>
            <strong><?= number_format($totalPercentage, 4, ',', '.') ?>%</strong>
        </div>
        <div>
            <span>Jumlah bahan</span>
            <strong><?= count($formula['items']) ?> bahan</strong>
        </div>
        <div>
            <span>Produk / pelanggan</span>
            <strong><?= count($formula['products']) ?> relasi</strong>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Komposisi bahan</h2>
                <p>Persentase setiap bahan sebagaimana tercatat pada workbook sumber.</p>
            </div>
            <span><?= count($formula['items']) ?> bahan</span>
        </div>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bahan baku</th>
                            <th class="text-end">Persentase</th>
                            <th>Proporsi komposisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formula['items'] as $item): ?>
                            <?php $itemPercentage = (float) $item['fraction'] * 100; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['material_code']) ?></strong></td>
                                <td class="text-end font-monospace"><?= number_format($itemPercentage, 4, ',', '.') ?>%</td>
                                <td>
                                    <div class="formula-composition-meter" role="img" aria-label="<?= htmlspecialchars($item['material_code']) ?> <?= number_format($itemPercentage, 4, ',', '.') ?> persen">
                                        <span style="width: <?= number_format(min(100, max(0, $itemPercentage)), 4, '.', '') ?>%"></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-end font-monospace"><?= number_format($totalPercentage, 4, ',', '.') ?>%</th>
                            <th>
                                <span class="formula-composition-label <?= $compositionComplete ? 'is-matched' : 'is-mismatch' ?>">
                                    <?= $compositionComplete ? 'Mendekati 100%' : 'Nilai asli workbook; tidak dinormalisasi' ?>
                                </span>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Produk dan pelanggan</h2>
                <p>Produk jadi, nomor material (Nomat), dan customer yang menggunakan varian formula ini.</p>
            </div>
            <span><?= count($formula['products']) ?> relasi</span>
        </div>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nomat</th>
                            <th>Produk</th>
                            <th>Pelanggan / Customer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($formula['products'])): ?>
                            <tr><td colspan="3" class="material-storage-empty">Belum ada produk atau pelanggan yang terhubung di workbook.</td></tr>
                        <?php else: ?>
                            <?php foreach ($formula['products'] as $product): ?>
                                <tr>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($product['nomat'] ?: '-') ?></span></td>
                                    <td><strong><?= htmlspecialchars($product['product_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($product['customer_name'] ?: 'Tidak dicantumkan') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

        <section class="material-detail-section">
                <div class="material-detail-section-heading">
                    <div>
                        <h2>Hubungkan ke Produk Jadi</h2>
                        <p>Pilih produk master dan padankan setiap bahan katalog ke material yang terdaftar.</p>
                    </div>
                </div>

                <?php if (empty($products) || empty($materials)): ?>
                    <div class="alert alert-warning mb-0">
                        <?= empty($products) ? 'Daftarkan produk jadi terlebih dahulu.' : 'Daftarkan material bahan baku terlebih dahulu.' ?>
                    </div>
                <?php else: ?>
                    <form action="/master/formulas/link-to-finished-good" method="POST" class="formula-link-form">
                        <input type="hidden" name="formula_id" value="<?= (int) $formula['id'] ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="formulaTargetProduct" class="form-label fw-semibold">Produk Jadi</label>
                                <select id="formulaTargetProduct" name="product_id" class="form-select" required>
                                    <option value="">-- Pilih produk jadi --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option
                                            value="<?= (int) $product['id'] ?>"
                                            data-standard-batch="<?= htmlspecialchars((string) $product['standard_batch_size'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-unit="<?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-active-bom="<?= htmlspecialchars((string) ($product['active_bom_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= (int) $selected_product_id === (int) $product['id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($product['code'] . ' - ' . $product['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" id="formulaBatchInfo">Pilih produk untuk melihat basis batch dan satuannya.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="formulaCodePreview" class="form-label fw-semibold">Versi BOM</label>
                                <input id="formulaCodePreview" type="text" class="form-control" value="<?= htmlspecialchars($formula['formula_code']) ?>" readonly>
                            </div>
                        </div>

                        <div class="alert alert-info py-2">
                            Perhitungan memakai persentase asli workbook tanpa normalisasi. Total komposisi varian ini
                            <strong><?= number_format($totalPercentage, 4, ',', '.') ?>%</strong>. Pastikan basis formula dan satuan produk/material sesuai.
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Bahan pada katalog</th>
                                        <th class="text-end">Persentase</th>
                                        <th>Padankan ke material</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formula['items'] as $item): ?>
                                        <?php
                                        $matchingMaterialIds = [];
                                        foreach ($materials as $material) {
                                            if (strcasecmp(trim($material['code']), trim($item['material_code'])) === 0
                                                || strcasecmp(trim($material['name']), trim($item['material_code'])) === 0) {
                                                $matchingMaterialIds[] = (int) $material['id'];
                                            }
                                        }
                                        $suggestedMaterialId = count($matchingMaterialIds) === 1 ? $matchingMaterialIds[0] : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($item['material_code']) ?></strong></td>
                                            <td class="text-end font-monospace"><?= number_format((float) $item['fraction'] * 100, 4, ',', '.') ?>%</td>
                                            <td>
                                                <select name="material_ids[<?= (int) $item['id'] ?>]" class="form-select form-select-sm" required>
                                                    <option value="">-- Pilih material --</option>
                                                    <?php foreach ($materials as $material): ?>
                                                        <option value="<?= (int) $material['id'] ?>" <?= $suggestedMaterialId === (int) $material['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($material['code'] . ' - ' . $material['name'] . ' (' . $material['unit'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-check mb-3" id="replaceActiveBomNotice" hidden>
                            <input class="form-check-input" type="checkbox" name="replace_active_bom" value="1" id="replaceActiveBom">
                            <label class="form-check-label" for="replaceActiveBom">
                                Jadikan formula ini BOM aktif dan nonaktifkan BOM aktif yang sekarang.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" <?= empty($materials) || empty($products) ? 'disabled' : '' ?>>
                            Hubungkan ke Produk Jadi
                        </button>
                    </form>
                <?php endif; ?>
        </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
        const productSelect = document.getElementById('formulaTargetProduct');
        const batchInfo = document.getElementById('formulaBatchInfo');
        const replaceNotice = document.getElementById('replaceActiveBomNotice');
        const replaceCheckbox = document.getElementById('replaceActiveBom');

        if (!productSelect) return;

        function updateProductSelection() {
                const option = productSelect.options[productSelect.selectedIndex];
                const activeBom = option ? option.dataset.activeBom : '';
                const batch = option ? option.dataset.standardBatch : '';
                const unit = option ? option.dataset.unit : '';
                if (batchInfo) {
                    batchInfo.textContent = batch && unit
                        ? `Basis perhitungan: ${batch} ${unit}; seluruh material harus memakai satuan yang sama.`
                        : 'Pilih produk untuk melihat basis batch dan satuannya.';
                }
                if (replaceNotice) replaceNotice.hidden = !activeBom;
                if (replaceCheckbox) {
                    replaceCheckbox.required = Boolean(activeBom);
                    if (!activeBom) replaceCheckbox.checked = false;
                }
        }

        productSelect.addEventListener('change', updateProductSelection);
        updateProductSelection();
});
</script>

@include('layouts.footer')
