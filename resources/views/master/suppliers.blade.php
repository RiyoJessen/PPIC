<?php
$pageTitle = 'Master Supplier & Gudang';
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Supplier Rekanan &amp; Gudang Penyimpanan</h3>
        <p class="text-muted small mb-0">
            Daftar produsen &amp; distributor bahan kimia rekanan PT Dunia Kimia Jaya Gresik serta lokasi penyimpanan.
        </p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="fas fa-handshake me-2 text-primary"></i> Daftar Supplier Terdaftar
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Perusahaan</th>
                            <th>Kontak Person &amp; Telepon</th>
                            <th>Email</th>
                            <th>Alamat Kantor / Pabrik</th>
                            <th class="text-center">Standar Lead Time</th>
                            <th class="text-center">Rating Kualitas</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($s['code']) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($s['contact_person'] ?? '-') ?></div>
                                    <small class="text-muted font-monospace"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($s['phone'] ?? '-') ?></small>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($s['email'] ?? '-') ?></small></td>
                                <td><small class="text-muted"><?= htmlspecialchars($s['address'] ?? '-') ?></small></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= $s['lead_time_days'] ?> Hari</span></td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border">
                                        <i class="fas fa-star text-warning me-1"></i> <?= $s['rating'] ?> / 5.0
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('layouts.footer')
