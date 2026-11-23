@php
$activeSection = $_GET['section'] ?? 'overview';
if (!in_array($activeSection, ['overview', 'needs', 'history', 'forecast'], true)) {
    $activeSection = 'overview';
}
$pageTitle = 'Sales & Forecast';
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$sectionSalesHistories = $activeSection === 'needs'
    ? $needs_histories
    : $sales_histories;
@endphp
@include('layouts.header')

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-chart-line text-primary me-2"></i>Sales &amp; Forecast</h5>
                <p class="text-muted small mb-0">Kelola kebutuhan penjualan, riwayat penjualan, dan perencanaan forecast.</p>
            </div>
        </div>
        <nav class="row g-2 border-top pt-3" aria-label="Navigasi Sales & Forecast">
            <div class="col-12 col-md-4">
                <a href="/forecast-sales?section=needs&year=<?= (int)$year ?>" class="btn w-100 py-2 <?= $activeSection === 'needs' ? 'btn-primary' : 'btn-light' ?>">
                    <i class="fas fa-clipboard-list me-2"></i>Kebutuhan Sales
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="/forecast-sales?section=history&year=<?= (int)$year ?>" class="btn w-100 py-2 <?= $activeSection === 'history' ? 'btn-primary' : 'btn-light' ?>">
                    <i class="fas fa-clock-rotate-left me-2"></i>Riwayat Penjualan
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="/forecast-sales?section=forecast&year=<?= (int)$year ?>" class="btn w-100 py-2 <?= $activeSection === 'forecast' ? 'btn-primary' : 'btn-light' ?>">
                    <i class="fas fa-chart-column me-2"></i>Forecast 12 Bulan
                </a>
            </div>
        </nav>
    </div>
</div>

<?php if ($activeSection === 'overview'): ?>
    <h6 class="fw-bold text-dark mb-2">Ringkasan Sales</h6>
    <div class="row g-2 mb-2">
        <div class="col-12 col-sm-6">
            <div class="card h-100"><div class="card-body p-3">
                <div class="small text-muted">Total Kebutuhan Sales</div>
                <div class="fs-5 fw-semibold text-dark mt-1"><?= (int)$open_quotation_so ?> dokumen</div>
                <small class="text-muted">Quotation dan Sales Order aktif</small>
            </div></div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="card h-100"><div class="card-body p-3">
                <div class="small text-muted">Total Penjualan Aktual</div>
                <div class="fs-5 fw-semibold text-dark mt-1"><?= number_format((float)$total_actual_sales_qty, 0, ',', '.') ?> unit</div>
                <small class="text-muted">Berdasarkan transaksi yang selesai</small>
            </div></div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="card h-100"><div class="card-body p-3">
                <div class="small text-muted">Total Forecast</div>
                <div class="fs-5 fw-semibold text-dark mt-1"><?= number_format((float)$total_12m_forecast, 0, ',', '.') ?> unit</div>
                <small class="text-muted">Periode 12 bulan tahun <?= (int)$year ?></small>
            </div></div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="card h-100"><div class="card-body p-3">
                <div class="small text-muted">Status Forecast</div>
                <div class="fs-5 fw-semibold text-dark mt-1"><?= count($forecasts) ?> produk</div>
                <small class="text-muted">Tersedia untuk tahun <?= (int)$year ?></small>
            </div></div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body p-3">
            <h6 class="fw-bold mb-2">Tren Penjualan &amp; Forecast</h6>
            <div style="height: 190px;"><canvas id="salesForecastOverviewChart"></canvas></div>
        </div>
    </div>
<?php elseif ($activeSection === 'needs'): ?>
    <div class="row g-2 mb-3">
        <div class="col-12 col-sm-4">
            <div class="card h-100">
                <div class="card-body p-2 px-3">
                    <div class="text-muted small"><i class="fas fa-clipboard-list me-1"></i></div>
                    <div class="fs-5 fw-bold text-dark"><?= number_format((int)$needs_summary['total_requests'], 0, ',', '.') ?></div>
                    <div class="text-muted small">Total Permintaan</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card h-100">
                <div class="card-body p-2 px-3">
                    <div class="text-muted small"><i class="fas fa-cube me-1"></i></div>
                    <div class="fs-5 fw-bold text-dark"><?= number_format((int)$needs_summary['product_types'], 0, ',', '.') ?></div>
                    <div class="text-muted small">Jenis Produk</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card h-100">
                <div class="card-body p-2 px-3">
                    <div class="text-muted small"><i class="fas fa-users me-1"></i></div>
                    <div class="fs-5 fw-bold text-dark"><?= number_format((int)$needs_summary['customers'], 0, ',', '.') ?></div>
                    <div class="text-muted small">Customer</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h6 class="fw-bold mb-1">Kebutuhan Sales</h6>
                <p class="text-muted small mb-0">Catat kebutuhan pelanggan, quotation, dan Sales Order untuk perencanaan produksi.</p>
            </div>
            <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAddSales">
                <i class="fas fa-plus-circle me-1"></i> Input Kebutuhan Sales
            </button>
        </div>
    </div>
<?php elseif ($activeSection === 'forecast'): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">Forecast 12 Bulan</h6>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="/forecast-sales" class="d-flex align-items-center gap-1">
                <input type="hidden" name="section" value="forecast">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === (int)$year ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <form action="/forecast-sales/generate" method="POST" class="d-inline">
                <input type="hidden" name="forecast_year" value="<?= (int)$year ?>">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-wand-magic-sparkles me-1"></i> Generate Forecast</button>
            </form>
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalManualForecast">
                <i class="fas fa-calendar-edit me-1"></i> Input Manual
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Panel Impor CSV Data Penjualan Aktual + Grafik Forecast 12 Bulan -->
<?php if (in_array($activeSection, ['history', 'forecast'], true)): ?>
<div class="row g-4 mb-4">
    <?php if ($activeSection === 'history'): ?>
    <div class="col-12">
        <div class="row g-2">
            <div class="col-12 col-sm-6">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="text-muted small"><i class="fas fa-weight-hanging me-1"></i> Total Penjualan Aktual (<?= (int)$year ?>)</div>
                        <div class="fs-5 fw-bold text-dark mt-1"><?= number_format((float)$history_summary['total_sales_tons'], 2, ',', '.') ?> Ton</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="text-muted small"><i class="fas fa-receipt me-1"></i> Jumlah Transaksi (<?= (int)$year ?>)</div>
                        <div class="fs-5 fw-bold text-dark mt-1"><?= number_format((int)$history_summary['transaction_count'], 0, ',', '.') ?> transaksi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="fas fa-file-csv text-primary me-2"></i> Impor Data Penjualan Aktual (CSV)
            </div>
            <div class="card-body small">
                <p class="text-muted mb-3">
                    Punya data penjualan beberapa bulan terakhir di Excel? Simpan sebagai <strong>CSV</strong> lalu unggah di sini. Setelah data masuk, klik <strong>Generate Forecast 12 Bln Otomatis</strong>.
                </p>
                <form action="/forecast-sales/import-csv" method="POST" enctype="multipart/form-data" class="mb-3">
                    <div class="mb-2">
                        <input type="file" name="sales_csv" class="form-control form-control-sm" accept=".csv,text/csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-upload me-1"></i> Impor Data Penjualan Sekarang
                    </button>
                </form>
                <div class="p-2 bg-light rounded border mb-2" style="font-size: 11px;">
                    <strong>Kolom CSV:</strong><br>
                    <code>product_code, sale_month, quantity, unit_price, customer_name, notes</code>
                </div>
                <a href="/forecast-sales/download-template" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-download me-1"></i> Unduh Template CSV Penjualan
                </a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-chart-column text-primary me-2"></i> Tren Penjualan Bulanan (<?= (int)$year ?>)</span>
                <span class="badge bg-light text-muted border">Jan &ndash; Des <?= (int)$year ?></span>
            </div>
            <div class="card-body">
                <div style="height: 235px;">
                    <canvas id="monthlySalesHistoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($activeSection === 'forecast'): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-chart-line text-success me-2"></i> Grafik Riwayat Penjualan Aktual vs Forecast Sales 12 Bulan (<?= (int)$year ?>)</span>
                <span class="badge bg-light text-muted border">Jan &ndash; Des <?= (int)$year ?></span>
            </div>
            <div class="card-body">
                <div style="height: 235px;">
                    <canvas id="salesForecastChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Matriks Hasil Forecast Sales 12 Bulan -->
<?php if ($activeSection === 'forecast'): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold"><i class="fas fa-calendar-alt text-primary me-2"></i> Hasil Forecast Sales 12 Bulan (Tahun <?= (int)$year ?>)</span>
            <div class="text-muted small" style="font-size: 11px;">Dihitung otomatis dari pola tren &amp; musiman data riwayat penjualan aktual, atau dapat disesuaikan manual per bulan.</div>
        </div>
        <span class="badge bg-primary-subtle text-primary border"><?= count($forecasts) ?> Produk</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle mb-0 small">
            <thead class="table-light text-center">
                <tr>
                    <th class="text-start">Produk</th>
                    <th>Metode</th>
                    <?php foreach ($monthLabels as $ml): ?>
                        <th><?= $ml ?></th>
                    <?php endforeach; ?>
                    <th>Total 12 Bln</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forecasts)): ?>
                    <tr>
                        <td colspan="16" class="text-center text-muted py-4">
                            Belum ada Forecast Sales untuk tahun <?= (int)$year ?>. Silakan input/impor data penjualan lalu klik <strong>Generate Forecast 12 Bln Otomatis</strong> atau <strong>Input Manual 12 Bln</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($forecasts as $f): ?>
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($f['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($f['product_code']) ?></small>
                            </td>
                            <td class="text-center">
                                <?php if (str_starts_with((string)$f['method'], 'auto')): ?>
                                    <span class="badge bg-success-subtle text-success border">Auto AI</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">Manual</span>
                                <?php endif; ?>
                            </td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <td class="text-end font-monospace"><?= number_format((float)$f['m' . $m], 0, ',', '.') ?></td>
                            <?php endfor; ?>
                            <td class="text-end font-monospace fw-bold text-primary text-nowrap">
                                <?= number_format((float)$f['total_12m_qty'], 0, ',', '.') ?> <?= htmlspecialchars($f['product_unit']) ?>
                            </td>
                            <td class="text-center">
                                <a href="/forecast-sales/delete-forecast?id=<?= (int)$f['id'] ?>&year=<?= (int)$year ?>&section=forecast" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Hapus forecast 12 bulan untuk <?= htmlspecialchars($f['product_name']) ?>?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Cek Formula & Cek Raw Material Otomatis dari Hasil Forecast Sales -->
<?php if ($activeSection === 'forecast'): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold"><i class="fas fa-cubes text-warning me-2"></i> Cek Formula BOM &amp; Ketersediaan Raw Material (Dari Forecast <?= (int)$year ?>)</span>
            <div class="text-muted small" style="font-size: 11px;">Memecah kebutuhan bulanan Forecast Sales ke kebutuhan Raw Material berdasarkan Formula BOM aktif dan membandingkannya dengan stok tersedia.</div>
        </div>
        <a href="/ai/forecast" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-wand-magic-sparkles me-1"></i> Buka AI Analisis Material &amp; Harga &rarr;
        </a>
    </div>
    <div class="card-body p-3">
        <?php if (empty($mrp_evaluations)): ?>
            <div class="text-center text-muted small py-3">
                Hasil pengecekan Formula &amp; Raw Material akan muncul otomatis setelah Forecast Sales 12 Bulan dibuat.
            </div>
        <?php else: ?>
            <?php foreach ($mrp_evaluations as $ev): ?>
                <?php $efc = $ev['forecast']; ?>
                <div class="border rounded-3 p-3 mb-3 bg-light">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                        <div>
                            <strong class="text-dark"><?= htmlspecialchars($efc['product_name']) ?></strong>
                            <span class="badge bg-white text-muted border font-monospace ms-1"><?= htmlspecialchars($efc['product_code']) ?></span>
                            <span class="small text-muted ms-2">Kebutuhan Bulanan: <strong class="text-dark font-monospace"><?= number_format((float)$ev['monthly_demand'], 0, ',', '.') ?> <?= htmlspecialchars($efc['product_unit']) ?></strong></span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?php if (!$ev['has_formula']): ?>
                                <span class="badge bg-danger text-white">Formula Belum Tersedia</span>
                                <a href="/master/boms" class="btn btn-warning btn-sm py-0 text-dark fw-semibold">
                                    <i class="fas fa-flask-vial me-1"></i> Buat Formula (R&amp;D) &rarr;
                                </a>
                            <?php elseif ($ev['rm_status'] === 'available'): ?>
                                <span class="badge bg-success text-white"><i class="fas fa-check-circle me-1"></i> Raw Material Tersedia</span>
                            <?php else: ?>
                                <span class="badge bg-danger text-white"><i class="fas fa-exclamation-triangle me-1"></i> <?= (int)$ev['shortage_count'] ?> RM Kurang (AI Insight)</span>
                                <a href="/master/boms/ai-advisor?product_id=<?= (int)$efc['product_id'] ?>" class="btn btn-sm py-0 text-white" style="background: #7c3aed;">
                                    <i class="fas fa-wand-magic-sparkles me-1"></i> Formula Sesuai Stok (AI)
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($ev['has_formula'] && !empty($ev['rm_requirements'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Raw Material</th>
                                        <th class="text-end">Kebutuhan / Bln</th>
                                        <th class="text-end">Stok Tersedia</th>
                                        <th class="text-end">Kekurangan RM</th>
                                        <th>Supplier &amp; Lead Time</th>
                                        <th class="text-center">Tindak Lanjut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ev['rm_requirements'] as $rm): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($rm['material_name']) ?> <small class="text-muted font-monospace">(<?= htmlspecialchars($rm['material_code']) ?>)</small></td>
                                            <td class="text-end font-monospace"><?= number_format((float)$rm['needed_monthly'], 1, ',', '.') ?> <?= htmlspecialchars($rm['unit']) ?></td>
                                            <td class="text-end font-monospace <?= $rm['shortage_qty'] > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
                                                <?= number_format((float)$rm['available_stock'], 1, ',', '.') ?> <?= htmlspecialchars($rm['unit']) ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold <?= $rm['shortage_qty'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                                <?= $rm['shortage_qty'] > 0 ? '-' . number_format((float)$rm['shortage_qty'], 1, ',', '.') . ' ' . htmlspecialchars($rm['unit']) : '0' ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($rm['supplier_name']) ?> (<?= (int)$rm['lead_time_days'] ?> hr)</td>
                                            <td class="text-center">
                                                <?php if ($rm['shortage_qty'] > 0): ?>
                                                    <a href="/purchasing/pr/create-from-ai?material_id=<?= (int)$rm['material_id'] ?>&qty=<?= (float)$rm['recommended_buy_qty'] ?>" class="btn btn-primary btn-sm py-0 px-2">
                                                        <i class="fas fa-cart-plus me-1"></i> Buat PR (<?= number_format((float)$rm['recommended_buy_qty'], 0, ',', '.') ?>)
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border">Cukup</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Tabel Riwayat Data Sales, Quotation & SO -->
<?php if (in_array($activeSection, ['needs', 'history'], true)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas <?= $activeSection === 'needs' ? 'fa-clipboard-list text-primary' : 'fa-history text-info' ?> me-2"></i>
            <?= $activeSection === 'needs' ? 'Daftar Kebutuhan Sales Aktif (Quotation &amp; SO)' : 'Detail Riwayat Penjualan' ?>
        </span>
    </div>
    <?php if ($activeSection === 'needs'): ?>
        <div class="px-3 py-3 bg-light border-bottom">
            <form method="GET" action="/forecast-sales" class="row g-2 align-items-end">
                <input type="hidden" name="section" value="needs">
                <input type="hidden" name="year" value="<?= (int)$year ?>">
                <div class="col-12 col-md-4">
                    <label for="needsStartDate" class="form-label small fw-semibold mb-1">Tanggal Mulai</label>
                    <input type="date" id="needsStartDate" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($needs_start_date ?? '') ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label for="needsProductId" class="form-label small fw-semibold mb-1">Produk</label>
                    <select id="needsProductId" name="needs_product_id" class="form-select form-select-sm">
                        <option value="">Semua Produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int)$product['id'] ?>" <?= (int)$needs_product_id === (int)$product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['code'] . ' — ' . $product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                    <a href="/forecast-sales?section=needs&year=<?= (int)$year ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="px-3 py-3 bg-light border-bottom">
            <form method="GET" action="/forecast-sales" class="row g-2 align-items-end">
                <input type="hidden" name="section" value="history">
                <input type="hidden" name="year" value="<?= (int)$year ?>">
                <div class="col-12 col-md-4">
                    <label for="historyStartDate" class="form-label small fw-semibold mb-1">Tanggal Mulai</label>
                    <input type="date" id="historyStartDate" name="history_start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($history_start_date ?? '') ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label for="historyProductId" class="form-label small fw-semibold mb-1">Produk</label>
                    <select id="historyProductId" name="history_product_id" class="form-select form-select-sm">
                        <option value="">Semua Produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int)$product['id'] ?>" <?= (int)$history_product_id === (int)$product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['code'] . ' — ' . $product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                    <a href="/forecast-sales?section=history&year=<?= (int)$year ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="salesHistoryTable">
            <thead class="table-light">
                <?php if ($activeSection === 'needs'): ?>
                    <tr>
                        <th>No. Dokumen</th>
                        <th>Periode</th>
                        <th>Pelanggan</th>
                        <th>Produk Jadi</th>
                        <th class="text-end">Kuantitas</th>
                        <th class="text-end">Total Nilai</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th>No. Transaksi</th>
                        <th>Tanggal Penjualan</th>
                        <th>Customer</th>
                        <th>Produk</th>
                        <th class="text-end">Jumlah Terjual</th>
                        <th class="text-end">Harga Satuan</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (empty($sectionSalesHistories)): ?>
                    <tr>
                        <td colspan="<?= $activeSection === 'needs' ? '8' : '6' ?>" class="text-center text-muted py-4">
                            <?= $activeSection === 'needs'
                                ? ($needs_start_date !== null || $needs_product_id !== null
                                    ? 'Tidak ada kebutuhan sales yang sesuai dengan filter.'
                                    : 'Belum ada kebutuhan sales aktif. Input kebutuhan baru melalui tombol di atas.')
                                : ($history_start_date !== null || $history_product_id !== null
                                    ? 'Tidak ada riwayat penjualan yang sesuai dengan filter.'
                                    : 'Belum ada penjualan aktual. Input atau impor transaksi penjualan untuk melihat riwayat.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sectionSalesHistories as $sh): ?>
                        <tr>
                            <?php if ($activeSection === 'needs'): ?>
                                <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($sh['doc_number']) ?></td>
                                <td class="font-monospace text-muted"><?= htmlspecialchars($sh['sale_month']) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($sh['customer_name']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($sh['product_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($sh['product_code']) ?></small>
                                </td>
                                <td class="text-end font-monospace fw-bold"><?= number_format((float)$sh['quantity'], 0, ',', '.') ?> <?= htmlspecialchars($sh['product_unit']) ?></td>
                                <td class="text-end font-monospace">Rp <?= number_format((float)$sh['total_amount'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($sh['status'] === 'confirmed_so'): ?>
                                        <span class="badge bg-primary-subtle text-primary border">Sales Order (SO)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border">Quotation</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($sh['status'] === 'quotation'): ?>
                                            <a href="/forecast-sales/status?id=<?= (int)$sh['id'] ?>&status=confirmed_so&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-primary" title="Konfirmasi menjadi SO">SO</a>
                                        <?php else: ?>
                                            <a href="/forecast-sales/status?id=<?= (int)$sh['id'] ?>&status=fulfilled&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-success" title="Tandai Selesai">Selesai</a>
                                        <?php endif; ?>
                                        <a href="/forecast-sales/delete-sales?id=<?= (int)$sh['id'] ?>&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-danger" onclick="return confirm('Hapus data penjualan <?= htmlspecialchars($sh['doc_number']) ?>?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            <?php else: ?>
                                <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($sh['doc_number']) ?></td>
                                <td class="font-monospace text-muted"><?= htmlspecialchars(date('d-m-Y', strtotime($sh['sale_date']))) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($sh['customer_name']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($sh['product_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($sh['product_code']) ?></small>
                                </td>
                                <td class="text-end font-monospace fw-bold"><?= number_format((float)$sh['quantity'], 0, ',', '.') ?> <?= htmlspecialchars($sh['product_unit']) ?></td>
                                <td class="text-end font-monospace">Rp <?= number_format((float)$sh['unit_price'], 0, ',', '.') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal Input Data Sales / Quotation / SO -->
<div class="modal fade" id="modalAddSales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/forecast-sales/store-sales" method="POST">
                <input type="hidden" name="redirect_year" value="<?= (int)$year ?>">
                <input type="hidden" name="redirect_section" value="<?= htmlspecialchars($activeSection) ?>">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i> Input Data Penjualan / Quotation / SO</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-warning small">
                            Belum ada master produk jadi. Silakan tambahkan produk di <a href="/master/products" class="fw-bold">Cek Finish Good (Produk)</a> terlebih dahulu, atau gunakan fitur <strong>Impor CSV Penjualan</strong> (otomatis membuat produk jika belum ada).
                        </div>
                    <?php endif; ?>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jenis Data</label>
                            <select name="doc_type" class="form-select form-select-sm">
                                <option value="history">Riwayat Penjualan Aktual</option>
                                <option value="sales_order">Sales Order (SO)</option>
                                <option value="quotation">Quotation (Penawaran)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Periode Bulan</label>
                            <input type="month" name="sale_month" class="form-control form-control-sm font-monospace" value="<?= date('Y-m') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Produk Jadi</label>
                        <select name="product_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Produk Jadi --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['code'] . ' — ' . $p['name'] . ' (' . $p['unit'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Pelanggan</label>
                        <input type="text" name="customer_name" class="form-control form-control-sm" required placeholder="Contoh: PT Pelanggan Industri / PDAM">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Kuantitas Terjual / Order</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control form-control-sm font-monospace" required placeholder="Contoh: 2500">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Satuan (Rp)</label>
                            <input type="number" step="1" min="0" name="unit_price" class="form-control form-control-sm font-monospace" value="0">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Catatan (Opsional)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Kontrak pengiriman bulanan">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Simpan Data Sales</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Input / Edit Forecast Sales 12 Bulan Manual -->
<div class="modal fade" id="modalManualForecast" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/forecast-sales/store-forecast" method="POST">
                <input type="hidden" name="redirect_section" value="forecast">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-calendar-alt text-primary me-2"></i> Input / Sesuaikan Forecast Sales 12 Bulan Manual</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Produk Jadi</label>
                            <select name="product_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Produk Jadi --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['code'] . ' — ' . $p['name'] . ' (' . $p['unit'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Tahun</label>
                            <input type="number" name="forecast_year" class="form-control form-control-sm font-monospace" value="<?= (int)$year ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Isi Cepat / Bulan</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="quickMonthlyFill" class="form-control font-monospace" placeholder="2000" min="0">
                                <button type="button" class="btn btn-outline-primary" onclick="fillMonthlyInputs()">Isi</button>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <?php foreach ($monthLabels as $idx => $ml): ?>
                            <div class="col-4 col-md-2">
                                <label class="form-label small text-muted mb-1"><?= $ml ?></label>
                                <input type="number" name="m<?= $idx + 1 ?>" class="form-control form-control-sm font-monospace fc-m-inp" min="0" step="1" value="0" oninput="calcTotal12m()" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-2 bg-light rounded border d-flex justify-content-between align-items-center mb-3">
                        <span class="small fw-semibold text-muted">Total Forecast 12 Bulan:</span>
                        <span class="fs-5 fw-bold font-monospace text-primary" id="total12mPreview">0</span>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Catatan</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Target tahunan dari divisi sales">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Simpan Forecast 12 Bulan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillMonthlyInputs() {
    const v = parseFloat(document.getElementById('quickMonthlyFill').value) || 0;
    document.querySelectorAll('.fc-m-inp').forEach(el => { el.value = v; });
    calcTotal12m();
}
function calcTotal12m() {
    let t = 0;
    document.querySelectorAll('.fc-m-inp').forEach(el => { t += parseFloat(el.value) || 0; });
    document.getElementById('total12mPreview').textContent = t.toLocaleString('id-ID');
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const historyChart = document.getElementById('monthlySalesHistoryChart');
    if (historyChart) {
        new Chart(historyChart, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Penjualan Aktual (Unit)',
                    data: <?= json_encode($monthly_actual_totals) ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
    ['salesForecastOverviewChart', 'salesForecastChart'].forEach((chartId) => {
        const ctx = document.getElementById(chartId);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [
                    {
                        label: 'Aktual',
                        data: <?= json_encode($monthly_actual_totals) ?>,
                        borderColor: '#64748b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 2,
                        fill: false
                    },
                    {
                        label: 'Forecast',
                        data: <?= json_encode($monthly_forecast_totals) ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'transparent',
                        borderDash: [5, 4],
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 2,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    });
});
</script>

@include('layouts.footer')
