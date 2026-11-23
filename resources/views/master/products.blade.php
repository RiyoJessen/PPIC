@php
$pageTitle = 'Master Produk Jadi DKJ';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Persediaan Produk Jadi</h3>
        <p class="text-muted small mb-0">
            Daftar produk kimia hasil produksi (koagulan, flokulan, penetral pH) beserta standar batch size dan status formula BOM.
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

<div class="row g-3">
    <?php foreach ($products as $p): ?>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary font-monospace"><?= htmlspecialchars($p['code']) ?></span>
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($p['name']) ?></h5>
                    <p class="text-muted small mb-3 lh-sm" style="min-height: 40px;">
                        <?= htmlspecialchars($p['description'] ?? 'Produk kimia industri PT Dunia Kimia Jaya Gresik.') ?>
                    </p>
                    <div class="pt-2 border-top small text-muted">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Batch Standar:</span>
                            <strong class="text-dark font-monospace"><?= number_format($p['standard_batch_size'], 0, ',', '.') ?> <?= $p['unit'] ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Formula BOM:</span>
                            <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Terhubung</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
                    <a href="/master/boms" class="btn btn-link btn-sm text-decoration-none py-0">
                        Cek Formula BOM &rarr;
                    </a>
                    <a href="/master/products/delete?id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Yakin ingin menghapus produk <?= htmlspecialchars($p['name']) ?> beserta formulanya?')" title="Hapus Produk">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

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
                            <input type="number" step="0.01" name="standard_batch_size" class="form-control form-control-sm" value="5000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi / Spesifikasi Teknis</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Contoh: Larutan koagulan kimia tersintesis untuk proses sedimentasi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layouts.footer')
