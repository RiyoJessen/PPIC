@php
$activeSection = $_GET['section'] ?? 'needs';
if (!in_array($activeSection, ['needs', 'history'], true)) {
    $activeSection = 'needs';
}
$pageTitle = 'Pusat Kebutuhan & Forecast PPIC';
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
                <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-chart-line text-primary me-2"></i>Pusat Kebutuhan &amp; Forecast PPIC</h5>
                <p class="text-muted small mb-0">Pantau permintaan pelanggan, hasil forecast, dan evaluasi realisasi dalam satu halaman.</p>
            </div>
        </div>
        <nav class="row g-2 border-top pt-3" aria-label="Navigasi Sales & Forecast">
            <div class="col-12 col-md-6">
                <a href="/forecast-sales?section=needs&year=<?= (int)$year ?>" class="btn w-100 py-2 <?= $activeSection === 'needs' ? 'btn-primary' : 'btn-light' ?>">
                    <i class="fas fa-layer-group me-2"></i>Kebutuhan &amp; Forecast
                </a>
            </div>
            <div class="col-12 col-md-6">
                <a href="/forecast-sales?section=history&year=<?= (int)$year ?>" class="btn w-100 py-2 <?= $activeSection === 'history' ? 'btn-primary' : 'btn-light' ?>">
                    <i class="fas fa-clock-rotate-left me-2"></i>Riwayat Penjualan
                </a>
            </div>
        </nav>
    </div>
</div>

<?php if ($activeSection === 'needs'): ?>
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
                <h6 class="fw-bold mb-1">Data Kebutuhan &amp; Forecast</h6>
                <p class="text-muted small mb-0">Forecast ditampilkan per bulan sebagai prediksi, bukan dokumen pesanan pelanggan.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAddSales">
                    <i class="fas fa-plus-circle me-1"></i> Input Kebutuhan Sales
                </button>
                <form action="/forecast-sales/generate" method="POST" class="d-inline">
                    <input type="hidden" name="forecast_year" value="<?= (int)$year ?>">
                    <input type="hidden" name="redirect_section" value="needs">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Generate Forecast <?= (int)$year ?>
                    </button>
                </form>
                <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalManualForecast">
                    <i class="fas fa-calendar-edit me-1"></i> Input Forecast
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($activeSection === 'needs'): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <span class="fw-bold"><i class="fas fa-table-list text-primary me-2"></i>Daftar Kebutuhan Sales &amp; Forecast</span>
                <div class="small text-muted">Quotation/SO adalah permintaan pelanggan; Forecast adalah prediksi model.</div>
            </div>
            <span class="badge bg-light text-dark border"><?= number_format((int)$combined_total, 0, ',', '.') ?> data</span>
        </div>
    </div>
    <div class="px-3 py-3 bg-light border-bottom">
        <form method="GET" action="/forecast-sales" class="row g-2 align-items-end">
            <input type="hidden" name="section" value="needs">
            <div class="col-6 col-md-2">
                <label for="combinedSource" class="form-label small fw-semibold mb-1">Sumber</label>
                <select id="combinedSource" name="source" class="form-select form-select-sm">
                    <option value="all" <?= $source_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                    <option value="sales" <?= $source_filter === 'sales' ? 'selected' : '' ?>>Kebutuhan Sales</option>
                    <option value="forecast" <?= $source_filter === 'forecast' ? 'selected' : '' ?>>Forecast</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="periodStart" class="form-label small fw-semibold mb-1">Periode Mulai</label>
                <input type="month" id="periodStart" name="period_start" class="form-control form-control-sm" value="<?= htmlspecialchars($period_start ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="periodEnd" class="form-label small fw-semibold mb-1">Periode Akhir</label>
                <input type="month" id="periodEnd" name="period_end" class="form-control form-control-sm" value="<?= htmlspecialchars($period_end ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="combinedProduct" class="form-label small fw-semibold mb-1">Produk</label>
                <select id="combinedProduct" name="product_id" class="form-select form-select-sm">
                    <option value="">Semua Produk</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id'] ?>" <?= (int)$selected_product_id === (int)$product['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($product['code'] . ' — ' . $product['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-md-2">
                <label for="combinedSearch" class="form-label small fw-semibold mb-1">Search</label>
                <input type="search" id="combinedSearch" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Produk, pelanggan, ID...">
            </div>
            <div class="col-4 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="/forecast-sales?section=needs" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="combinedNeedsTable">
            <thead class="table-light">
                <tr>
                    <th>ID Kebutuhan</th>
                    <th>Nama Produk</th>
                    <th class="text-end">Jumlah Kebutuhan</th>
                    <th>Periode/Tanggal</th>
                    <th>Sumber Kebutuhan</th>
                    <th>Status</th>
                    <th class="text-center">Aksi/Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($combined_rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data yang sesuai dengan filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($combined_rows as $row): ?>
                        <tr>
                            <td class="font-monospace fw-semibold <?= $row['source'] === 'forecast' ? 'text-success' : 'text-primary' ?>"><?= htmlspecialchars($row['display_id']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($row['product_code']) ?></small>
                                <?php if ($row['source'] === 'sales'): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($row['customer_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-nowrap">
                                <?= number_format((float)$row['quantity'], 0, ',', '.') ?> <?= htmlspecialchars($row['product_unit']) ?>
                            </td>
                            <td class="font-monospace text-nowrap">
                                <?php if ($row['source'] === 'sales'): ?>
                                    <?= htmlspecialchars(date('d-m-Y', strtotime($row['date']))) ?>
                                    <div class="small text-muted"><?= htmlspecialchars($row['period']) ?></div>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['period']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['source'] === 'forecast'): ?>
                                    <span class="badge bg-success-subtle text-success border"><i class="fas fa-chart-line me-1"></i>Forecast</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary border"><i class="fas fa-file-signature me-1"></i>Kebutuhan Sales</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['source'] === 'forecast'): ?>
                                    <span class="badge bg-success-subtle text-success border">Prediksi</span>
                                <?php elseif ($row['status'] === 'quotation'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border">Quotation</span>
                                <?php elseif ($row['status'] === 'confirmed_so'): ?>
                                    <span class="badge bg-primary-subtle text-primary border">Sales Order (SO)</span>
                                <?php elseif ($row['doc_type'] === 'sales_order'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">SO Selesai</span>
                                <?php elseif ($row['doc_type'] === 'quotation'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">Quotation Selesai</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <details class="text-start">
                                        <summary class="btn btn-outline-secondary btn-sm py-1">Detail</summary>
                                        <div class="position-absolute bg-white border rounded shadow p-2 text-start small" style="z-index: 5; min-width: 240px; max-width: 320px;">
                                            <?php if ($row['source'] === 'forecast'): ?>
                                                <div><strong>Model:</strong> <?= htmlspecialchars($row['method']) ?></div>
                                                <div><strong>Periode model:</strong> <?= htmlspecialchars($row['period']) ?></div>
                                                <div><strong>Catatan:</strong> <?= htmlspecialchars($row['notes'] ?: 'Tidak ada catatan.') ?></div>
                                            <?php else: ?>
                                                <div><strong>Dokumen:</strong> <?= htmlspecialchars($row['display_id']) ?></div>
                                                <div><strong>Jenis:</strong> <?= $row['doc_type'] === 'sales_order' ? 'Sales Order (SO)' : 'Quotation' ?></div>
                                                <div><strong>Pelanggan:</strong> <?= htmlspecialchars($row['customer_name']) ?></div>
                                                <div><strong>Total nilai:</strong> Rp <?= number_format((float)$row['total_amount'], 0, ',', '.') ?></div>
                                                <div><strong>Catatan:</strong> <?= htmlspecialchars($row['notes'] ?: 'Tidak ada catatan.') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                    <?php if ($row['source'] === 'sales'): ?>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($row['status'] === 'quotation'): ?>
                                                <a href="/forecast-sales/status?id=<?= (int)$row['id'] ?>&status=confirmed_so&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-primary" title="Konfirmasi menjadi SO">SO</a>
                                            <?php elseif ($row['status'] === 'confirmed_so'): ?>
                                                <a href="/forecast-sales/status?id=<?= (int)$row['id'] ?>&status=fulfilled&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-success" title="Tandai selesai">Selesai</a>
                                            <?php endif; ?>
                                            <a href="/forecast-sales/delete-sales?id=<?= (int)$row['id'] ?>&year=<?= (int)$year ?>&section=needs" class="btn btn-outline-danger" title="Hapus" onclick="return confirm('Hapus data <?= htmlspecialchars($row['display_id']) ?>?')"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    <?php else: ?>
                                        <a href="/forecast-sales/delete-forecast?id=<?= (int)$row['id'] ?>&year=<?= (int)substr($row['period'], 0, 4) ?>&section=needs" class="btn btn-outline-danger btn-sm" title="Hapus forecast" onclick="return confirm('Hapus seluruh forecast tahunan produk ini?')"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($combined_last_page > 1): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Halaman <?= (int)$combined_page ?> dari <?= (int)$combined_last_page ?></small>
            <nav aria-label="Pagination kebutuhan dan forecast">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($pageNumber = 1; $pageNumber <= $combined_last_page; $pageNumber++): ?>
                        <?php $pageQuery = $_GET; $pageQuery['page'] = $pageNumber; ?>
                        <li class="page-item <?= $pageNumber === (int)$combined_page ? 'active' : '' ?>">
                            <a class="page-link" href="/forecast-sales?<?= htmlspecialchars(http_build_query($pageQuery), ENT_QUOTES, 'UTF-8') ?>"><?= $pageNumber ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-2">
    <div>
        <h6 class="fw-bold text-dark mb-1"><i class="fas fa-scale-balanced text-primary me-2"></i>Evaluasi Forecast vs Realisasi</h6>
        <div class="small text-muted">Realisasi dihitung dari Sales Order dan penjualan yang selesai; Quotation tidak dihitung sebagai realisasi.</div>
    </div>
    <span class="small text-muted">Selisih = Forecast − Realisasi; error = |selisih| ÷ nilai terbesar</span>
</div>
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body p-3"><div class="small text-muted">Total Forecast</div><div class="fs-5 fw-bold text-success"><?= number_format((float)$evaluation_summary['forecast_total'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body p-3"><div class="small text-muted">Total Realisasi</div><div class="fs-5 fw-bold text-primary"><?= number_format((float)$evaluation_summary['realization_total'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body p-3"><div class="small text-muted">Total Selisih</div><div class="fs-5 fw-bold <?= $evaluation_summary['difference_total'] > 0 ? 'text-danger' : ($evaluation_summary['difference_total'] < 0 ? 'text-warning-emphasis' : 'text-success') ?>"><?= number_format((float)$evaluation_summary['difference_total'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body p-3"><div class="small text-muted">Rata-rata Error / Accuracy</div><div class="fs-5 fw-bold text-dark"><?= number_format((float)$evaluation_summary['average_error'], 2, ',', '.') ?>% <small class="fs-6 text-muted">error</small></div><small class="text-muted">Akurasi <?= number_format((float)$evaluation_summary['accuracy'], 2, ',', '.') ?>%</small></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Forecast vs Realisasi per Periode (Jumlah)</div><div class="card-body"><div style="height:260px"><canvas id="forecastActualBarChart"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Tren Forecast &amp; Realisasi</div><div class="card-body"><div style="height:260px"><canvas id="forecastActualLineChart"></canvas></div></div></div></div>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 fw-bold"><i class="fas fa-magnifying-glass-chart text-primary me-2"></i>Rincian Evaluasi &amp; AI Insight</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Nama Produk</th><th>Periode Forecast</th><th class="text-end">Jumlah Forecast</th><th class="text-end">Jumlah Realisasi</th><th class="text-end">Selisih</th><th class="text-end">Persentase Error</th><th>Keterangan Evaluasi</th><th>Aksi/Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($evaluation_rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data forecast yang sesuai dengan filter untuk dievaluasi.</td></tr>
                <?php else: ?>
                    <?php foreach ($evaluation_rows as $row): ?>
                        <?php $evaluationClass = $row['evaluation'] === 'Overforecast' ? 'danger' : ($row['evaluation'] === 'Underforecast' ? 'warning text-dark' : 'success'); ?>
                        <tr>
                            <td><div class="fw-semibold"><?= htmlspecialchars($row['product_name']) ?></div><small class="text-muted font-monospace"><?= htmlspecialchars($row['product_code']) ?></small></td>
                            <td class="font-monospace"><?= htmlspecialchars($row['period']) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)$row['forecast_quantity'], 0, ',', '.') ?> <?= htmlspecialchars($row['product_unit']) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)$row['realization_quantity'], 0, ',', '.') ?> <?= htmlspecialchars($row['product_unit']) ?></td>
                            <td class="text-end font-monospace fw-bold <?= $row['difference'] > 0 ? 'text-danger' : ($row['difference'] < 0 ? 'text-warning-emphasis' : 'text-success') ?>"><?= number_format((float)$row['difference'], 0, ',', '.') ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)$row['error_percentage'], 2, ',', '.') ?>%</td>
                            <td><span class="badge bg-<?= $evaluationClass ?>"><?= htmlspecialchars($row['evaluation']) ?></span></td>
                            <td><details><summary class="btn btn-outline-primary btn-sm">AI Insight</summary><div class="position-absolute bg-white border rounded shadow p-2 small" style="z-index:5; min-width:240px; max-width:320px;"><strong>Insight Evaluasi Otomatis</strong><br><?= htmlspecialchars($row['insight']) ?></div></details></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Panel Riwayat dan Impor CSV Data Penjualan Aktual -->
<?php if ($activeSection === 'history'): ?>
<div class="row g-4 mb-4">
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
</div>
<?php endif; ?>

<!-- Tabel Riwayat Data Sales, Quotation & SO -->
<?php if ($activeSection === 'history'): ?>
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
                <input type="hidden" name="redirect_section" value="needs">
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
    const evaluationChartLabels = <?= json_encode($evaluation_chart_labels) ?>;
    const evaluationForecast = <?= json_encode($evaluation_chart_forecast) ?>;
    const evaluationRealization = <?= json_encode($evaluation_chart_realization) ?>;
    const comparisonChart = document.getElementById('forecastActualBarChart');
    if (comparisonChart) {
        new Chart(comparisonChart, {
            type: 'bar',
            data: {
                labels: evaluationChartLabels,
                datasets: [
                    {
                        label: 'Forecast',
                        data: evaluationForecast,
                        backgroundColor: 'rgba(22, 163, 74, 0.75)',
                        borderColor: '#16a34a',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Realisasi',
                        data: evaluationRealization,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 4
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
    }
    const trendChart = document.getElementById('forecastActualLineChart');
    if (trendChart) {
        new Chart(trendChart, {
            type: 'line',
            data: {
                labels: evaluationChartLabels,
                datasets: [
                    {
                        label: 'Forecast',
                        data: evaluationForecast,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 3
                    },
                    {
                        label: 'Realisasi',
                        data: evaluationRealization,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 3
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
    }
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
});
</script>

@include('layouts.footer')
