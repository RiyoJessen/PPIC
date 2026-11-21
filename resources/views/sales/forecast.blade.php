@php
$pageTitle = 'Data Sales & Forecast Sales 12 Bulan';
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
@endphp
@include('layouts.header')

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Input Data Sales &amp; Forecast Sales 12 Bulan</h3>
        <p class="text-muted small mb-0">
            Masukkan riwayat penjualan aktual (manual atau impor CSV) &amp; Quotation/SO &rarr; hitung <strong>Forecast Sales 12 Bulan</strong> otomatis &rarr; cek kesiapan Formula BOM &amp; Raw Material.
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="GET" action="/forecast-sales" class="d-flex align-items-center gap-1">
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === (int)$year ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddSales">
            <i class="fas fa-plus-circle me-1"></i> Input Data Sales / SO
        </button>
        <form action="/forecast-sales/generate" method="POST" class="d-inline">
            <input type="hidden" name="forecast_year" value="<?= (int)$year ?>">
            <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm">
                <i class="fas fa-wand-magic-sparkles me-1"></i> Generate Forecast 12 Bln Otomatis
            </button>
        </form>
        <button class="btn btn-outline-dark btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalManualForecast">
            <i class="fas fa-calendar-edit me-1"></i> Input Manual 12 Bln
        </button>
    </div>
</div>

<!-- 4 KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Data Riwayat Penjualan</div>
                <div class="fs-4 fw-bold font-monospace text-primary mt-1"><?= (int)$total_sales_records ?> <small class="fs-6 fw-normal text-muted">Transaksi</small></div>
                <small class="text-muted">Total volume: <?= number_format((float)$total_sales_qty, 0, ',', '.') ?> Unit</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Total Forecast 12 Bln (<?= (int)$year ?>)</div>
                <div class="fs-4 fw-bold font-monospace text-success mt-1"><?= number_format((float)$total_12m_forecast, 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">Unit</small></div>
                <small class="text-muted"><?= count($forecasts) ?> produk ter-forecast</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-start border-4 border-danger h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Perlu Tindak Lanjut Formula / RM</div>
                <div class="fs-4 fw-bold font-monospace text-danger mt-1"><?= (int)$shortage_products_count ?> <small class="fs-6 fw-normal text-muted">Produk</small></div>
                <small class="text-muted">Formula belum ada atau RM kurang</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-start border-4 border-info h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Quotation &amp; SO Aktif</div>
                <div class="fs-4 fw-bold font-monospace text-info mt-1"><?= (int)$open_quotation_so ?> <small class="fs-6 fw-normal text-muted">Dokumen</small></div>
                <small class="text-muted">Tersinkron ke kebutuhan sales</small>
            </div>
        </div>
    </div>
</div>

<!-- Panel Impor CSV Data Penjualan Aktual + Grafik Forecast 12 Bulan -->
<div class="row g-4 mb-4">
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
</div>

<!-- Matriks Hasil Forecast Sales 12 Bulan -->
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
                                <a href="/forecast-sales/delete-forecast?id=<?= (int)$f['id'] ?>&year=<?= (int)$year ?>" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Hapus forecast 12 bulan untuk <?= htmlspecialchars($f['product_name']) ?>?')">
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

<!-- Cek Formula & Cek Raw Material Otomatis dari Hasil Forecast Sales -->
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

<!-- Tabel Riwayat Data Sales, Quotation & SO -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-history text-info me-2"></i> Daftar Data Riwayat Penjualan, Quotation &amp; Sales Order (SO)</span>
        <input type="text" class="form-control form-control-sm table-search" data-target="#salesHistoryTable" placeholder="Cari pelanggan, produk..." style="max-width: 240px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="salesHistoryTable">
            <thead class="table-light">
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
            </thead>
            <tbody>
                <?php if (empty($sales_histories)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Belum ada data penjualan. Klik <strong>Input Data Sales / SO</strong> atau unggah CSV di panel kiri atas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales_histories as $sh): ?>
                        <tr>
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
                                <?php if ($sh['status'] === 'fulfilled'): ?>
                                    <span class="badge bg-success-subtle text-success border">Riwayat Aktual</span>
                                <?php elseif ($sh['status'] === 'confirmed_so'): ?>
                                    <span class="badge bg-primary-subtle text-primary border">Sales Order (SO)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border">Quotation</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($sh['status'] === 'quotation'): ?>
                                        <a href="/forecast-sales/status?id=<?= (int)$sh['id'] ?>&status=confirmed_so&year=<?= (int)$year ?>" class="btn btn-outline-primary" title="Konfirmasi menjadi SO">SO</a>
                                    <?php elseif ($sh['status'] === 'confirmed_so'): ?>
                                        <a href="/forecast-sales/status?id=<?= (int)$sh['id'] ?>&status=fulfilled&year=<?= (int)$year ?>" class="btn btn-outline-success" title="Tandai Selesai (Riwayat)">Selesai</a>
                                    <?php endif; ?>
                                    <a href="/forecast-sales/delete-sales?id=<?= (int)$sh['id'] ?>&year=<?= (int)$year ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus data penjualan <?= htmlspecialchars($sh['doc_number']) ?>?')">
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

<!-- Modal Input Data Sales / Quotation / SO -->
<div class="modal fade" id="modalAddSales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/forecast-sales/store-sales" method="POST">
                <input type="hidden" name="redirect_year" value="<?= (int)$year ?>">
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
    const ctx = document.getElementById('salesForecastChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [
                {
                    label: 'Forecast Sales 12 Bln (<?= (int)$year ?>)',
                    data: <?= json_encode($monthly_forecast_totals) ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderRadius: 6
                },
                {
                    label: 'Riwayat Penjualan Aktual',
                    data: <?= json_encode($monthly_actual_totals) ?>,
                    type: 'line',
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    borderWidth: 2.5,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

@include('layouts.footer')
