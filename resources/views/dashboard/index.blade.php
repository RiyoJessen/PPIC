@php
$pageTitle = 'Executive Dashboard PPIC';
@endphp
@include('layouts.header')

<!-- Header Title & Action -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Dashboard PPIC &amp; AI Control</h3>
        <p class="text-muted small mb-0">
            Monitoring terintegrasi stok bahan baku kimia, rencana produksi, dan pengadaan cerdas untuk <strong>PT Dunia Kimia Jaya Gresik</strong>.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (\App\Services\AccessControl::isSuperAdmin()): ?>
            <?php if (($depleting_30_count ?? $kritis_count) > 0): ?>
                <a href="/notifications/send-stock-alert" class="btn btn-danger btn-sm px-3 shadow-sm" title="Kirim notifikasi email proyeksi stok habis ke riyo.nurmalaya22@student.uisi.ac.id" onclick="return confirm('Kirim notifikasi email proyeksi stok habis ke riyo.nurmalaya22@student.uisi.ac.id (Mulai <?= $earliest_depletion_date ?? 'Segera' ?>)?')">
                    <i class="fas fa-envelope-open-text me-1"></i> Kirim Notifikasi Email (Habis Mulai <?= $earliest_depletion_date ?? 'Segera' ?> &bull; <?= $earliest_days ?? '' ?> Hari Lagi)
                </a>
            <?php endif; ?>
            <a href="/ai/forecast" class="btn btn-dark btn-sm px-3 shadow-sm">
                <i class="fas fa-wand-magic-sparkles me-1 text-warning"></i> AI Analisis Material &amp; Harga
            </a>
        <?php elseif (\App\Services\AccessControl::getCurrentRole() === \App\Services\AccessControl::ROLE_PURCHASING): ?>
            <a href="/purchasing/pr" class="btn btn-success btn-sm px-3 shadow-sm">
                <i class="fas fa-file-invoice-dollar me-1"></i> Rekomendasi PR &amp; Evaluasi Vendor
            </a>
            <a href="/purchasing/po" class="btn btn-teal btn-sm px-3 shadow-sm text-white" style="background-color: #0d9488;">
                <i class="fas fa-truck-loading me-1"></i> Terbitkan Purchase Order (PO)
            </a>
        <?php elseif (\App\Services\AccessControl::getCurrentRole() === \App\Services\AccessControl::ROLE_RD): ?>
            <a href="/master/boms" class="btn btn-warning btn-sm px-3 shadow-sm text-dark">
                <i class="fas fa-sitemap me-1"></i> Evaluasi Formula &amp; BOM Kimia
            </a>
            <a href="/master/materials" class="btn btn-primary btn-sm px-3 shadow-sm">
                <i class="fas fa-flask me-1"></i> Master Material &amp; Safety Stock
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Access Banner -->
<?php if (\App\Services\AccessControl::isSuperAdmin()): ?>
    <div class="alert alert-danger-subtle border border-danger-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-2xs">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt fs-4 me-3 text-danger"></i>
            <div>
                <strong class="text-danger">SUPER ADMIN AKTIF (PPIC)</strong>:
                <span class="text-secondary small ms-1">Anda memiliki wewenang penuh untuk <strong>melihat, menginput, mengubah, dan menghapus</strong> data di seluruh 5 divisi (PPIC, Purchasing, Gudang, QC/QA, dan R&amp;D).</span>
            </div>
        </div>
        <span class="badge bg-danger text-white">Semua Akses Terbuka</span>
    </div>
<?php else: ?>
    <?php $roleCode = \App\Services\AccessControl::getCurrentRole(); ?>
    <div class="alert alert-info-subtle border border-info-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-2xs">
        <div class="d-flex align-items-center">
            <i class="fas fa-lock fs-4 me-3 text-info"></i>
            <div>
                <strong class="text-info-emphasis">AKSES DIVISI TERBATAS</strong>:
                <span class="text-secondary small ms-1">Saat ini Anda login sebagai <strong><?= \App\Services\AccessControl::getDivisionLabel($roleCode) ?></strong>. Anda hanya memiliki izin akses untuk <strong>melihat, menginput, dan menghapus</strong> data pada modul divisi Anda sendiri. Modul divisi lain terkunci khusus Super Admin (PPIC).</span>
            </div>
        </div>
        <span class="badge bg-secondary">Akses Terkunci ke Divisi Lain</span>
    </div>
<?php endif; ?>

<!-- KPI Cards Row -->
<div class="row g-3 mb-4">
    <!-- Card 1: Kritis -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm border-start border-4 border-danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Bahan Status Kritis</span>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline">
                    <h2 class="fw-bold text-danger mb-0"><?= $kritis_count ?></h2>
                    <span class="text-muted small ms-2">Bahan Baku</span>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-arrow-down text-danger me-1"></i> Stok tersedia &le; safety stock
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Waspada -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm border-start border-4 border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Bahan Status Waspada</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1">
                        <i class="fas fa-bell"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline">
                    <h2 class="fw-bold text-warning-emphasis mb-0"><?= $waspada_count ?></h2>
                    <span class="text-muted small ms-2">Bahan Baku</span>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-exclamation-circle text-warning me-1"></i> Mendekati Reorder Point (ROP)
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: PR Pending -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Purchase Requisition Aktif</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline">
                    <h2 class="fw-bold text-primary mb-0"><?= count($pending_prs) ?></h2>
                    <span class="text-muted small ms-2">PR Menunggu</span>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-check-circle text-primary me-1"></i> Menunggu persetujuan &amp; terbit PO
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Open PO -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Purchase Order Berjalan</span>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">
                        <i class="fas fa-truck"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline">
                    <h2 class="fw-bold text-success mb-0"><?= count($open_pos) ?></h2>
                    <span class="text-muted small ms-2">PO Terbit</span>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-clock text-success me-1"></i> Menunggu kedatangan fisik
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Gemini Insight Banner -->
<?php if (!empty($recent_ai_logs)): ?>
    <?php $firstLog = $recent_ai_logs[0]; ?>
    <div class="card ai-recommendation-card mb-4 border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge ai-badge px-3 py-2 rounded-pill">
                        <i class="fas fa-sparkles me-1"></i> Rekomendasi AI Gemini
                    </span>
                    <span class="text-muted small">Update: <?= date('d M Y', strtotime($firstLog['analysis_date'])) ?></span>
                </div>
                <a href="/ai/forecast" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                    Buka Forecasting Detail <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <h5 class="fw-bold text-dark mb-2">
                Sorotan Kritis: <?= htmlspecialchars($firstLog['material_name']) ?>
            </h5>
            <p class="text-secondary mb-3 lh-base" style="font-size: 0.95rem;">
                <?= nl2br(htmlspecialchars($firstLog['ai_narrative'])) ?>
            </p>
            <div class="d-flex flex-wrap gap-3 align-items-center pt-2 border-top border-secondary-subtle">
                <div class="small">
                    <strong>Rekomendasi Beli (Q):</strong> 
                    <span class="badge bg-primary fs-6 ms-1"><?= number_format((float)$firstLog['recommended_qty'], 0, ',', '.') ?> <?= $firstLog['material_unit'] ?></span>
                </div>
                <div class="small">
                    <strong>Tingkat Risiko:</strong> 
                    <span class="badge bg-danger ms-1"><?= $firstLog['risk_level'] ?></span>
                </div>
                <div class="ms-auto">
                    <a href="/purchasing/pr/create-from-ai?material_id=<?= $firstLog['material_id'] ?>&qty=<?= $firstLog['recommended_qty'] ?>" class="btn btn-primary btn-sm px-3 shadow-sm">
                        <i class="fas fa-cart-plus me-1"></i> Ajukan PR Otomatis
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- Bar Chart: Bahan Kritis vs Safety Stock -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i> Perbandingan Stok Tersedia vs Safety Stock (Top Bahan Baku)</span>
                <span class="badge bg-light text-muted border">Satuan Kg / L</span>
            </div>
            <div class="card-body">
                <canvas id="stockComparisonChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut Chart: Sebaran Status -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-chart-pie me-2 text-warning"></i> Komposisi Kesehatan Stok
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div style="width: 220px; height: 220px;">
                    <canvas id="stockStatusChart"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-3 small text-muted">
                    <span><i class="fas fa-circle text-danger me-1"></i> Kritis (<?= $kritis_count ?>)</span>
                    <span><i class="fas fa-circle text-warning me-1"></i> Waspada (<?= $waspada_count ?>)</span>
                    <span><i class="fas fa-circle text-success me-1"></i> Aman (<?= $aman_count ?>)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-3 mb-4">
    <!-- Critical Materials Table -->
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Bahan Baku Berisiko &amp; Rekomendasi Tindak Lanjut</span>
                <a href="/master/materials" class="btn btn-link btn-sm text-decoration-none">Lihat Semua Stok &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Bahan Baku</th>
                            <th class="text-end">Tersedia</th>
                            <th class="text-center">Estimasi Habis (AI)</th>
                            <th class="text-center">Batas Order (Lead Time)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($kritis_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i><br>
                                    Seluruh stok bahan baku berada pada batas aman.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($kritis_items, 0, 5) as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($item['name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($item['code']) ?></small>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-danger">
                                        <?= number_format($item['available_stock'], 0, ',', '.') ?> <?= $item['unit'] ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= (($item['days_of_supply'] ?? 999) < 15) ? 'bg-danger' : 'bg-warning text-dark' ?> rounded-pill">
                                            <?= $item['depletion_date'] ?? '-' ?>
                                        </span>
                                        <div class="small text-muted" style="font-size: 11px;"><?= $item['days_of_supply'] ?> hari lagi</div>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($item['is_order_due_today'])): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-1">
                                                <i class="fas fa-exclamation-circle me-1"></i> Hari Ini! (<?= $item['latest_order_date'] ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border rounded-pill">
                                                <?= $item['latest_order_date'] ?> (<?= $item['order_days_left'] ?> hr lagi)
                                            </span>
                                        <?php endif; ?>
                                        <div class="text-muted" style="font-size: 10px;">Lead: <?= $item['lead_time_days'] ?> hr</div>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-outline-dark btn-sm py-0 px-2" onclick="runAiAnalysis(<?= $item['id'] ?>, this)">
                                            <i class="fas fa-wand-magic-sparkles text-purple"></i> AI
                                        </button>
                                        <a href="/purchasing/pr/create-from-ai?material_id=<?= $item['id'] ?>&qty=<?= max((float)$item['moq'], (float)$item['safety_stock'] - (float)$item['available_stock']) ?>" class="btn btn-primary btn-sm py-0 px-2 ms-1" title="Ajukan PR">
                                            <i class="fas fa-plus"></i> PR
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Active Production & POs -->
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-truck-moving text-primary me-2"></i> Purchase Order (PO) Berjalan</span>
                <a href="/purchasing/po" class="btn btn-link btn-sm text-decoration-none">Semua PO &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>No. PO / Supplier</th>
                            <th>Est. Tiba</th>
                            <th class="text-end">Total Nilai</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($open_pos)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Tidak ada PO yang sedang berjalan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($open_pos as $po): ?>
                                <tr>
                                    <td>
                                        <a href="/purchasing/po/show?id=<?= $po['id'] ?>" class="fw-semibold text-primary text-decoration-none">
                                            <?= htmlspecialchars($po['po_number']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($po['supplier_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= date('d M Y', strtotime($po['expected_arrival_date'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace">
                                        Rp <?= number_format((float)$po['total_amount'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border px-2 py-1">Issued</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top small text-muted d-flex justify-content-between align-items-center">
                <span>AI Forecasting &amp; Pengadaan</span>
                <a href="/ai/forecast" class="btn btn-outline-primary btn-sm py-0">Buka AI Forecasting</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal AI Quick Analysis -->
<div class="modal fade" id="aiAnalysisModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title d-flex align-items-center gap-2">
                    <i class="fas fa-wand-magic-sparkles text-warning"></i>
                    Analisis AI Google Gemini: <span id="modalMaterialName" class="text-info fw-bold"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                    <div>
                        <span class="text-muted small">Status Persediaan:</span>
                        <span id="modalRiskBadge" class="badge ms-1"></span>
                    </div>
                    <div>
                        <span class="text-muted small">Rekomendasi Pembelian (Q):</span>
                        <span id="modalRecommendedQty" class="fw-bold font-monospace text-primary ms-1"></span>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">Penjelasan Naratif &amp; Evaluasi Risiko:</h6>
                <div id="modalAiNarrative" class="p-3 rounded bg-light border text-secondary lh-base mb-3 font-sans" style="font-size: 0.95rem;">
                </div>

                <div class="text-muted small font-monospace" style="font-size: 11px;">
                    <i class="fas fa-microchip me-1"></i> Sumber Analisis: <span id="modalAiSource"></span>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="modalCreatePrBtn" class="btn btn-primary btn-sm px-3 shadow-sm">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Buat PR Otomatis
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Chart initialization scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Bar Chart: Stok Tersedia vs Safety Stock
    <?php
    $labels = [];
    $availData = [];
    $safetyData = [];
    foreach (array_slice($materials, 0, 8) as $m) {
        $labels[] = $m['code'];
        $availData[] = $m['available_stock'];
        $safetyData[] = $m['safety_stock'];
    }
    ?>
    const barCtx = document.getElementById('stockComparisonChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Stok Bebas Tersedia',
                    data: <?= json_encode($availData) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.85)',
                    borderRadius: 4,
                },
                {
                    label: 'Safety Stock Minimum',
                    data: <?= json_encode($safetyData) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 2. Doughnut Chart: Sebaran Status
    const doughnutCtx = document.getElementById('stockStatusChart').getContext('2d');
    new Chart(doughnutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Kritis', 'Waspada', 'Aman'],
            datasets: [{
                data: [<?= $kritis_count ?>, <?= $waspada_count ?>, <?= $aman_count ?>],
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

@include('layouts.footer')
