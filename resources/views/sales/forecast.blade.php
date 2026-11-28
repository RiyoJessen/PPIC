@php
$activeSection = $_GET['section'] ?? 'needs';
if (!in_array($activeSection, ['needs', 'history'], true)) {
    $activeSection = 'needs';
}
$pageTitle = 'Pusat Kebutuhan & Forecast PPIC';
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$selectedForecastMonth = isset($period_start, $period_end)
    && $period_start === $period_end
    && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period_start)
    ? $period_start
    : '';
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
                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportForecast">
                    <i class="fas fa-file-excel me-1"></i> Upload Forecast Excel
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($activeSection === 'needs'): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <span class="fw-bold"><i class="fas fa-calendar-days text-primary me-2"></i>Periode Forecast</span>
            <span class="badge rounded-pill bg-primary-subtle text-primary"><?= (int)$year ?></span>
        </div>
        <nav class="d-flex flex-nowrap gap-2 overflow-auto pb-1" aria-label="Pilih bulan forecast">
            <?php if (empty($available_forecast_months)): ?>
                <span class="small text-muted">Belum ada data forecast untuk tahun <?= (int)$year ?>.</span>
            <?php endif; ?>
            <?php foreach ($available_forecast_months as $monthValue): ?>
                <?php
                    $monthIndex = (int) substr($monthValue, 5, 2) - 1;
                    $monthLabel = $monthLabels[$monthIndex] ?? '';
                    $monthQuery = [
                        'section' => 'needs',
                        'year' => (int)$year,
                        'period_start' => $monthValue,
                        'period_end' => $monthValue,
                        'source' => $source_filter,
                    ];
                    if ($search !== '') {
                        $monthQuery['search'] = $search;
                    }
                ?>
                <a href="/forecast-sales?<?= htmlspecialchars(http_build_query($monthQuery), ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-sm rounded-pill px-3 flex-shrink-0 <?= $selectedForecastMonth === $monthValue ? 'btn-primary' : 'btn-light text-muted' ?>">
                    <?= htmlspecialchars($monthLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="card-body border-bottom py-3">
        <div class="row g-3">
            <div class="col-6 col-md-6">
                <div class="small text-muted">Jumlah Data</div>
                <div class="fs-5 fw-semibold text-dark"><?= number_format((int)$combined_total, 0, ',', '.') ?></div>
            </div>
            <div class="col-6 col-md-6">
                <div class="small text-muted">Sumber Data</div>
                <div class="fw-semibold text-dark">
                    <?php if ($source_filter === 'forecast'): ?>
                        Forecast
                    <?php elseif ($source_filter === 'sales'): ?>
                        Kebutuhan Sales
                    <?php else: ?>
                        Forecast &amp; Kebutuhan Sales
                    <?php endif; ?>
                </div>
                <div class="small text-muted">
                    <?= $selectedForecastMonth !== ''
                        ? htmlspecialchars($monthNames[(int)substr($selectedForecastMonth, 5, 2) - 1] . ' ' . substr($selectedForecastMonth, 0, 4))
                        : 'Semua periode yang sesuai filter' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="px-3 py-3">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <span class="fw-semibold">Daftar Forecast<?= $selectedForecastMonth !== '' ? ' ' . htmlspecialchars($monthNames[(int)substr($selectedForecastMonth, 5, 2) - 1] . ' ' . substr($selectedForecastMonth, 0, 4)) : '' ?></span>
            <span class="small text-muted"><?= number_format((int)$combined_total, 0, ',', '.') ?> data</span>
        </div>
        <form method="GET" action="/forecast-sales" class="row g-2">
            <input type="hidden" name="section" value="needs">
            <input type="hidden" name="year" value="<?= (int)$year ?>">
            <?php if (!empty($period_start)): ?>
                <input type="hidden" name="period_start" value="<?= htmlspecialchars($period_start) ?>">
            <?php endif; ?>
            <?php if (!empty($period_end)): ?>
                <input type="hidden" name="period_end" value="<?= htmlspecialchars($period_end) ?>">
            <?php endif; ?>
            <div class="col-12 col-md-8">
                <label for="combinedSearch" class="visually-hidden">Cari nomor atau nama material</label>
                <input type="search" id="combinedSearch" name="search" class="form-control form-control-sm rounded-pill" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nomor atau nama material...">
            </div>
            <div class="col-7 col-md-2">
                <label for="combinedSource" class="visually-hidden">Sumber data</label>
                <select id="combinedSource" name="source" class="form-select form-select-sm">
                    <option value="all" <?= $source_filter === 'all' ? 'selected' : '' ?>>Semua sumber data</option>
                    <option value="forecast" <?= $source_filter === 'forecast' ? 'selected' : '' ?>>Forecast</option>
                    <option value="sales" <?= $source_filter === 'sales' ? 'selected' : '' ?>>Kebutuhan Sales</option>
                </select>
            </div>
            <div class="col-5 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Cari"><i class="fas fa-magnifying-glass"></i></button>
                <a href="/forecast-sales?section=needs&year=<?= (int)$year ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="combinedNeedsTable">
            <thead class="table-light">
                <tr>
                    <th class="text-center">No.</th>
                    <th>Nomor Material</th>
                    <th>Nama Material</th>
                    <th class="text-end">Forecast</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($combined_rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data yang sesuai dengan filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($combined_rows as $rowIndex => $row): ?>
                        <tr>
                            <td class="text-center text-muted"><?= (($combined_page - 1) * $combined_per_page) + $rowIndex + 1 ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($row['product_code']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['product_name']) ?></div>
                                <?php if ($row['source'] === 'sales'): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($row['customer_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-nowrap">
                                <?= number_format((float)$row['quantity'], 0, ',', '.') ?> <?= htmlspecialchars($row['product_unit']) ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <a href="/forecast-sales/detail?source=<?= urlencode($row['source']) ?>&id=<?= (int)$row['id'] ?>&period=<?= urlencode($row['period']) ?>" class="btn btn-outline-secondary btn-sm py-1">Detail</a>
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

<div class="mb-2">
    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-chart-column text-primary me-2"></i>Grafik Forecast &amp; Realisasi</h6>
    <div class="small text-muted">Ringkasan tren sesuai periode dan produk yang dipilih.</div>
</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Forecast vs Realisasi per Periode</div><div class="card-body"><div style="height:260px"><canvas id="forecastActualBarChart"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Tren Forecast &amp; Realisasi</div><div class="card-body"><div style="height:260px"><canvas id="forecastActualLineChart"></canvas></div></div></div></div>
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

<div class="modal fade" id="modalImportForecast" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/forecast-sales/import-xlsx" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-file-excel text-success me-2"></i>Upload Forecast Excel</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="forecastImportYear" class="form-label small fw-semibold">Tahun Forecast</label>
                        <input type="number" id="forecastImportYear" name="forecast_year" class="form-control form-control-sm" min="2000" max="2100" value="<?= (int)$year ?>" required>
                    </div>
                    <div class="mb-2">
                        <label for="forecastImportFile" class="form-label small fw-semibold">File Excel (.xlsx)</label>
                        <input type="file" id="forecastImportFile" name="forecast_file" class="form-control form-control-sm" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </div>
                    <div class="small text-muted">
                        Satu sheet untuk setiap bulan, dengan nama sheet berupa nama bulan (contoh: Mei). Baris pertama berisi kolom <strong>Nomor Material</strong>, <strong>Nama Material</strong>, dan <strong>Forecast</strong>. Nomor material harus sudah terdaftar di master Produk Jadi. Nilai bulan yang tidak diunggah tetap dipertahankan.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-upload me-1"></i>Impor Forecast</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    const chartLabels = <?= json_encode($comparison_chart_labels) ?>;
    const forecastValues = <?= json_encode($comparison_chart_forecast) ?>;
    const realizationValues = <?= json_encode($comparison_chart_realization) ?>;
    const comparisonChart = document.getElementById('forecastActualBarChart');
    if (comparisonChart) {
        new Chart(comparisonChart, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'Forecast', data: forecastValues, backgroundColor: 'rgba(22, 163, 74, 0.75)', borderColor: '#16a34a', borderWidth: 1, borderRadius: 4 },
                    { label: 'Realisasi', data: realizationValues, backgroundColor: 'rgba(37, 99, 235, 0.75)', borderColor: '#2563eb', borderWidth: 1, borderRadius: 4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
        });
    }
    const trendChart = document.getElementById('forecastActualLineChart');
    if (trendChart) {
        new Chart(trendChart, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'Forecast', data: forecastValues, borderColor: '#16a34a', backgroundColor: 'rgba(22, 163, 74, 0.1)', borderWidth: 2, tension: 0.25, pointRadius: 3 },
                    { label: 'Realisasi', data: realizationValues, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.1)', borderWidth: 2, tension: 0.25, pointRadius: 3 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
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
