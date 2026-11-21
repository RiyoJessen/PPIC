<?php
$pageTitle = 'Laporan Operasional & Audit Trail';
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Laporan Terpadu &amp; Jejak Audit Sistem</h3>
        <p class="text-muted small mb-0">
            Audit trail seluruh aktivitas pengguna, mutasi stok persediaan, dan evaluasi rekomendasi AI di PT Dunia Kimia Jaya Gresik.
        </p>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="audit-tab" data-bs-toggle="tab" data-bs-target="#auditTabPane" type="button">
            <i class="fas fa-shield-alt me-1 text-primary"></i> Jejak Audit Pengguna (Audit Trail)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="forecast-tab" data-bs-toggle="tab" data-bs-target="#forecastTabPane" type="button">
            <i class="fas fa-wand-magic-sparkles me-1 text-purple"></i> Evaluasi Forecast AI
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stockTabPane" type="button">
            <i class="fas fa-boxes me-1 text-success"></i> Rekap Status Stok
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabsContent">
    <!-- Audit Tab -->
    <div class="tab-pane fade show active" id="auditTabPane">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-history me-2 text-primary"></i> Log Aktivitas Pengguna Terverifikasi</span>
                <span class="badge bg-light text-muted border">Menampilkan 100 log terakhir</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Modul</th>
                            <th>Aksi</th>
                            <th>Deskripsi Aktivitas</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($audits as $a): ?>
                            <tr>
                                <td class="font-monospace text-muted" style="font-size: 11px;">
                                    <?= date('d/m/Y H:i:s', strtotime($a['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($a['user_name'] ?? 'Sistem') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($a['user_role'] ?? 'System') ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['module']) ?></span></td>
                                <td><span class="font-monospace text-primary fw-semibold"><?= htmlspecialchars($a['action']) ?></span></td>
                                <td class="text-secondary" style="max-width: 400px;"><?= htmlspecialchars($a['description']) ?></td>
                                <td class="font-monospace text-muted small"><?= htmlspecialchars($a['ip_address'] ?? '127.0.0.1') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Forecast Tab -->
    <div class="tab-pane fade" id="forecastTabPane">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="fas fa-brain me-2 text-purple"></i> Riwayat &amp; Evaluasi Output Gemini AI
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Tgl Analisis</th>
                            <th>Bahan Baku</th>
                            <th class="text-end">Stok Saat Itu</th>
                            <th class="text-end">Safety Stock</th>
                            <th class="text-end">Rekomendasi Q</th>
                            <th class="text-center">Risiko</th>
                            <th>Penjelasan Naratif</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($forecast_evaluations as $fe): ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= date('d/m/Y', strtotime($fe['analysis_date'])) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($fe['material_name']) ?></td>
                                <td class="text-end font-monospace"><?= number_format((float)$fe['current_stock'], 0, ',', '.') ?> <?= $fe['material_unit'] ?></td>
                                <td class="text-end font-monospace"><?= number_format((float)$fe['safety_stock'], 0, ',', '.') ?></td>
                                <td class="text-end font-monospace fw-bold text-primary"><?= number_format((float)$fe['recommended_qty'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= strtolower($fe['risk_level']) ?> px-2 py-1 rounded-pill">
                                        <?= $fe['risk_level'] ?>
                                    </span>
                                </td>
                                <td class="text-secondary small" style="max-width: 450px;">
                                    <?= nl2br(htmlspecialchars($fe['ai_narrative'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Tab -->
    <div class="tab-pane fade" id="stockTabPane">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="fas fa-boxes me-2 text-success"></i> Rekapitulasi Saldo Persediaan
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Bahan</th>
                            <th class="text-end">Stok Fisik</th>
                            <th class="text-end">Teralokasi</th>
                            <th class="text-end">Bebas Tersedia</th>
                            <th class="text-end">Safety Stock</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td class="font-monospace text-primary"><?= htmlspecialchars($m['code']) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($m['name']) ?></td>
                                <td class="text-end font-monospace"><?= number_format($m['physical_stock'], 0, ',', '.') ?> <?= $m['unit'] ?></td>
                                <td class="text-end font-monospace text-secondary"><?= number_format($m['allocated_stock'], 0, ',', '.') ?></td>
                                <td class="text-end font-monospace fw-bold <?= $m['available_stock'] <= $m['safety_stock'] ? 'text-danger' : 'text-success' ?>">
                                    <?= number_format($m['available_stock'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end font-monospace"><?= number_format($m['safety_stock'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= strtolower($m['stock_status']) ?> px-2 py-1 rounded-pill"><?= $m['stock_status'] ?></span>
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
