@php
$pageTitle = 'Master Produk Jadi DKJ';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Persediaan Produk Jadi</h3>
        <p class="text-muted small mb-0">
            Daftar produk kimia hasil produksi beserta stok, standar batch, dan status formula BOM.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createProductModal">
            <i class="fas fa-plus-circle me-1"></i> Tambah Produk Baru
        </button>
        <a href="/master/boms" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sitemap me-1"></i> Kelola Formula BOM
        </a>
    </div>
</div>

<?php
$productCount = count($products);
$withoutFormulaCount = 0;
$lowStockCount = 0;
foreach ($products as $product) {
    if ((int) $product['has_active_bom'] === 0) $withoutFormulaCount++;
    $standardBatch = (float) ($product['standard_batch_size'] ?? 0);
    if ($standardBatch > 0 && (float) $product['finished_good_stock'] / $standardBatch < 0.25) $lowStockCount++;
}
?>
<div class="finish-product-toolbar">
    <div class="finish-product-search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="search" id="productSearch" placeholder="Cari produk, kode, atau kategori" aria-label="Cari produk">
    </div>
    <div class="finish-product-filters" role="group" aria-label="Filter produk">
        <button type="button" class="finish-product-filter is-active" data-product-filter="all" aria-pressed="true">Semua <span><?= $productCount ?></span></button>
        <button type="button" class="finish-product-filter" data-product-filter="no-formula" aria-pressed="false">Tanpa formula <span><?= $withoutFormulaCount ?></span></button>
        <button type="button" class="finish-product-filter" data-product-filter="low-stock" aria-pressed="false">Stok rendah <span><?= $lowStockCount ?></span></button>
    </div>
</div>

<div class="finish-product-table-wrap">
    <div class="table-responsive">
        <table class="table finish-product-table align-middle mb-0" id="finishProductTable">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="text-end">Batch standar</th>
                    <th class="text-end">Stok finish good</th>
                    <th>Level stok</th>
                    <th>Formula BOM</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="6" class="finish-product-empty">Belum ada produk jadi yang terdaftar.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p):
                        $standardBatch = (float) ($p['standard_batch_size'] ?? 0);
                        $stock = (float) ($p['finished_good_stock'] ?? 0);
                        $stockPercent = $standardBatch > 0 ? ($stock / $standardBatch) * 100 : 0;
                        $stockLevel = $stockPercent < 25 ? 'low' : ($stockPercent < 60 ? 'medium' : 'good');
                        $hasFormula = (int) $p['has_active_bom'] > 0;
                        $searchText = strtolower($p['name'] . ' ' . $p['code'] . ' ' . ($p['category'] ?? ''));
                    ?>
                        <tr data-product-row data-has-formula="<?= $hasFormula ? '1' : '0' ?>" data-low-stock="<?= $stockPercent < 25 ? '1' : '0' ?>" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <div class="finish-product-name">
                                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                                    <span class="finish-product-active <?= (int) ($p['is_active'] ?? 1) === 1 ? 'is-active' : 'is-inactive' ?>">
                                        <?= (int) ($p['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </div>
                                <div class="finish-product-meta">
                                    <span class="finish-product-code"><?= htmlspecialchars($p['code']) ?></span>
                                    <span><?= htmlspecialchars($p['category'] ?? 'Produk jadi') ?></span>
                                </div>
                            </td>
                            <td class="text-end font-monospace">
                                <?= number_format($standardBatch, 0, ',', '.') ?> <?= htmlspecialchars($p['unit']) ?>
                            </td>
                            <td class="text-end">
                                <form action="/master/products/update-stock" method="POST" class="finish-product-stock-form">
                                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                                    <label class="visually-hidden" for="product-stock-<?= (int) $p['id'] ?>">Stok <?= htmlspecialchars($p['name']) ?></label>
                                    <input id="product-stock-<?= (int) $p['id'] ?>" type="number" name="quantity" min="0" step="0.01" value="<?= htmlspecialchars((string) $stock) ?>" required>
                                    <span><?= htmlspecialchars($p['unit']) ?></span>
                                    <button type="submit">Simpan</button>
                                </form>
                            </td>
                            <td>
                                <div class="finish-product-level">
                                    <div class="finish-product-progress <?= $stockLevel ?>">
                                        <span style="width:<?= min(100, max($stockPercent > 0 ? 1 : 0, $stockPercent)) ?>%"></span>
                                    </div>
                                    <small><?= number_format($stockPercent, 1, ',', '.') ?>% dari batch</small>
                                </div>
                            </td>
                            <td>
                                <?php if ($hasFormula): ?>
                                    <div class="finish-product-formula">
                                        <span class="finish-product-version"><?= htmlspecialchars($p['active_bom_version'] ?? 'Aktif') ?></span>
                                        <span class="finish-product-active is-active">Aktif</span>
                                        <?php if (!empty($p['source_formula_code'])): ?>
                                            <small class="formula-product-meta">Katalog <?= htmlspecialchars($p['source_formula_code']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="finish-product-no-formula"><i class="fas fa-triangle-exclamation"></i> Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="finish-product-actions">
                                    <?php if ($hasFormula): ?>
                                        <a href="/master/products/formula?id=<?= (int) $p['id'] ?>" class="finish-product-link">Kelola &amp; hitung <i class="fas fa-arrow-right"></i></a>
                                    <?php else: ?>
                                        <a href="/master/products/formula?id=<?= (int) $p['id'] ?>" class="finish-product-link">Atur formula <i class="fas fa-arrow-right"></i></a>
                                    <?php endif; ?>
                                    <a href="/master/products/delete?id=<?= (int) $p['id'] ?>" class="finish-product-delete" onclick="return confirm('Yakin ingin menghapus produk <?= htmlspecialchars($p['name']) ?> beserta formulanya?')" title="Hapus produk <?= htmlspecialchars($p['name']) ?>" aria-label="Hapus produk <?= htmlspecialchars($p['name']) ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="finishProductNoResults" hidden>
                        <td colspan="6" class="finish-product-empty">Tidak ada produk yang cocok dengan pencarian atau filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('finishProductTable');
    const searchInput = document.getElementById('productSearch');
    if (!table || !searchInput) return;

    const filterButtons = document.querySelectorAll('[data-product-filter]');
    const noResults = document.getElementById('finishProductNoResults');
    let activeFilter = 'all';

    function updateProductList() {
        const query = searchInput.value.trim().toLowerCase();
        const rows = Array.from(table.querySelectorAll('[data-product-row]'));
        let visibleCount = 0;

        rows.forEach(row => {
            const matchesFilter = activeFilter === 'all'
                || (activeFilter === 'no-formula' && row.dataset.hasFormula === '0')
                || (activeFilter === 'low-stock' && row.dataset.lowStock === '1');
            const matchesSearch = `${row.dataset.search || ''} ${row.textContent}`.toLowerCase().includes(query);
            row.hidden = !matchesFilter || !matchesSearch;
            if (!row.hidden) visibleCount++;
        });

        if (noResults) noResults.hidden = visibleCount > 0;
    }

    searchInput.addEventListener('input', updateProductList);
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.productFilter;
            filterButtons.forEach(filter => {
                const isSelected = filter === button;
                filter.classList.toggle('is-active', isSelected);
                filter.setAttribute('aria-pressed', String(isSelected));
            });
            updateProductList();
        });
    });
});
</script>

<!-- Modal Create Product -->
<div class="modal fade" id="createProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/master/products/store" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i> Tambah Master Produk Jadi Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Kode Produk</label>
                            <input type="text" name="code" class="form-control form-control-sm font-monospace" placeholder="PRD-005" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Nama Produk Jadi</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: DKJ Coagulant Mix C-50" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Kategori Produk</label>
                            <input type="text" name="category" class="form-control form-control-sm" value="Koagulan Cair" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-semibold">Satuan</label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="Kg" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-semibold">Std Batch</label>
                            <input type="number" min="0.01" step="0.01" name="standard_batch_size" class="form-control form-control-sm" value="5000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Stok Awal Finish Good</label>
                        <input type="number" step="0.01" min="0" name="initial_stock" class="form-control form-control-sm" value="0" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi / Spesifikasi Teknis</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Contoh: Larutan koagulan kimia tersintesis untuk proses sedimentasi..."></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Setelah produk disimpan, Anda langsung diarahkan untuk menyusun formula manual atau memilih formula katalog.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan &amp; lanjut ke formula</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layouts.footer')
