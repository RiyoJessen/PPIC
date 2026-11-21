@php
$pageTitle = 'Formula & Bill of Materials (BOM)';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Formula &amp; Bill of Materials (BOM)</h3>
        <p class="text-muted small mb-0">
            Resep komposisi baku per standar batch, target yield persentase, dan toleransi susut/scrap produksi.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/master/boms/ai-advisor" class="btn btn-purple btn-sm px-3 shadow-sm text-white fw-semibold" style="background: linear-gradient(135deg, #9333ea, #7c3aed); border: none;">
            <i class="fas fa-wand-magic-sparkles me-1 text-warning"></i> AI Formulation Advisor (R&amp;D)
        </a>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createBomModal">
            <i class="fas fa-plus-circle me-1"></i> Buat Formula BOM Baru
        </button>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($boms as $b): ?>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary me-2"><?= htmlspecialchars($b['version']) ?></span>
                        <strong class="text-dark"><?= htmlspecialchars($b['product_name']) ?></strong>
                    </div>
                    <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($b['product_code']) ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between small text-muted mb-3 pb-2 border-bottom">
                        <span>Basis Batch: <strong class="text-dark font-monospace"><?= number_format($b['batch_size'], 0) ?> <?= $b['unit'] ?></strong></span>
                        <span>Yield: <strong class="text-success"><?= $b['yield_pct'] ?>%</strong> (Scrap: <?= $b['scrap_pct'] ?>%)</span>
                    </div>

                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Komposisi Bahan Baku Per Batch:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead class="table-light small">
                                <tr>
                                    <th>Bahan Baku</th>
                                    <th class="text-end">Jumlah / Batch</th>
                                    <th>Keterangan Teknis</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($b['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['material_name']) ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($item['material_code']) ?></small>
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-primary">
                                            <?= number_format((float)$item['quantity_per_batch'], 1, ',', '.') ?> <?= $item['unit'] ?>
                                        </td>
                                        <td class="text-muted small"><?= htmlspecialchars($item['notes'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($b['notes']): ?>
                        <div class="p-2 bg-light rounded text-muted small mt-2">
                            <i class="fas fa-info-circle text-primary me-1"></i> Catatan Proses: <?= htmlspecialchars($b['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
                    <a href="/master/boms/delete?id=<?= $b['id'] ?>" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Yakin ingin menghapus formula BOM <?= htmlspecialchars($b['version']) ?> ini?')" title="Hapus Formula BOM">
                        <i class="fas fa-trash-alt me-1"></i> Hapus Formula
                    </a>
                    <a href="/ai/forecast" class="btn btn-outline-primary btn-sm py-0">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Analisis Kebutuhan Material AI
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

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
