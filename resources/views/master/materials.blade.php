@php
$pageTitle = 'Master Data & Stok Bahan Baku (Raw Material)';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Master Data &amp; Stok Bahan Baku (Raw Material)</h3>
        <p class="text-muted small mb-0">
            Pengelolaan terpadu spesifikasi bahan baku, saldo stok tersedia, safety stock, ROP, lead time, MOQ, dan harga acuan.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#updateStockModal" onclick="openUpdateStockModal('', 'in')">
            <i class="fas fa-boxes-stacked me-1"></i> Update Stok RM
        </button>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createMaterialModal">
            <i class="fas fa-plus-circle me-1"></i> Tambah Bahan Baku
        </button>
        <a href="/settings" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-csv me-1"></i> Import CSV
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm table-search" data-target="#materialsMasterTable" placeholder="Cari nama bahan, kode...">
            </div>
            <div class="col-md-8 text-md-end small text-muted">
                <span class="me-3"><i class="fas fa-square text-danger me-1"></i> Kritis (&le; Safety Stock)</span>
                <span class="me-3"><i class="fas fa-square text-warning me-1"></i> Waspada (&le; ROP)</span>
                <span class="me-3"><i class="fas fa-square text-success me-1"></i> Aman</span>
                <span>Total: <strong><?= count($materials) ?></strong> Item</span>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="materialsMasterTable">
            <thead class="table-light small">
                <tr>
                    <th>Kode</th>
                    <th>Nama Bahan Baku</th>
                    <th class="text-end">Stok Tersedia</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Safety Stock</th>
                    <th class="text-end">ROP</th>
                    <th class="text-center">Lead Time</th>
                    <th class="text-end">MOQ</th>
                    <th class="text-end">Harga Satuan</th>
                    <th>Supplier</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($materials)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Belum ada master bahan baku terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $m): ?>
                        <tr>
                            <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($m['code']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($m['category_name'] ?? '-') ?> &bull; <?= htmlspecialchars($m['storage_condition'] ?: 'Penyimpanan standar') ?></small>
                            </td>
                            <td class="text-end font-monospace fw-bold <?= $m['stock_status'] === 'Kritis' ? 'text-danger' : ($m['stock_status'] === 'Waspada' ? 'text-warning-emphasis' : 'text-success') ?>">
                                <?= number_format((float)$m['available_stock'], 0, ',', '.') ?> <small class="fw-normal text-muted"><?= htmlspecialchars($m['unit']) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-<?= strtolower($m['stock_status']) ?> px-2 py-1 rounded-pill">
                                    <?= htmlspecialchars($m['stock_status']) ?>
                                </span>
                            </td>
                            <td class="text-end font-monospace text-danger"><?= number_format((float)$m['safety_stock'], 0, ',', '.') ?></td>
                            <td class="text-end font-monospace text-warning-emphasis"><?= number_format((float)$m['reorder_point'], 0, ',', '.') ?></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$m['lead_time_days'] ?> Hari</span></td>
                            <td class="text-end font-monospace"><?= number_format((float)$m['moq'], 0, ',', '.') ?></td>
                            <td class="text-end font-monospace fw-bold text-dark">Rp <?= number_format((float)$m['unit_cost'], 0, ',', '.') ?></td>
                            <td>
                                <div class="small fw-semibold"><?= htmlspecialchars($m['supplier_name'] ?? '-') ?></div>
                            </td>
                            <td class="text-center text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="/master/materials/detail?id=<?= (int)$m['id'] ?>" class="btn btn-outline-primary" title="Detail bahan baku">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#updateStockModal" onclick="openUpdateStockModal(<?= (int)$m['id'] ?>, 'in')" title="Update Stok Bahan Baku">
                                        <i class="fas fa-plus-minus"></i> Stok
                                    </button>
                                    <a href="/master/materials/delete?id=<?= (int)$m['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Yakin ingin menghapus bahan baku <?= htmlspecialchars($m['name']) ?>?')" title="Hapus Bahan Baku">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Update Stok Bahan Baku -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/master/materials/update-stock" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-boxes-stacked me-2 text-success"></i> Update Stok Bahan Baku (Raw Material)</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Bahan Baku</label>
                        <select name="material_id" id="stockMaterialSelect" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Bahan Baku --</option>
                            <?php foreach ($materials as $m): ?>
                                <option value="<?= (int)$m['id'] ?>">
                                    <?= htmlspecialchars($m['code']) ?> &mdash; <?= htmlspecialchars($m['name']) ?> (Stok: <?= number_format((float)$m['available_stock'], 0, ',', '.') ?> <?= htmlspecialchars($m['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jenis Update</label>
                            <select name="stock_action" id="stockActionSelect" class="form-select form-select-sm" required>
                                <option value="in">Tambah Stok (+ Masuk)</option>
                                <option value="out">Kurangi Stok (- Pemakaian/Opname)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jumlah</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control form-control-sm font-monospace" required placeholder="Contoh: 2500">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">No. Batch / Lot (Opsional)</label>
                            <input type="text" name="batch_number" class="form-control form-control-sm font-monospace" placeholder="Otomatis jika kosong">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tanggal Expired (Opsional)</label>
                            <input type="date" name="expired_date" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Catatan / Keterangan</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Penerimaan barang / stock opname bulanan">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-4"><i class="fas fa-save me-1"></i> Simpan Update Stok</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Create Material -->
<div class="modal fade" id="createMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/master/materials/store" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i> Tambah Master Bahan Baku Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Kode Bahan</label>
                            <input type="text" name="code" class="form-control form-control-sm font-monospace" placeholder="BB-011" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Nama Bahan Baku</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: Natrium Silikat 48%" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Kategori</label>
                            <select name="category_id" class="form-select form-select-sm" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Satuan</label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="Kg" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Stok Awal</label>
                            <input type="number" step="0.01" min="0" name="initial_stock" class="form-control form-control-sm font-monospace" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Harga Satuan (Rp)</label>
                            <input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm" placeholder="5000" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Safety Stock</label>
                            <input type="number" step="0.01" name="safety_stock" class="form-control form-control-sm" placeholder="5000" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Reorder Point (ROP)</label>
                            <input type="number" step="0.01" name="reorder_point" class="form-control form-control-sm" placeholder="8000" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Lead Time (Hari)</label>
                            <input type="number" name="lead_time_days" class="form-control form-control-sm" value="5" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">MOQ</label>
                            <input type="number" step="0.01" name="moq" class="form-control form-control-sm" value="1000" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Supplier Utama Rekanan</label>
                            <select name="preferred_supplier_id" class="form-select form-select-sm">
                                <option value="">-- Pilih Supplier --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Lokasi Penyimpanan</label>
                            <select name="warehouse_id" class="form-select form-select-sm">
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kondisi Khusus Penyimpanan</label>
                        <input type="text" name="storage_condition" class="form-control form-control-sm" placeholder="Contoh: Tangki Rubber-lined dengan scrubber...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan Bahan Baku</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUpdateStockModal(materialId, action) {
    const sel = document.getElementById('stockMaterialSelect');
    const act = document.getElementById('stockActionSelect');
    if (sel) sel.value = materialId ? String(materialId) : '';
    if (act && action) act.value = action;
}
</script>

@include('layouts.footer')
