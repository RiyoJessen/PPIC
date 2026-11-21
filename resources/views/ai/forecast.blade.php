@php
$pageTitle = 'AI Forecasting & Analisis Google Gemini';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">AI Forecasting &amp; Gemini Procurement Advisor</h3>
        <p class="text-muted small mb-0">
            Kombinasi kalkulasi statistik deterministik (Reorder Point &amp; Safety Stock) dengan narasi analitis cerdas berbasis Google Gemini.
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Filter Horizon Riwayat -->
        <div class="btn-group btn-group-sm shadow-xs" role="group">
            <span class="btn btn-light disabled text-secondary border small fw-semibold">
                <i class="fas fa-history me-1"></i> Rentang Riwayat:
            </span>
            <a href="?material_id=<?= $active_analysis['material']['id'] ?? 1 ?>&lookback=30" class="btn btn-outline-secondary btn-sm <?= ($lookback_days == 30) ? 'active bg-dark text-white' : '' ?>">1 Bulan (30 hr)</a>
            <a href="?material_id=<?= $active_analysis['material']['id'] ?? 1 ?>&lookback=90" class="btn btn-outline-secondary btn-sm <?= ($lookback_days == 90) ? 'active bg-dark text-white' : '' ?>">3 Bulan (90 hr)</a>
            <a href="?material_id=<?= $active_analysis['material']['id'] ?? 1 ?>&lookback=180" class="btn btn-outline-secondary btn-sm <?= ($lookback_days == 180) ? 'active bg-dark text-white' : '' ?>">6 Bulan (180 hr)</a>
            <a href="?material_id=<?= $active_analysis['material']['id'] ?? 1 ?>&lookback=365" class="btn btn-outline-secondary btn-sm <?= ($lookback_days == 365) ? 'active bg-dark text-white' : '' ?>">1 Tahun (365 hr)</a>
        </div>

        <span class="badge <?= $has_api_key ? 'bg-success-subtle text-success border' : 'bg-warning-subtle text-warning-emphasis border' ?> px-3 py-2">
            <i class="fas fa-microchip me-1"></i> Mode AI: <?= $has_api_key ? 'Google Gemini Live (' . htmlspecialchars($model_name) . ')' : 'Heuristik AI Simulasi Cerdas' ?>
        </span>
        <a href="/settings" class="btn btn-outline-secondary btn-sm" title="Atur API Key Gemini">
            <i class="fas fa-key"></i>
        </a>
    </div>
</div>

<!-- Active AI Recommendation Showcase -->
<?php if ($active_analysis): ?>
    <?php 
    $mat = $active_analysis['material']; 
    ?>
    <div class="card ai-recommendation-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 pb-3 mb-3 border-bottom border-secondary-subtle">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge ai-badge px-3 py-2 rounded-pill fs-6">
                        <i class="fas fa-sparkles me-1"></i> Rekomendasi Cerdas Gemini AI
                    </span>
                    <span class="badge badge-<?= strtolower($mat['stock_status']) ?> px-3 py-2 rounded-pill fs-6">
                        Status: <?= $mat['stock_status'] ?>
                    </span>
                </div>
                <div class="small text-muted font-monospace">
                    <i class="fas fa-satellite-dish me-1"></i> <?= htmlspecialchars($active_analysis['source'] ?? 'Engine PPIC') ?>
                </div>
            </div>

            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h4 class="fw-bold text-dark mb-2">
                        <?= htmlspecialchars($mat['name']) ?> <span class="text-muted fs-6 fw-normal font-monospace">(<?= htmlspecialchars($mat['code']) ?>)</span>
                    </h4>
                    
                    <div class="p-3 bg-white rounded border shadow-sm mb-3">
                        <h6 class="fw-bold text-dark small text-uppercase mb-2"><i class="fas fa-comment-dots text-primary me-2"></i> Narasi Evaluasi Risiko &amp; Rekomendasi AI:</h6>
                        <div class="text-secondary lh-base font-sans" style="font-size: 0.95rem;">
                            <?= preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', nl2br(htmlspecialchars($active_analysis['narrative']))) ?>
                        </div>
                    </div>

                    <!-- Riwayat Konsumsi Riil Bulan-Bulan Sebelumnya -->
                    <?php if (!empty($active_analysis['historical_stats']['has_history'])): ?>
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <span class="fw-bold small text-dark">
                                    <i class="fas fa-chart-line text-success me-1"></i> Data Historis Konsumsi Bulan-Bulan Sebelumnya:
                                </span>
                                <span class="badge bg-success-subtle text-success border font-monospace">
                                    Laju: <?= number_format((float)$active_analysis['daily_usage'], 1, ',', '.') ?> <?= $mat['unit'] ?>/hari (<?= $active_analysis['usage_source'] ?? 'Historis Riil' ?>)
                                </span>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($active_analysis['historical_stats']['periods'] as $hp): ?>
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded border text-center shadow-xs">
                                            <div class="text-muted font-monospace fw-semibold" style="font-size: 11px;">Bulan <?= $hp['period_month'] ?></div>
                                            <div class="fw-bold text-dark font-monospace fs-6"><?= number_format((float)$hp['total_qty'], 0, ',', '.') ?> <small class="fw-normal"><?= $mat['unit'] ?></small></div>
                                            <div class="text-muted" style="font-size: 10px;"><?= $hp['transaction_count'] ?> transaksi keluar</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-2 text-muted" style="font-size: 11px;">
                                <i class="fas fa-check-double text-success me-1"></i> <strong>Proyeksi Tanggal Habis Nyata:</strong> Dihitung langsung dari rata-rata konsumsi <?= $active_analysis['historical_stats']['lookback_days'] ?> hari terakhir, sehingga estimasi tanggal habis persediaan berjalan akurat dan relevan dengan ritme pabrik.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 text-muted small">
                        <div class="col-sm-4">
                            <i class="fas fa-industry text-primary me-1"></i> Supplier: <strong><?= htmlspecialchars($mat['supplier_name'] ?? '-') ?></strong>
                        </div>
                        <div class="col-sm-4">
                            <i class="fas fa-shipping-fast text-secondary me-1"></i> Lead Time: <strong><?= $mat['supplier_lead_time'] ?> Hari</strong>
                        </div>
                        <div class="col-sm-4">
                            <i class="fas fa-boxes text-secondary me-1"></i> MOQ: <strong><?= number_format((float)$mat['moq'], 0) ?> <?= $mat['unit'] ?></strong>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="bg-white p-4 rounded-3 border shadow-sm text-center">
                        <span class="text-muted small fw-semibold text-uppercase">Indikasi Kebutuhan Beli (Q)</span>
                        <h1 class="fw-bold text-primary my-2 font-monospace">
                            <?= number_format((float)$active_analysis['final_recommended_qty'], 0, ',', '.') ?>
                            <span class="fs-5 fw-normal text-muted"><?= $mat['unit'] ?></span>
                        </h1>
                        <p class="small text-muted mb-3">
                            Estimasi Nilai Pengadaan:<br>
                            <strong class="text-dark fs-6 font-monospace">Rp <?= number_format((float)$active_analysis['estimated_cost'], 0, ',', '.') ?></strong>
                        </p>

                        <?php if ($active_analysis['final_recommended_qty'] > 0): ?>
                            <a href="/purchasing/pr/create-from-ai?material_id=<?= $mat['id'] ?>&qty=<?= $active_analysis['final_recommended_qty'] ?>" class="btn btn-primary w-100 shadow-sm py-2">
                                <i class="fas fa-cart-plus me-1"></i> Ajukan Purchase Requisition (PR)
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100 py-2" disabled>
                                <i class="fas fa-check-circle text-success me-1"></i> Stok Masih Mencukupi
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ====================================================================== -->
<!-- SECTION BARU: PERAMALAN HARGA PASAR BAHAN BAKU BULAN DEPAN             -->
<!-- ====================================================================== -->
<?php if (!empty($active_price_forecast) && !empty($price_summary)): ?>
    <?php
    $pf = $active_price_forecast;
    $pfMat = $pf['material'];
    $ps = $price_summary;
    $pfSign = $pf['price_change_pct'] > 0 ? '+' : '';
    $pfTrendIcon = match($pf['trend_direction']) {
        'naik' => 'fa-arrow-trend-up text-danger',
        'turun' => 'fa-arrow-trend-down text-success',
        default => 'fa-arrows-left-right text-info',
    };
    $pfLineColor = match($pf['trend_direction']) {
        'naik' => '#dc2626',
        'turun' => '#16a34a',
        default => '#0284c7',
    };
    ?>
    <div id="price-forecast-section" class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fas fa-chart-area text-primary me-2"></i> Peramalan Harga Pasar Bahan Baku &mdash; <?= htmlspecialchars($ps['next_month_label']) ?>
                </h4>
                <p class="text-muted small mb-0">
                    Proyeksi harga pasar bulan depan menggunakan model statistik <em>Hybrid OLS Linear Trend + Weighted Momentum</em> dan analisis AI industri kimia.
                </p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRecordPrice" onclick="openPriceModal(<?= (int)$pfMat['id'] ?>, <?= (float)$pf['current_price'] ?>)">
                    <i class="fas fa-plus-circle me-1"></i> Catat Harga Pasar Aktual
                </button>
            </div>
        </div>

        <!-- 4 KPI Ringkasan Proyeksi Harga Bulan Depan -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Rata-Rata Tren Pasar</div>
                                <div class="fs-4 fw-bold font-monospace mt-1 <?= $ps['avg_change_pct'] > 0 ? 'text-danger' : ($ps['avg_change_pct'] < 0 ? 'text-success' : 'text-info') ?>">
                                    <?= ($ps['avg_change_pct'] > 0 ? '+' : '') . number_format((float)$ps['avg_change_pct'], 2, ',', '.') ?>%
                                </div>
                                <small class="text-muted">Proyeksi MoM <?= htmlspecialchars($ps['next_month_label']) ?></small>
                            </div>
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-3">
                                <i class="fas fa-percent fs-5"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Arah Harga Bahan</div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <span class="badge bg-danger-subtle text-danger border font-monospace"><?= (int)$ps['rising_count'] ?> Naik</span>
                                    <span class="badge bg-info-subtle text-info border font-monospace"><?= (int)$ps['stable_count'] ?> Stabil</span>
                                    <span class="badge bg-success-subtle text-success border font-monospace"><?= (int)$ps['falling_count'] ?> Turun</span>
                                </div>
                                <small class="text-muted d-block mt-1">Dari <?= (int)$ps['total_materials'] ?> bahan baku aktif</small>
                            </div>
                            <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-3">
                                <i class="fas fa-scale-balanced fs-5"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Estimasi Anggaran Q Bulan Depan</div>
                                <div class="fs-5 fw-bold font-monospace text-dark mt-1">
                                    Rp <?= number_format((float)$ps['total_next_month_budget'], 0, ',', '.') ?>
                                </div>
                                <small class="text-muted">
                                    Bulan ini: <span class="font-monospace">Rp <?= number_format((float)$ps['total_current_budget'], 0, ',', '.') ?></span>
                                </small>
                            </div>
                            <span class="badge bg-info-subtle text-info p-2 rounded-3">
                                <i class="fas fa-coins fs-5"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm stat-card h-100 border-start border-4 border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Potensi Hemat Lock Harga</div>
                                <div class="fs-5 fw-bold font-monospace text-success mt-1">
                                    Rp <?= number_format((float)$ps['potential_early_savings'], 0, ',', '.') ?>
                                </div>
                                <small class="text-muted">Jika PO bahan naik dikunci bulan ini</small>
                            </div>
                            <span class="badge bg-success-subtle text-success p-2 rounded-3">
                                <i class="fas fa-piggy-bank fs-5"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Detail Grafik & Analisis Harga Bahan Terpilih -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold text-dark">
                        <i class="fas <?= $pfTrendIcon ?> me-1"></i>
                        Tren &amp; Ramalan Harga: <?= htmlspecialchars($pfMat['name']) ?>
                    </span>
                    <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($pfMat['code']) ?></span>
                    <span class="badge bg-<?= $pf['signal_badge'] ?>-subtle text-<?= $pf['signal_badge'] === 'warning' ? 'warning-emphasis' : $pf['signal_badge'] ?> border">
                        <?= htmlspecialchars($pf['signal_label']) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label for="priceMaterialSelect" class="small text-muted mb-0 text-nowrap">Pilih Bahan:</label>
                    <select id="priceMaterialSelect" class="form-select form-select-sm" style="min-width: 220px;" onchange="if(this.value) window.location.href='/ai/forecast?material_id=' + this.value + '&lookback=<?= (int)$lookback_days ?>#price-forecast-section';">
                        <?php foreach ($materials as $optMat): ?>
                            <option value="<?= (int)$optMat['id'] ?>" <?= ((int)$optMat['id'] === (int)$pfMat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($optMat['code'] . ' — ' . $optMat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-stretch">
                    <!-- Kiri: Grafik Chart.js -->
                    <div class="col-lg-7 d-flex flex-column">
                        <div class="p-3 bg-light rounded border flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark">
                                    <i class="fas fa-chart-line text-primary me-1"></i> Grafik Historis 6 Bulan &amp; Proyeksi <?= htmlspecialchars($pf['next_month_label']) ?>
                                </span>
                                <span class="badge bg-white text-secondary border font-monospace" style="font-size: 11px;">
                                    Keyakinan Model: <?= (int)$pf['confidence_pct'] ?>% &bull; Volatilitas: <?= number_format((float)$pf['volatility_pct'], 1, ',', '.') ?>%
                                </span>
                            </div>
                            <div style="position: relative; height: 270px;">
                                <canvas id="marketPriceForecastChart"></canvas>
                            </div>
                        </div>

                        <!-- Faktor Pemicu Pasar -->
                        <?php if (!empty($pf['market_drivers'])): ?>
                            <div class="mt-3">
                                <div class="small fw-semibold text-muted mb-1"><i class="fas fa-bolt text-warning me-1"></i> Faktor Pemicu Harga Pasar Teridentifikasi:</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($pf['market_drivers'] as $drv): ?>
                                        <span class="badge bg-light text-dark border fw-normal py-1 px-2">
                                            <i class="fas fa-circle text-primary me-1" style="font-size: 6px; vertical-align: middle;"></i> <?= htmlspecialchars($drv) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Kanan: Ringkasan Proyeksi & Narasi AI -->
                    <div class="col-lg-5 d-flex flex-column gap-3">
                        <div class="p-3 rounded-3 border bg-white shadow-xs">
                            <div class="row g-2 text-center align-items-center">
                                <div class="col-5">
                                    <div class="text-muted small">Harga <?= htmlspecialchars($pf['current_month_label']) ?></div>
                                    <div class="fw-bold fs-5 font-monospace text-dark">
                                        Rp <?= number_format((float)$pf['current_price'], 0, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">per <?= htmlspecialchars($pfMat['unit']) ?></small>
                                </div>
                                <div class="col-2">
                                    <i class="fas fa-arrow-right text-secondary fs-5"></i>
                                </div>
                                <div class="col-5">
                                    <div class="text-muted small">Ramalan <?= htmlspecialchars($pf['next_month_short']) ?></div>
                                    <div class="fw-bold fs-5 font-monospace text-<?= $pf['trend_badge'] === 'warning' ? 'warning-emphasis' : $pf['trend_badge'] ?>">
                                        Rp <?= number_format((float)$pf['forecast_price'], 0, ',', '.') ?>
                                    </div>
                                    <span class="badge bg-<?= $pf['trend_badge'] ?>-subtle text-<?= $pf['trend_badge'] === 'warning' ? 'warning-emphasis' : $pf['trend_badge'] ?> border font-monospace" style="font-size: 11px;">
                                        <?= $pfSign . number_format((float)$pf['price_change_pct'], 2, ',', '.') ?>% (<?= ($pf['price_diff_rp'] >= 0 ? '+' : '-') ?>Rp <?= number_format(abs((float)$pf['price_diff_rp']), 0, ',', '.') ?>)
                                    </span>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Rentang Estimasi Bulan Depan:</span>
                                <strong class="font-monospace text-dark">
                                    Rp <?= number_format((float)$pf['low_price'], 0, ',', '.') ?> &ndash; Rp <?= number_format((float)$pf['high_price'], 0, ',', '.') ?>
                                </strong>
                            </div>
                            <?php if ($pf['recommended_qty'] > 0): ?>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Dampak Biaya pada Q (<?= number_format((float)$pf['recommended_qty'], 0, ',', '.') ?> <?= htmlspecialchars($pfMat['unit']) ?>):</span>
                                    <strong class="font-monospace <?= $pf['budget_delta'] > 0 ? 'text-danger' : ($pf['budget_delta'] < 0 ? 'text-success' : 'text-secondary') ?>">
                                        <?= $pf['budget_delta'] >= 0 ? '+' : '-' ?>Rp <?= number_format(abs((float)$pf['budget_delta']), 0, ',', '.') ?>
                                    </strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 rounded-3 border bg-light flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold small text-uppercase text-dark mb-0">
                                    <i class="fas fa-robot text-primary me-1"></i> Analisis &amp; Strategi Harga AI
                                </h6>
                                <small class="text-muted font-monospace" style="font-size: 10px;"><?= htmlspecialchars($pf['ai_source']) ?></small>
                            </div>
                            <div class="text-secondary small lh-base">
                                <?= preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', nl2br(htmlspecialchars($pf['ai_narrative']))) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Matriks Peramalan Harga Seluruh Bahan Baku Bulan Depan -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <span class="fw-bold"><i class="fas fa-money-bill-trend-up me-2 text-success"></i> Matriks Ramalan Harga Pasar Bulan Depan (<?= htmlspecialchars($ps['next_month_label']) ?>)</span>
                    <div class="text-muted small mt-1" style="font-size: 11px;">
                        Perbandingan harga acuan bulan ini terhadap proyeksi harga pasar bulan depan beserta rekomendasi waktu pengadaan.
                    </div>
                </div>
                <div>
                    <input type="text" class="form-control form-control-sm table-search" data-target="#priceForecastTable" placeholder="Cari bahan baku...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="priceForecastTable">
                    <thead class="table-light small">
                        <tr>
                            <th>Bahan Baku</th>
                            <th class="text-end">Harga Saat Ini</th>
                            <th class="text-end">Ramalan <?= htmlspecialchars($ps['next_month_label']) ?></th>
                            <th class="text-center">Perubahan (MoM)</th>
                            <th class="text-end">Rentang Estimasi</th>
                            <th class="text-center">Sinyal Pengadaan</th>
                            <th class="text-end">Dampak Biaya (Q)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($price_forecasts as $itemPf): ?>
                            <?php
                            $im = $itemPf['material'];
                            $isSelected = ((int)$im['id'] === (int)$pfMat['id']);
                            $chgSign = $itemPf['price_change_pct'] > 0 ? '+' : '';
                            ?>
                            <tr class="<?= $isSelected ? 'table-primary bg-opacity-10' : '' ?>">
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($im['name']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($im['code']) ?> &bull; per <?= htmlspecialchars($im['unit']) ?></small>
                                </td>
                                <td class="text-end font-monospace text-dark">
                                    Rp <?= number_format((float)$itemPf['current_price'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-<?= $itemPf['trend_badge'] === 'warning' ? 'warning-emphasis' : $itemPf['trend_badge'] ?>">
                                    Rp <?= number_format((float)$itemPf['forecast_price'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $itemPf['trend_badge'] ?>-subtle text-<?= $itemPf['trend_badge'] === 'warning' ? 'warning-emphasis' : $itemPf['trend_badge'] ?> border font-monospace">
                                        <?= $chgSign . number_format((float)$itemPf['price_change_pct'], 2, ',', '.') ?>%
                                    </span>
                                    <div class="text-muted font-monospace" style="font-size: 10px;">
                                        <?= $itemPf['price_diff_rp'] >= 0 ? '+' : '-' ?>Rp <?= number_format(abs((float)$itemPf['price_diff_rp']), 0, ',', '.') ?>
                                    </div>
                                </td>
                                <td class="text-end font-monospace text-muted" style="font-size: 11px;">
                                    Rp <?= number_format((float)$itemPf['low_price'], 0, ',', '.') ?> &ndash; <?= number_format((float)$itemPf['high_price'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $itemPf['signal_badge'] ?>-subtle text-<?= $itemPf['signal_badge'] === 'warning' ? 'warning-emphasis' : $itemPf['signal_badge'] ?> border">
                                        <?= htmlspecialchars($itemPf['signal_label']) ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    <?php if ($itemPf['recommended_qty'] > 0): ?>
                                        <span class="fw-bold <?= $itemPf['budget_delta'] > 0 ? 'text-danger' : ($itemPf['budget_delta'] < 0 ? 'text-success' : 'text-muted') ?>">
                                            <?= $itemPf['budget_delta'] >= 0 ? '+' : '-' ?>Rp <?= number_format(abs((float)$itemPf['budget_delta']), 0, ',', '.') ?>
                                        </span>
                                        <div class="text-muted" style="font-size: 10px;">Q: <?= number_format((float)$itemPf['recommended_qty'], 0, ',', '.') ?> <?= htmlspecialchars($im['unit']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/ai/forecast?material_id=<?= (int)$im['id'] ?>&lookback=<?= (int)$lookback_days ?>#price-forecast-section" class="btn btn-outline-primary" title="Lihat Grafik & Analisis Harga">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalRecordPrice" onclick="openPriceModal(<?= (int)$im['id'] ?>, <?= (float)$itemPf['current_price'] ?>)" title="Catat / Perbarui Harga Pasar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Matrix Table of All Materials & Formula Calculations -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <span class="fw-bold"><i class="fas fa-table-list me-2 text-primary"></i> Matriks Perhitungan Formula Pengadaan Bahan Baku</span>
            <div class="text-muted small mt-1 font-monospace" style="font-size: 11px;">
                Rumus: Q = max(0, Kebutuhan 30 Hari + Safety Stock &minus; Stok Tersedia &minus; PO Datang)
            </div>
        </div>
        <div>
            <input type="text" class="form-control form-control-sm table-search" data-target="#aiMatrixTable" placeholder="Cari bahan...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="aiMatrixTable">
            <thead class="table-light small">
                <tr>
                    <th>Bahan Baku</th>
                    <th class="text-end">Laju Harian (Historis)</th>
                    <th class="text-end">Kebutuhan 30 Hari</th>
                    <th class="text-end">Stok Tersedia</th>
                    <th class="text-end">Rekomendasi Q</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi AI</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php foreach ($recommendations as $rec): ?>
                    <?php $m = $rec['material']; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                            <small class="text-muted font-monospace"><?= htmlspecialchars($m['code']) ?></small>
                        </td>
                        <td class="text-end">
                            <span class="font-monospace fw-bold text-dark"><?= number_format((float)$rec['daily_usage'], 1, ',', '.') ?> <?= $m['unit'] ?>/hr</span><br>
                            <small class="text-muted" style="font-size: 10px;"><?= $rec['usage_source'] ?? 'Historis' ?></small>
                        </td>
                        <td class="text-end font-monospace"><?= number_format((float)$rec['period_demand'], 0, ',', '.') ?> <?= $m['unit'] ?></td>
                        <td class="text-end font-monospace fw-bold <?= (float)$rec['available_stock'] <= (float)$rec['safety_stock'] ? 'text-danger' : 'text-success' ?>">
                            <?= number_format((float)$rec['available_stock'], 0, ',', '.') ?>
                        </td>
                        <td class="text-end font-monospace fw-bold <?= ((float)$rec['final_recommended_qty'] > 0) ? 'text-primary fs-6' : 'text-muted' ?>">
                            <?= number_format((float)$rec['final_recommended_qty'], 0, ',', '.') ?> <?= $m['unit'] ?>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-<?= strtolower($m['stock_status']) ?> px-2 py-1 rounded-pill">
                                <?= $m['stock_status'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="/ai/forecast?material_id=<?= $m['id'] ?>" class="btn btn-outline-dark" title="Fokus Analisis Gemini">
                                    <i class="fas fa-wand-magic-sparkles text-purple"></i> Analisis
                                </a>
                                <?php if ($rec['final_recommended_qty'] > 0): ?>
                                    <a href="/purchasing/pr/create-from-ai?material_id=<?= $m['id'] ?>&qty=<?= $rec['final_recommended_qty'] ?>" class="btn btn-primary" title="Buat PR">
                                        <i class="fas fa-cart-plus"></i> PR
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- History Log of AI Forecasts -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 fw-bold">
        <i class="fas fa-history me-2 text-primary"></i> Jejak Riwayat Analisis AI Terakhir
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Waktu Analisis</th>
                    <th>Bahan Baku</th>
                    <th class="text-end">Rekomendasi Q</th>
                    <th class="text-center">Risiko</th>
                    <th>Ringkasan Narasi AI</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="font-monospace text-muted" style="font-size: 11px;">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                        </td>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($log['material_name']) ?></td>
                        <td class="text-end font-monospace fw-bold text-primary">
                            <?= number_format((float)$log['recommended_qty'], 0, ',', '.') ?> <?= $log['material_unit'] ?>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-<?= strtolower($log['risk_level']) ?> px-2 py-1 rounded-pill">
                                <?= $log['risk_level'] ?>
                            </span>
                        </td>
                        <td class="text-secondary" style="max-width: 450px;">
                            <small><?= htmlspecialchars(mb_strimwidth($log['ai_narrative'], 0, 160, '...')) ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Catat / Perbarui Harga Pasar Bahan Baku -->
<div class="modal fade" id="modalRecordPrice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/ai/price-history/store" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        <i class="fas fa-money-bill-wave text-primary me-2"></i> Catat / Perbarui Harga Pasar Bahan Baku
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bahan Baku</label>
                        <select name="material_id" id="modalPriceMaterialId" class="form-select form-select-sm" required>
                            <?php foreach ($materials as $mOpt): ?>
                                <option value="<?= (int)$mOpt['id'] ?>">
                                    <?= htmlspecialchars($mOpt['code'] . ' — ' . $mOpt['name'] . ' (' . $mOpt['unit'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Periode Bulan</label>
                            <input type="month" name="price_month" class="form-control form-control-sm font-monospace" value="<?= date('Y-m') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Pasar Aktual (Rp / Satuan)</label>
                            <input type="number" name="market_price" id="modalPriceInput" class="form-control form-control-sm font-monospace" min="1" step="10" required placeholder="Contoh: 5200">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sumber Data Harga</label>
                        <select name="source" class="form-select form-select-sm">
                            <option value="Penawaran Supplier / Quotation">Penawaran Supplier / Quotation</option>
                            <option value="Harga PO Aktual">Harga PO Aktual</option>
                            <option value="Indeks Pasar Kimia (ICIS / Domestik)">Indeks Pasar Kimia (ICIS / Domestik)</option>
                            <option value="Survei Pasar Purchasing">Survei Pasar Purchasing</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan Pasar (Opsional)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Kenaikan tarif energi / kurs USD menguat">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sync_master_cost" value="1" id="syncMasterCostCheck" checked>
                        <label class="form-check-label small text-secondary" for="syncMasterCostCheck">
                            Sinkronkan juga ke harga satuan master bahan (<code>unit_cost</code>)
                        </label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i> Simpan &amp; Hitung Ulang Ramalan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPriceModal(materialId, currentPrice) {
    const sel = document.getElementById('modalPriceMaterialId');
    const inp = document.getElementById('modalPriceInput');
    if (sel && materialId) sel.value = String(materialId);
    if (inp && currentPrice) inp.value = Math.round(currentPrice);
}

<?php if (!empty($active_price_forecast)): ?>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('marketPriceForecastChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const labels = <?= json_encode($active_price_forecast['chart_labels']) ?>;
    const histPrices = <?= json_encode($active_price_forecast['chart_historical']) ?>;
    const forecastPrice = <?= json_encode((float)$active_price_forecast['forecast_price']) ?>;
    const lowPrice = <?= json_encode((float)$active_price_forecast['low_price']) ?>;
    const highPrice = <?= json_encode((float)$active_price_forecast['high_price']) ?>;
    const unitLabel = <?= json_encode($active_price_forecast['material']['unit'] ?? 'Kg') ?>;
    const forecastColor = <?= json_encode($pfLineColor ?? '#2563eb') ?>;

    // Historical line stops at current month (null on next month)
    const historicalSeries = [...histPrices, null];
    // Forecast segment connects last historical month to predicted next month
    const forecastSeries = new Array(Math.max(0, histPrices.length - 1)).fill(null);
    if (histPrices.length > 0) {
        forecastSeries.push(histPrices[histPrices.length - 1]);
    }
    forecastSeries.push(forecastPrice);

    // Upper & lower bound markers on next month
    const upperSeries = new Array(histPrices.length).fill(null);
    upperSeries.push(highPrice);
    const lowerSeries = new Array(histPrices.length).fill(null);
    lowerSeries.push(lowPrice);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Harga Historis (Rp/' + unitLabel + ')',
                    data: historicalSeries,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.10)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb',
                    tension: 0.25,
                    fill: true
                },
                {
                    label: 'Proyeksi Bulan Depan',
                    data: forecastSeries,
                    borderColor: forecastColor,
                    backgroundColor: forecastColor,
                    borderDash: [6, 4],
                    borderWidth: 2.5,
                    pointRadius: (ctx) => ctx.dataIndex === labels.length - 1 ? 7 : 4,
                    pointStyle: 'circle',
                    tension: 0.1,
                    fill: false
                },
                {
                    label: 'Batas Atas Estimasi',
                    data: upperSeries,
                    borderColor: 'rgba(100, 116, 139, 0.7)',
                    backgroundColor: 'rgba(100, 116, 139, 0.7)',
                    pointRadius: 4,
                    pointStyle: 'triangle',
                    showLine: false
                },
                {
                    label: 'Batas Bawah Estimasi',
                    data: lowerSeries,
                    borderColor: 'rgba(100, 116, 139, 0.7)',
                    backgroundColor: 'rgba(100, 116, 139, 0.7)',
                    pointRadius: 4,
                    pointStyle: 'rectRot',
                    showLine: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            if (context.parsed.y === null || context.parsed.y === undefined) return null;
                            return context.dataset.label + ': Rp ' + Number(context.parsed.y).toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: (val) => 'Rp ' + Number(val).toLocaleString('id-ID'),
                        font: { size: 11 }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.2)' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

@include('layouts.footer')
