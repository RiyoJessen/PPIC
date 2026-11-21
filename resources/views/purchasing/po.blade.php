<?php
$pageTitle = 'Purchase Order (PO) & Penerimaan Barang';
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Purchase Order (PO) &amp; Penerimaan Barang</h3>
        <p class="text-muted small mb-0">
            Penerbitan pesanan resmi kepada supplier kimia dan konfirmasi penerimaan fisik barang ke gudang PT Dunia Kimia Jaya Gresik.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/purchasing/pr" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-invoice-dollar me-1"></i> Tinjau PR Menunggu PO
        </a>
    </div>
</div>

<!-- PO List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-truck-loading me-2 text-primary"></i> Daftar Purchase Order</span>
        <span class="badge bg-light text-muted border">Total: <?= count($pos) ?> Dokumen</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>No. PO</th>
                    <th>Supplier Rekanan</th>
                    <th>Ref. PR</th>
                    <th>Tgl Terbit</th>
                    <th>Est. Kedatangan (ETA)</th>
                    <th class="text-end">Total Nilai</th>
                    <th>Term Pembayaran</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi Gudang</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($pos)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Belum ada Purchase Order yang diterbitkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pos as $po): ?>
                        <tr>
                            <td class="font-monospace fw-semibold text-primary">
                                <a href="/purchasing/po/show?id=<?= $po['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($po['po_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($po['supplier_name']) ?></div>
                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($po['supplier_phone'] ?? '-') ?></small>
                            </td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($po['pr_number'] ?? '-') ?></td>
                            <td class="text-muted"><?= date('d M Y', strtotime($po['order_date'])) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <i class="fas fa-calendar-day text-secondary me-1"></i> <?= date('d M Y', strtotime($po['expected_arrival_date'])) ?>
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                Rp <?= number_format((float)$po['total_amount'], 0, ',', '.') ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($po['payment_terms']) ?></td>
                            <td class="text-center">
                                <?php
                                $statusBadge = match($po['status']) {
                                    'draft' => 'bg-secondary-subtle text-secondary border',
                                    'issued' => 'bg-info-subtle text-info border',
                                    'partially_received' => 'bg-warning-subtle text-warning-emphasis border',
                                    'received' => 'bg-success-subtle text-success border',
                                    'cancelled' => 'bg-danger-subtle text-danger border',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?> px-2 py-1">
                                    <?= strtoupper($po['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="/purchasing/po/show?id=<?= $po['id'] ?>" class="btn btn-primary btn-sm py-0 px-2">
                                    <i class="fas fa-box-open me-1"></i> Terima Fisik / Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

@include('layouts.footer')
