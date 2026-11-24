@php
$pageTitle = 'Formula & Bill of Materials (BOM)';
@endphp
@include('layouts.header')

<div class="formula-page">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Formula &amp; Bill of Materials</h3>
        <p class="formula-subtitle small mb-0">
            Resep per produk, dikelompokkan per versi. Klik Detail untuk melihat komposisi lengkap.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/master/boms/ai-advisor" class="btn formula-ai-button btn-sm px-3 fw-semibold">
            <i class="fas fa-wand-magic-sparkles me-1"></i> Saran formula dari AI
        </a>
        <button class="btn formula-create-button btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#createBomModal">
            <i class="fas fa-plus-circle me-1"></i> Buat formula baru
        </button>
    </div>
</div>

<div class="formula-toolbar mb-3">
    <div class="formula-search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="search" id="bomSearch" class="form-control" placeholder="Cari produk atau kode" aria-label="Cari produk atau kode formula">
    </div>
    <div class="formula-filters" role="group" aria-label="Filter status formula">
        <button type="button" class="formula-filter is-active" data-bom-filter="all" aria-pressed="true">Semua</button>
        <button type="button" class="formula-filter" data-bom-filter="active" aria-pressed="false">Aktif</button>
        <button type="button" class="formula-filter" data-bom-filter="draft" aria-pressed="false">Draf</button>
    </div>
    <span class="formula-count" id="bomCount"><?= count($boms) ?> versi formula</span>
</div>

<?php
$productVersionCounts = [];
foreach ($boms as $bom) {
    $productId = (int) $bom['product_id'];
    $productVersionCounts[$productId] = ($productVersionCounts[$productId] ?? 0) + 1;
}
$currentProductId = null;
?>
<div class="formula-table-wrap">
    <div class="table-responsive">
        <table class="table formula-table align-middle mb-0" id="bomFormulaTable">
            <thead>
                <tr>
                    <th>Versi</th>
                    <th class="text-end">Basis batch</th>
                    <th class="text-center">Yield</th>
                    <th class="text-center">Scrap</th>
                    <th>Komposisi terhadap basis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($boms)): ?>
                    <tr>
                        <td colspan="6" class="formula-empty-state">Belum ada formula yang dibuat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($boms as $b): ?>
                        <?php if ($currentProductId !== (int) $b['product_id']): ?>
                            <?php $currentProductId = (int) $b['product_id']; ?>
                            <tr class="bom-product-group" data-product-group="<?= $currentProductId ?>">
                                <td colspan="6">
                                    <strong><?= htmlspecialchars($b['product_name']) ?></strong>
                                    <span class="formula-product-meta"><?= htmlspecialchars($b['product_code']) ?>, <?= $productVersionCounts[$currentProductId] ?> versi</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php
                        $compositionTotal = 0.0;
                        foreach ($b['items'] as $item) {
                            $compositionTotal += (float) $item['quantity_per_batch'];
                        }
                        $batchBasis = (float) $b['batch_size'];
                        $compositionPct = $batchBasis > 0 ? ($compositionTotal / $batchBasis) * 100 : 0;
                        $progressPct = min(100, max(0, $compositionPct));
                        $isActive = (int) $b['is_active'] === 1;
                        $matchesBasis = abs($compositionTotal - $batchBasis) <= max(0.01, $batchBasis * 0.01);
                        ?>
                        <tr class="bom-version-row" data-product-group="<?= (int) $b['product_id'] ?>" data-status="<?= $isActive ? 'active' : 'draft' ?>" data-details-id="bom-detail-<?= (int) $b['id'] ?>" data-search="<?= htmlspecialchars(strtolower($b['product_name'] . ' ' . $b['product_code']), ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <span class="formula-version-badge"><?= htmlspecialchars($b['version']) ?></span>
                                <span class="formula-status-badge <?= $isActive ? 'is-active' : 'is-draft' ?>"><?= $isActive ? 'Aktif' : 'Draf' ?></span>
                            </td>
                            <td class="text-end font-monospace"><?= number_format($batchBasis, 0, ',', '.') ?> <?= htmlspecialchars($b['unit']) ?></td>
                            <td class="text-center"><?= number_format((float) $b['yield_pct'], 0, ',', '.') ?>%</td>
                            <td class="text-center"><?= number_format((float) $b['scrap_pct'], 0, ',', '.') ?>%</td>
                            <td>
                                <div class="formula-composition-meter" role="img" aria-label="Total komposisi <?= number_format($compositionPct, 1, ',', '.') ?> persen dari basis batch">
                                    <?php foreach ($b['items'] as $item): ?>
                                        <?php $segmentPct = $compositionTotal > 0 ? $progressPct * (float) $item['quantity_per_batch'] / $compositionTotal : 0; ?>
                                        <span style="width: <?= number_format($segmentPct, 2, '.', '') ?>%"></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="formula-composition-label <?= $matchesBasis ? 'is-matched' : 'is-mismatch' ?>">
                                    <?php if ($matchesBasis): ?>
                                        Pas dengan basis batch
                                    <?php else: ?>
                                        Total <?= number_format($compositionTotal, 0, ',', '.') ?> <?= htmlspecialchars($b['unit']) ?>, basis <?= number_format($batchBasis, 0, ',', '.') ?> <?= htmlspecialchars($b['unit']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="formula-row-actions">
                                    <button type="button" class="formula-detail-button" data-bom-details-toggle="bom-detail-<?= (int) $b['id'] ?>" aria-expanded="false" aria-controls="bom-detail-<?= (int) $b['id'] ?>">
                                        Detail
                                    </button>
                                    <div class="dropdown">
                                        <button type="button" class="formula-more-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aksi formula <?= htmlspecialchars($b['version']) ?>">
                                            <i class="fas fa-ellipsis"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end formula-actions-menu">
                                            <li><a class="dropdown-item" href="/ai/forecast"><i class="fas fa-wand-magic-sparkles me-2"></i>Analisis kebutuhan AI</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="/master/boms/delete?id=<?= (int) $b['id'] ?>" onclick="return confirm('Yakin ingin menghapus formula BOM <?= htmlspecialchars($b['version']) ?> ini?')">
                                                    <i class="fas fa-trash-alt me-2"></i>Hapus formula
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="bom-detail-row" id="bom-detail-<?= (int) $b['id'] ?>" data-product-group="<?= (int) $b['product_id'] ?>" hidden>
                            <td colspan="6">
                                <div class="formula-detail-content">
                                    <?php foreach ($b['items'] as $item): ?>
                                        <div class="formula-detail-item">
                                            <span>
                                                <strong><?= htmlspecialchars($item['material_name']) ?></strong>
                                                <small class="formula-product-meta"><?= htmlspecialchars($item['material_code']) ?></small>
                                            </span>
                                            <span class="font-monospace"><?= number_format((float) $item['quantity_per_batch'], 1, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?></span>
                                            <span class="formula-product-meta"><?= htmlspecialchars($item['notes'] ?: '-') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($b['notes'])): ?>
                                        <div class="formula-process-note">Catatan proses: <?= htmlspecialchars($b['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="bomNoResults" hidden>
                        <td colspan="6" class="formula-empty-state">Tidak ada formula yang cocok dengan pencarian atau filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('bomFormulaTable');
    const searchInput = document.getElementById('bomSearch');
    if (!table || !searchInput) return;

    const filterButtons = document.querySelectorAll('[data-bom-filter]');
    const countLabel = document.getElementById('bomCount');
    const noResults = document.getElementById('bomNoResults');
    let activeFilter = 'all';

    function updateFormulaList() {
        const query = searchInput.value.trim().toLowerCase();
        const formulaRows = Array.from(table.querySelectorAll('.bom-version-row'));
        let visibleCount = 0;

        formulaRows.forEach(row => {
            const matchesFilter = activeFilter === 'all' || row.dataset.status === activeFilter;
            const detailsRow = document.getElementById(row.dataset.detailsId);
            const matchesSearch = `${row.dataset.search || ''} ${row.textContent} ${detailsRow?.textContent || ''}`.toLowerCase().includes(query);
            row.hidden = !matchesFilter || !matchesSearch;
            if (row.hidden && detailsRow) {
                detailsRow.hidden = true;
                const toggle = document.querySelector(`[data-bom-details-toggle="${row.dataset.detailsId}"]`);
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
            if (!row.hidden) visibleCount++;
        });

        table.querySelectorAll('.bom-detail-row').forEach(row => {
            const formulaRow = formulaRows.find(item => item.dataset.detailsId === row.id);
            if (!formulaRow || formulaRow.hidden) row.hidden = true;
        });

        table.querySelectorAll('.bom-product-group').forEach(group => {
            const hasVisibleFormula = formulaRows.some(row =>
                row.dataset.productGroup === group.dataset.productGroup && !row.hidden
            );
            group.hidden = !hasVisibleFormula;
        });

        if (noResults) noResults.hidden = visibleCount > 0;
        countLabel.textContent = `${visibleCount} versi formula`;
    }

    searchInput.addEventListener('input', updateFormulaList);
    table.querySelectorAll('[data-bom-details-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const detailsRow = document.getElementById(button.dataset.bomDetailsToggle);
            if (!detailsRow) return;
            detailsRow.hidden = !detailsRow.hidden;
            button.setAttribute('aria-expanded', String(!detailsRow.hidden));
        });
    });
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.bomFilter;
            filterButtons.forEach(filter => {
                const isSelected = filter === button;
                filter.classList.toggle('is-active', isSelected);
                filter.setAttribute('aria-pressed', String(isSelected));
            });
            updateFormulaList();
        });
    });
});
</script>

<!-- Modal Create BOM -->
<div class="modal fade" id="createBomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/master/boms/store" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-sitemap me-2 text-primary"></i> Buat Formula &amp; Bill of Materials (BOM) Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Produk Jadi Target</label>
                            <select name="product_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Produk Jadi --</option>
                                <?php foreach ($products as $prd): ?>
                                    <option value="<?= $prd['id'] ?>"><?= htmlspecialchars($prd['code']) ?> - <?= htmlspecialchars($prd['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-semibold">Versi Formula</label>
                            <input type="text" name="version" class="form-control form-control-sm font-monospace" value="v1.0" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-semibold">Batch Size (Basis)</label>
                            <input type="number" step="0.01" name="batch_size" class="form-control form-control-sm" value="5000" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Satuan Hasil</label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="Kg" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Target Yield (%)</label>
                            <input type="number" step="0.1" name="yield_pct" class="form-control form-control-sm" value="98.0" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Toleransi Scrap / Susut (%)</label>
                            <input type="number" step="0.1" name="scrap_pct" class="form-control form-control-sm" value="2.0" required>
                        </div>
                    </div>

                    <h6 class="small fw-bold text-uppercase text-dark mt-4 mb-2">Komposisi Bahan Baku Per Batch:</h6>
                    <div class="p-3 bg-light rounded border mb-3">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted">Bahan Baku #<?= $i+1 ?></label>
                                    <select name="items[<?= $i ?>][material_id]" class="form-select form-select-sm">
                                        <option value="">-- Pilih Bahan Baku --</option>
                                        <?php foreach ($materials as $m): ?>
                                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['code']) ?> - <?= htmlspecialchars($m['name']) ?> (<?= $m['unit'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <label class="form-label small text-muted">Jumlah Per Batch</label>
                                    <input type="number" step="0.01" name="items[<?= $i ?>][quantity_per_batch]" class="form-control form-control-sm" placeholder="0.00">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small text-muted">Keterangan Teknis</label>
                                    <input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm" placeholder="Contoh: Reaktor R-01">
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan Operasional / Prosedur Reaksi</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Suhu reaksi 95°C, agitasi 120 RPM..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan Formula BOM</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layouts.footer')
