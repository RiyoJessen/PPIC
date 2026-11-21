<?php
$pageTitle = 'Detail Purchase Order: ' . htmlspecialchars($po['po_number']);
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="mb-4">
    <a href="/purchasing/po" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Purchase Order
    </a>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($po['po_number']) ?></h3>
            <p class="text-muted small mb-0">
                Supplier: <strong><?= htmlspecialchars($po['supplier_name']) ?></strong> &bull; Ref PR: <?= htmlspecialchars($po['pr_number'] ?? 'Manual') ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill text-uppercase">
                Status: <?= $po['status'] ?>
            </span>
        </div>
    </div>
</div>

<!-- Supplier & Order Meta -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-building text-primary me-2"></i> Informasi Supplier</h6>
            <div class="small">
                <strong><?= htmlspecialchars($po['supplier_name']) ?></strong><br>
                <span class="text-muted"><?= htmlspecialchars($po['supplier_address'] ?? 'Alamat terdaftar') ?></span><br>
                <i class="fas fa-phone-alt text-secondary me-1"></i> <?= htmlspecialchars($po['supplier_phone'] ?? '-') ?><br>
                <i class="fas fa-envelope text-secondary me-1"></i> <?= htmlspecialchars($po['supplier_email'] ?? '-') ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-file-contract text-primary me-2"></i> Ketentuan Pemesanan</h6>
            <div class="small">
                Tanggal Terbit: <strong><?= date('d M Y', strtotime($po['order_date'])) ?></strong><br>
                Estimasi Tiba (ETA): <strong class="text-primary"><?= date('d M Y', strtotime($po['expected_arrival_date'])) ?></strong><br>
                Ketentuan Bayar: <?= htmlspecialchars($po['payment_terms']) ?><br>
                Dibuat Oleh: <?= htmlspecialchars($po['creator_name'] ?? 'Purchasing') ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-money-bill-wave text-success me-2"></i> Total Nilai Transaksi</h6>
            <h3 class="fw-bold text-success mb-1">Rp <?= number_format((float)$po['total_amount'], 0, ',', '.') ?></h3>
            <small class="text-muted">Termasuk PPN &amp; Franco Pabrik Gresik</small>
        </div>
    </div>
</div>

<!-- Items & Receiving Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 fw-bold">
        <i class="fas fa-boxes me-2 text-primary"></i> Rincian Item Barang Dipesan
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Bahan Baku</th>
                    <th class="text-end">Jumlah Dipesan</th>
                    <th class="text-end">Jumlah Diterima</th>
                    <th class="text-end">Sisa Belum Tiba</th>
                    <th class="text-end">Harga Satuan</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-center">Status Item</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php foreach ($items as $item): ?>
                    <?php 
                    $ordered = (float) $item['quantity_ordered'];
                    $received = (float) $item['quantity_received'];
                    $remaining = max(0.0, $ordered - $received);
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['material_name']) ?></div>
                            <small class="text-muted font-monospace"><?= htmlspecialchars($item['material_code']) ?></small>
                        </td>
                        <td class="text-end font-monospace"><?= number_format($ordered, 0, ',', '.') ?> <?= $item['unit'] ?></td>
                        <td class="text-end font-monospace text-success fw-bold"><?= number_format($received, 0, ',', '.') ?></td>
                        <td class="text-end font-monospace <?= ($remaining > 0) ? 'text-danger' : 'text-muted' ?>">
                            <?= number_format($remaining, 0, ',', '.') ?> <?= $item['unit'] ?>
                        </td>
                        <td class="text-end font-monospace">Rp <?= number_format((float)$item['unit_price'], 0, ',', '.') ?></td>
                        <td class="text-end font-monospace fw-bold">Rp <?= number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <?php if ($remaining <= 0): ?>
                                <span class="badge bg-success-subtle text-success border">Lengkap</span>
                            <?php elseif ($received > 0): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border">Parsial</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Belum Tiba</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Goods Receiving Form (Penerimaan Fisik Gudang) -->
<?php if ($po['status'] !== 'received'): ?>
    <div class="card border-0 shadow-sm border-start border-4 border-success">
        <div class="card-header bg-white py-3 fw-bold">
            <i class="fas fa-truck-loading me-2 text-success"></i> Konfirmasi Penerimaan Fisik Barang Masuk (Warehouse Receiving)
        </div>
        <div class="card-body">
            <form action="/purchasing/po/receive" method="POST">
                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                
                <p class="text-muted small mb-3">
                    Masukkan nomor batch/lot supplier, verifikasi nomor COA, tanggal kedaluwarsa, dan gudang penyimpanan. Saldo stok fisik akan langsung bertambah dan kartu stok tercatat.
                </p>

                <?php foreach ($items as $item): ?>
                    <?php 
                    $rem = max(0.0, (float)$item['quantity_ordered'] - (float)$item['quantity_received']);
                    if ($rem <= 0) continue;
                    ?>
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-dark mb-2">Item: <?= htmlspecialchars($item['material_name']) ?> (Sisa: <?= number_format($rem, 0) ?> <?= $item['unit'] ?>)</h6>
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Jumlah Tiba (<?= $item['unit'] ?>)</label>
                                <input type="number" step="0.01" name="items[<?= $item['id'] ?>][qty]" class="form-control form-control-sm" value="<?= $rem ?>" max="<?= $rem ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">No. Batch/Lot</label>
                                <input type="text" name="items[<?= $item['id'] ?>][batch_number]" class="form-control form-control-sm font-monospace" value="LOT-<?= date('ymd') ?>-<?= rand(10, 99) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Nomor COA</label>
                                <input type="text" name="items[<?= $item['id'] ?>][coa_number]" class="form-control form-control-sm font-monospace" value="COA-<?= date('ymd') ?>-<?= rand(100, 999) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Tgl Expired</label>
                                <input type="date" name="items[<?= $item['id'] ?>][expired_date]" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+365 days')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Simpan ke Gudang</label>
                                <select name="items[<?= $item['id'] ?>][warehouse_id]" class="form-select form-select-sm" required>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= $wh['id'] ?>" <?= ($item['warehouse_id'] == $wh['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wh['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="text-end">
                    <button type="submit" class="btn btn-success px-4" onclick="return confirm('Konfirmasi penerimaan barang? Stok fisik gudang akan diperbarui.')">
                        <i class="fas fa-check-circle me-1"></i> Simpan Penerimaan Fisik &amp; Tambah Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fas fa-check-circle fs-4 me-2"></i>
        <div>Seluruh barang pada Purchase Order ini telah diterima lengkap di gudang.</div>
    </div>
<?php endif; ?>

@include('layouts.footer')
