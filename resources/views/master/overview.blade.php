@php
$pageTitle = 'Material';
@endphp
@include('layouts.header')

<div class="mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Material</h3>
        <p class="text-muted small mb-0">Penyimpanan data Raw Material, Finish Good, dan Formula yang sudah dibuat.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Data Raw Material</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($materials) ?></div>
                </div>
                <i class="fas fa-layer-group fs-3 text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Data Finish Good</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($products) ?></div>
                </div>
                <i class="fas fa-cube fs-3 text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Data Formula</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($boms) ?></div>
                </div>
                <i class="fas fa-flask fs-3 text-warning opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0"><i class="fas fa-layer-group text-primary me-2"></i>Data Raw Material</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Kode</th>
                    <th>Nama Bahan Baku</th>
                    <th>Kategori</th>
                    <th class="text-end">Stok Tersedia</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($materials)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data Raw Material.</td></tr>
                <?php else: ?>
                    <?php foreach ($materials as $material): ?>
                        <tr>
                            <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($material['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($material['name']) ?></td>
                            <td><?= htmlspecialchars($material['category_name'] ?? '-') ?></td>
                            <td class="text-end font-monospace"><?= number_format((float) $material['available_stock'], 0, ',', '.') ?> <?= htmlspecialchars($material['unit']) ?></td>
                            <td class="text-center"><span class="badge badge-<?= strtolower($material['stock_status']) ?>"><?= htmlspecialchars($material['stock_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0"><i class="fas fa-cube text-success me-2"></i>Data Finish Good</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Kode</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-end">Stok Finish Good</th>
                    <th class="text-end">Batch Standar</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($products)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data Finish Good.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($product['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                            <td class="text-end font-monospace"><?= number_format((float) $product['finished_good_stock'], 0, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float) $product['standard_batch_size'], 0, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0"><i class="fas fa-flask text-warning me-2"></i>Data Formula</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Produk Finish Good</th>
                    <th>Versi</th>
                    <th class="text-end">Basis Batch</th>
                    <th class="text-center">Yield</th>
                    <th>Komposisi Raw Material</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($boms)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data Formula.</td></tr>
                <?php else: ?>
                    <?php foreach ($boms as $bom): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($bom['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($bom['product_code']) ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($bom['version']) ?></span></td>
                            <td class="text-end font-monospace"><?= number_format((float) $bom['batch_size'], 0, ',', '.') ?> <?= htmlspecialchars($bom['unit']) ?></td>
                            <td class="text-center"><?= number_format((float) $bom['yield_pct'], 1, ',', '.') ?>%</td>
                            <td>
                                <?php if (empty($bom['items'])): ?>
                                    <span class="text-muted">Belum ada komposisi</span>
                                <?php else: ?>
                                    <?php foreach ($bom['items'] as $item): ?>
                                        <div>
                                            <span class="fw-semibold"><?= htmlspecialchars($item['material_name']) ?></span>
                                            <span class="text-muted">(<?= number_format((float) $item['quantity_per_batch'], 2, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

@include('layouts.footer')
