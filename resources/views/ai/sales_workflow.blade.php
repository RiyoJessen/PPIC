@php
$pageTitle = 'Alur Terpadu Forecast Sales 12 Bulan & Pengadaan AI';
@endphp
@include('layouts.header')

<!-- Page Header -->
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-dark btn-sm px-3" data-bs-toggle="collapse" data-bs-target="#flowchartMapCollapse" aria-expanded="true">
            <i class="fas fa-sitemap me-1 text-primary"></i> Peta Visual Flowchart
        </button>
        <a href="/ai/forecast" class="btn btn-outline-primary btn-sm px-3">
            <i class="fas fa-chart-line me-1"></i> Forecast Stok &amp; Harga Pasar
        </a>
        <a href="/purchasing/pr" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="fas fa-file-invoice-dollar me-1"></i> Daftar PR &amp; PO
        </a>
    </div>
</div>

<!-- ====================================================================== -->
<!-- PETA VISUAL FLOWCHART INTERAKTIF (SESUAI DIAGRAM ALUR TERBARU)         -->
<!-- ====================================================================== -->
<div class="collapse show mb-4" id="flowchartMapCollapse">
    <div class="card border-0 shadow-sm" style="background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: #f8fafc;">
        <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-project-diagram text-info"></i>
                <span class="fw-bold text-white">Peta Interaktif Flowchart Sistem PPIC (Klik Tahap untuk Navigasi)</span>
            </div>
            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace" id="activeFlowStageBadge">
                Tahap Aktif: 1. Input Forecast Sales 12 Bulan
            </span>
        </div>
        <div class="card-body p-3 p-lg-4">
            <!-- Baris Alur Visual -->
            <div class="row g-3 align-items-stretch text-center">
                <!-- STEP 1 -->
                <div class="col-12 col-md-6 col-xl">
                    <div class="p-3 rounded-3 border border-primary bg-dark bg-opacity-50 h-100 flow-node-card cursor-pointer" id="flowNode1" onclick="activateWorkflowStage(1)" style="cursor: pointer; transition: all .2s;">
                        <div class="badge bg-primary rounded-pill mb-2">TAHAP 1 &bull; MULAI</div>
                        <div class="fw-bold text-white small mb-1"><i class="fas fa-chart-bar text-info me-1"></i> Kebutuhan Sales</div>
                        <div class="text-white-50" style="font-size: 11px;">
                            Input Forecast Sales 12 Bulan &rarr; Mengecek Finish Good
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="badge bg-success-subtle text-success font-monospace" style="font-size: 10px;" id="flowStatus1">Siap Input 12 Bulan</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 2 -->
                <div class="col-12 col-md-6 col-xl">
                    <div class="p-3 rounded-3 border border-secondary border-opacity-50 bg-dark bg-opacity-50 h-100 flow-node-card" id="flowNode2" onclick="activateWorkflowStage(2)" style="cursor: pointer; transition: all .2s;">
                        <div class="badge bg-warning text-dark rounded-pill mb-2">TAHAP 2 &bull; FORMULA BOM</div>
                        <div class="fw-bold text-white small mb-1"><i class="fas fa-flask-vial text-warning me-1"></i> Mengecek Formula Material</div>
                        <div class="text-white-50" style="font-size: 11px;">
                            <strong>Ada:</strong> AI Analisis Sales &amp; Material<br>
                            <strong>Tidak Ada:</strong> AI Buat Formula &rarr; R&amp;D Evaluasi
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="badge bg-info-subtle text-info font-monospace" style="font-size: 10px;" id="flowStatus2">Cek BOM &amp; R&amp;D</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 3 -->
                <div class="col-12 col-md-6 col-xl">
                    <div class="p-3 rounded-3 border border-secondary border-opacity-50 bg-dark bg-opacity-50 h-100 flow-node-card" id="flowNode3" onclick="activateWorkflowStage(3)" style="cursor: pointer; transition: all .2s;">
                        <div class="badge bg-info text-dark rounded-pill mb-2">TAHAP 3 &bull; MRP 12 BULAN</div>
                        <div class="fw-bold text-white small mb-1"><i class="fas fa-boxes-stacked text-info me-1"></i> Hitung Material &amp; Safety Stock</div>
                        <div class="text-white-50" style="font-size: 11px;">
                            <strong>Stok Cukup (Ya):</strong> &rarr; Update Data AI<br>
                            <strong>Tidak:</strong> Hitung Kekurangan + AI &rarr; Rekomendasi PR
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="badge bg-danger-subtle text-danger font-monospace" style="font-size: 10px;" id="flowStatus3">Analisis Defisit</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 -->
                <div class="col-12 col-md-6 col-xl">
                    <div class="p-3 rounded-3 border border-secondary border-opacity-50 bg-dark bg-opacity-50 h-100 flow-node-card" id="flowNode4" onclick="activateWorkflowStage(4)" style="cursor: pointer; transition: all .2s;">
                        <div class="badge bg-purple text-white rounded-pill mb-2" style="background-color: #9333ea;">TAHAP 4 &bull; VENDOR &amp; PO</div>
                        <div class="fw-bold text-white small mb-1"><i class="fas fa-handshake text-warning me-1"></i> Purchasing Evaluasi Vendor</div>
                        <div class="text-white-50" style="font-size: 11px;">
                            Bandingkan Harga, Lead Time, Kualitas, MOQ &rarr; AI Rekomendasi &rarr; Setuju? &rarr; Terbit PO + Notif PPIC
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="badge bg-warning-subtle text-warning font-monospace" style="font-size: 10px;" id="flowStatus4">Evaluasi Multi-Vendor</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 5 -->
                <div class="col-12 col-md-12 col-xl">
                    <div class="p-3 rounded-3 border border-secondary border-opacity-50 bg-dark bg-opacity-50 h-100 flow-node-card" id="flowNode5" onclick="activateWorkflowStage(5)" style="cursor: pointer; transition: all .2s;">
                        <div class="badge bg-success rounded-pill mb-2">TAHAP 5 &bull; LOOP AI</div>
                        <div class="fw-bold text-white small mb-1"><i class="fas fa-sync-alt text-success me-1"></i> Inbound &amp; Update Data AI</div>
                        <div class="text-white-50" style="font-size: 11px;">
                            Barang Diterima &amp; Update Stok &rarr; Update Data AI &rarr; Forecast Periode Berikutnya
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="badge bg-secondary-subtle text-light font-monospace" style="font-size: 10px;" id="flowStatus5">Siklus Berkelanjutan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- NAVIGASI TAB TAHAPAN FLOWCHART                                         -->
<!-- ====================================================================== -->
<ul class="nav nav-pills nav-fill bg-white p-2 rounded-3 shadow-sm border mb-4 gap-2" id="workflowStageTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold small py-2" id="tabBtnStage1" type="button" onclick="activateWorkflowStage(1)">
            <span class="badge bg-white text-primary me-1">1</span> Forecast Sales 12 Bln &amp; Cek FG
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold small py-2" id="tabBtnStage2" type="button" onclick="activateWorkflowStage(2)">
            <span class="badge bg-secondary-subtle text-dark me-1">2</span> Cek Formula BOM (AI &amp; R&amp;D)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold small py-2" id="tabBtnStage3" type="button" onclick="activateWorkflowStage(3)">
            <span class="badge bg-secondary-subtle text-dark me-1">3</span> MRP 12 Bln, Safety Stock &amp; PR
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold small py-2" id="tabBtnStage4" type="button" onclick="activateWorkflowStage(4)">
            <span class="badge bg-secondary-subtle text-dark me-1">4</span> Evaluasi Vendor AI &amp; Terbit PO
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold small py-2" id="tabBtnStage5" type="button" onclick="activateWorkflowStage(5)">
            <span class="badge bg-secondary-subtle text-dark me-1">5</span> Terima Barang &amp; Update Data AI
        </button>
    </li>
</ul>

<!-- ====================================================================== -->
<!-- TAHAP 1: KEBUTUHAN SALES, INPUT FORECAST 12 BULAN & CEK FINISH GOOD    -->
<!-- ====================================================================== -->
<div id="workflowStage1" class="workflow-stage-pane">
    <div class="row g-4">
        <!-- Kiri: Pilih Produk & Input 12 Bulan -->
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <span class="badge bg-primary me-2">NODE 1 &ndash; 2</span>
                        <span class="fw-bold text-dark">Kebutuhan Sales &amp; Input Forecast Sales Selama 12 Bulan</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="applySalesPreset('seasonal')">
                            <i class="fas fa-wave-square me-1"></i> Musiman PDAM
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" onclick="applySalesPreset('growth')">
                            <i class="fas fa-arrow-trend-up me-1"></i> Tren Naik +15%
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="applySalesPreset('flat')">
                            <i class="fas fa-equals me-1"></i> Kontrak Stabil
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Pemilih Produk Jadi (Finish Good) -->
                    <div class="row g-3 align-items-end mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-md-7">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fas fa-box-open text-primary me-1"></i> Pilih Produk Jadi (Finish Good) untuk Forecast Sales 12 Bulan:
                            </label>
                            <?php if (empty($products_workflow)): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 bg-white border rounded">
                                    <span class="small text-muted"><i class="fas fa-info-circle me-1"></i> Belum ada Produk Jadi (Finish Good) terdaftar.</span>
                                    <a href="/master/products" class="btn btn-primary btn-sm py-0 px-2">
                                        <i class="fas fa-plus me-1"></i> Tambah Produk Jadi
                                    </a>
                                </div>
                                <select id="wfProductSelect" class="form-select form-select-sm d-none"></select>
                            <?php else: ?>
                                <select id="wfProductSelect" class="form-select form-select-sm fw-semibold" onchange="onSelectWorkflowProduct()">
                                    <?php foreach ($products_workflow as $idx => $pw): ?>
                                        <option value="<?= $idx ?>">
                                            <?= htmlspecialchars($pw['code'] . ' — ' . $pw['name']) ?>
                                            <?= $pw['has_bom'] ? ' [Formula BOM Tersedia]' : ' [Belum Ada Formula BOM]' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Ukuran Batch Standar</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="wfBatchSizeDisplay" class="form-control font-monospace bg-white" readonly>
                                <span class="input-group-text" id="wfUnitDisplay">Kg</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <span id="wfBomBadge" class="badge bg-success-subtle text-success border w-100 py-2">
                                <i class="fas fa-check-circle me-1"></i> BOM Aktif
                            </span>
                        </div>
                    </div>

                    <!-- Grid Input Forecast Sales 12 Bulan -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold small text-uppercase text-muted mb-0">
                            <i class="fas fa-calendar-days text-primary me-1"></i> Matriks Input Forecast Penjualan 12 Bulan (M1 &ndash; M12)
                        </h6>
                        <small class="text-muted">Ubah angka di bawah untuk simulasi real-time</small>
                    </div>

                    <div class="row g-2 mb-4" id="wfMonthlyInputsContainer">
                        <?php foreach ($month_labels as $mIdx => $mLabel): ?>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="p-2 border rounded bg-white shadow-2xs">
                                    <label class="form-label text-muted font-monospace mb-1 d-flex justify-content-between" style="font-size: 11px;">
                                        <span>M<?= $mIdx + 1 ?></span>
                                        <strong class="text-dark"><?= htmlspecialchars($mLabel) ?></strong>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input
                                            type="number"
                                            class="form-control form-control-sm font-monospace fw-bold text-end wf-month-input"
                                            data-month-index="<?= $mIdx ?>"
                                            min="0"
                                            step="500"
                                            value="25000"
                                            oninput="recalculateWorkflow()"
                                        >
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Grafik Proyeksi Sales 12 Bulan vs Stok Finish Good -->
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-dark">
                                <i class="fas fa-chart-column text-primary me-1"></i> Grafik Forecast Sales 12 Bulan vs Stok Finish Good Tersedia
                            </span>
                            <span class="badge bg-white text-secondary border font-monospace" id="wfChartSummaryBadge">Total 12 Bulan: 0 Kg</span>
                        </div>
                        <div style="position: relative; height: 220px;">
                            <canvas id="wfSales12MChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanan: Mengecek Finish Good & Ringkasan Net Production -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm border-top border-4 border-primary h-100">
                <div class="card-header bg-white py-3">
                    <span class="badge bg-info text-dark me-2">NODE 3</span>
                    <span class="fw-bold text-dark">Mengecek Finish Good (Stok Produk Jadi)</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small mb-3">
                            Sistem memeriksa saldo produk jadi di <strong>Gudang Finished Goods (GUD-04)</strong> untuk menghitung kebutuhan produksi bersih (<em>Net Production Requirement</em>) selama 12 bulan.
                        </p>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">Stok Finish Good Tersedia di Gudang Saat Ini</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="wfFgStockInput" class="form-control font-monospace fw-bold" value="15000" min="0" step="500" oninput="recalculateWorkflow()">
                                <span class="input-group-text">Kg</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">Target Buffer / Safety Stock Finish Good</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="wfFgBufferInput" class="form-control font-monospace" value="5000" min="0" step="500" oninput="recalculateWorkflow()">
                                <span class="input-group-text">Kg</span>
                            </div>
                        </div>

                        <hr>

                        <!-- Ringkasan Kalkulasi Finish Good -->
                        <div class="p-3 rounded-3 bg-light border mb-3">
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted">Total Forecast Sales (12 Bln):</span>
                                <strong class="font-monospace text-dark" id="wfTotalSales12M">0 Kg</strong>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted">(+) Target Buffer Finish Good:</span>
                                <span class="font-monospace text-secondary" id="wfFgBufferLabel">0 Kg</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2 pb-2 border-bottom">
                                <span class="text-muted">(&minus;) Stok Finish Good Gudang:</span>
                                <span class="font-monospace text-success fw-semibold" id="wfFgStockLabel">0 Kg</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-bold small text-dark">Kebutuhan Produksi Bersih:</span>
                                <span class="fw-bold fs-5 font-monospace text-primary" id="wfNetProduction12M">0 Kg</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="text-muted small">Estimasi Total Batch (12 Bln):</span>
                                <span class="badge bg-primary font-monospace" id="wfRequiredBatches12M">0 Batch</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-2 fw-semibold shadow-sm" onclick="activateWorkflowStage(2)">
                            Lanjut ke Tahap 2: Mengecek Formula Material <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- TAHAP 2: MENGECEK FORMULA MATERIAL (CABANG ADA vs TIDAK ADA)           -->
<!-- ====================================================================== -->
<div id="workflowStage2" class="workflow-stage-pane d-none">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <span class="badge bg-warning text-dark me-2">NODE 4 &ndash; 6</span>
                <span class="fw-bold text-dark">Mengecek Formula Material (BOM) &amp; Evaluasi R&amp;D / AI</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Simulasi Cabang Flowchart:</span>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="forceBomBranch(true)">
                    <i class="fas fa-check me-1"></i> Cabang "Ada Formula"
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="forceBomBranch(false)">
                    <i class="fas fa-wand-magic-sparkles me-1"></i> Cabang "Tidak Ada &rarr; AI Buat Formula Baru"
                </button>
            </div>
        </div>
        <div class="card-body p-4">
            <!-- CABANG 1: FORMULA ADA -->
            <div id="wfBranchFormulaExists">
                <div class="alert alert-success border-start border-4 border-success d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h6 class="fw-bold text-success mb-1">
                            <i class="fas fa-check-circle me-1"></i> JALUR FLOWCHART: FORMULA MATERIAL TERSEDIA ("ADA")
                        </h6>
                        <div class="small text-secondary">
                            Produk <strong id="wfFormulaProdName1">-</strong> telah memiliki formula Bill of Materials aktif. Sistem langsung menjalankan <strong>AI Analisis Forecast Sales dan Kebutuhan Material</strong>.
                        </div>
                    </div>
                    <span class="badge bg-success px-3 py-2 font-monospace" id="wfActiveBomVersionBadge">BOM v1.0</span>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <h6 class="fw-bold small text-uppercase text-dark mb-2">
                            <i class="fas fa-list-check text-primary me-1"></i> Komposisi Formula Material Standar Per Batch
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Bahan Baku</th>
                                        <th class="text-end">Kebutuhan / Batch</th>
                                        <th class="text-end">Total 12 Bulan</th>
                                    </tr>
                                </thead>
                                <tbody class="small" id="wfBomItemsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 rounded-3 ai-recommendation-card h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge ai-badge rounded-pill px-3 py-1">
                                        <i class="fas fa-brain me-1"></i> AI Analisis Forecast Sales &amp; Material
                                    </span>
                                    <small class="text-muted font-monospace"><?= $has_api_key ? 'Gemini Live' : 'AI Engine DKJ' ?></small>
                                </div>
                                <div class="small text-dark lh-base mt-2" id="wfAiSalesMaterialNarrative"></div>
                            </div>
                            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                                <a href="/master/boms/ai-advisor" class="btn btn-outline-dark btn-sm">
                                    <i class="fas fa-flask me-1"></i> Buka AI BOM Advisor
                                </a>
                                <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold" onclick="activateWorkflowStage(3)">
                                    Hitung Kebutuhan Material 12 Bulan <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CABANG 2: FORMULA TIDAK ADA -> AI MEMBUAT FORMULA BARU -> R&D EVALUASI -->
            <div id="wfBranchFormulaMissing" class="d-none">
                <div class="alert alert-warning border-start border-4 border-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">
                    <div>
                        <h6 class="fw-bold text-dark mb-1">
                            <i class="fas fa-triangle-exclamation text-warning me-1"></i> JALUR FLOWCHART: FORMULA MATERIAL BELUM TERSEDIA ("TIDAK ADA")
                        </h6>
                        <div class="small text-secondary">
                            Produk <strong id="wfFormulaProdName2">-</strong> belum memiliki resep BOM aktif. Sesuai flowchart: <strong>Jika belum ada, AI akan membuat Formula baru &rarr; R&amp;D Evaluasi Formula &rarr; Hitung Kebutuhan Material 12 Bulan</strong>.
                        </div>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 font-monospace" id="wfRdFlowStatusBadge">Menunggu Generate AI</span>
                </div>

                <div class="row g-4">
                    <!-- Langkah A: AI Membuat Formula Baru -->
                    <div class="col-lg-6">
                        <div class="card border shadow-2xs h-100">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-dark">
                                    <i class="fas fa-wand-magic-sparkles text-purple me-1" style="color:#9333ea;"></i> Langkah 2A: AI Membuat Formula Baru
                                </span>
                                <button type="button" class="btn btn-primary btn-sm py-1 px-3" id="btnAiGenerateFormula" onclick="generateAiNewFormula()">
                                    <i class="fas fa-bolt me-1"></i> Generate Formula Baru dengan AI
                                </button>
                            </div>
                            <div class="card-body" id="wfAiDraftFormulaBox">
                                <div class="text-center text-muted py-4" id="wfAiDraftPlaceholder">
                                    <i class="fas fa-flask-vial fs-2 text-secondary opacity-50 mb-2 d-block"></i>
                                    Klik tombol <strong>"Generate Formula Baru dengan AI"</strong> untuk menyusun komposisi stoikiometri bahan baku otomatis.
                                </div>
                                <div id="wfAiDraftContent" class="d-none">
                                    <div class="d-flex justify-content-between small mb-2">
                                        <span>Kode Draft AI: <strong class="font-monospace text-primary">v1.0-AI-NEW</strong></span>
                                        <span>Target Yield: <strong class="text-success">98.5%</strong></span>
                                    </div>
                                    <div class="table-responsive mb-2">
                                        <table class="table table-sm table-bordered small mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Bahan Baku</th>
                                                    <th class="text-end">Komposisi AI / Batch</th>
                                                </tr>
                                            </thead>
                                            <tbody id="wfAiDraftTableBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 bg-light rounded border small text-muted" id="wfAiDraftReactionNote"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Langkah B: R&D Evaluasi Formula -->
                    <div class="col-lg-6">
                        <div class="card border shadow-2xs h-100">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-dark">
                                    <i class="fas fa-microscope text-warning me-1"></i> Langkah 2B: R&amp;D Evaluasi Formula
                                </span>
                                <span class="badge bg-secondary" id="wfRdApprovalBadge">Belum Dievaluasi</span>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <p class="small text-muted mb-3">
                                        Tim R&amp;D memeriksa kelayakan reaksi kimia, kestabilan pH, serta kesesuaian spesifikasi Certificate of Analysis (COA) sebelum formula dipakai untuk menghitung kebutuhan material 12 bulan.
                                    </p>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Catatan Evaluasi Tim R&amp;D</label>
                                        <textarea id="wfRdEvalNotes" class="form-control form-control-sm" rows="2">Komposisi stoikiometri AI telah sesuai standar viskositas & kadar Al2O3 PT Dunia Kimia Jaya Gresik.</textarea>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generateAiNewFormula()">
                                        <i class="fas fa-rotate me-1"></i> Regenerate Komposisi AI
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm px-3 fw-semibold" id="btnRdApproveFormula" onclick="approveRdFormula()" disabled>
                                        <i class="fas fa-check-double me-1"></i> R&amp;D Setujui Formula &amp; Lanjut MRP 12 Bulan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- TAHAP 3: HITUNG KEBUTUHAN MATERIAL 12 BULAN & ANALISIS SAFETY STOCK    -->
<!-- ====================================================================== -->
<div id="workflowStage3" class="workflow-stage-pane d-none">
    <!-- Decision Diamond Banner: Stok dan Material Mencukupi? -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-info text-dark me-2">NODE 7 &ndash; 10</span>
                        <span class="fw-bold text-dark">Hitung Kebutuhan Material 12 Bulan &amp; Analisis Minimum / Safety Stock</span>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace" id="wfMrpDeficitSummaryBadge">Menganalisis...</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Bahan Baku</th>
                                <th class="text-end">Kebutuhan 12 Bln</th>
                                <th class="text-end">Min / Safety Stock</th>
                                <th class="text-end">Stok Tersedia + PO</th>
                                <th class="text-center">Stok Mencukupi?</th>
                                <th class="text-end">Kekurangan (Defisit)</th>
                                <th class="text-center">Rekomendasi PR AI</th>
                            </tr>
                        </thead>
                        <tbody class="small" id="wfMrp12MTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kanan: Decision Diamond "Stok dan Material Mencukupi?" -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold text-dark"><i class="fas fa-code-branch text-primary me-2"></i> Keputusan: Stok &amp; Material Mencukupi?</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <!-- Jalur YA (Stok Aman) -->
                        <div class="p-3 rounded-3 border border-success-subtle bg-success-subtle bg-opacity-10 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-success"><i class="fas fa-check-circle me-1"></i> Cabang "YA (Stok Aman)"</span>
                                <span class="badge bg-success font-monospace" id="wfSafeMatCountBadge">0 Bahan</span>
                            </div>
                            <p class="small text-secondary mb-2">
                                Jika stok tersedia mampu menutup kebutuhan material serta berada di atas Minimum Stock &amp; Safety Stock, alur langsung menuju <strong>Update Data AI &rarr; Forecast Periode Berikutnya</strong>.
                            </p>
                            <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="activateWorkflowStage(5)">
                                <i class="fas fa-forward me-1"></i> Langsung ke Update Data AI (Stok Aman)
                            </button>
                        </div>

                        <!-- Jalur TIDAK (Ada Kekurangan Material) -->
                        <div class="p-3 rounded-3 border border-danger-subtle bg-danger-subtle bg-opacity-10">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Cabang "TIDAK (Defisit Material)"</span>
                                <span class="badge bg-danger font-monospace" id="wfDeficitMatCountBadge">0 Bahan</span>
                            </div>
                            <p class="small text-secondary mb-2">
                                Sistem menjalankan <strong>Hitung Kekurangan Material &harr; AI</strong> untuk menyusun <strong>Rekomendasi Purchase Requisition (PR)</strong> dan meneruskannya ke <strong>Purchasing Evaluasi Vendor</strong>.
                            </p>
                            <button type="button" class="btn btn-danger btn-sm w-100 fw-semibold shadow-sm" onclick="activateWorkflowStage(4)">
                                <i class="fas fa-file-invoice-dollar me-1"></i> Terbitkan Rekomendasi PR &amp; Evaluasi Vendor <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-2 bg-light rounded border small text-muted mt-3">
                        <i class="fas fa-robot text-primary me-1"></i> <strong>Rumus Defisit AI:</strong>
                        <code>Max(0, Kebutuhan + Safety Stock &minus; Stok Tersedia &minus; PO Berjalan)</code> disesuaikan dengan MOQ.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- TAHAP 4: PURCHASING EVALUASI VENDOR, AI REKOMENDASI & TERBIT PO        -->
<!-- ====================================================================== -->
<div id="workflowStage4" class="workflow-stage-pane d-none">
    <div class="row g-4">
        <!-- Kiri: Tabel Perbandingan Multi-Vendor (Harga, Lead Time, Kualitas, MOQ) -->
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <span class="badge bg-purple text-white me-2" style="background-color:#9333ea;">NODE 11 &ndash; 14</span>
                        <span class="fw-bold text-dark">Purchasing Evaluasi Vendor: Bandingkan Harga, Lead Time, Kualitas &amp; MOQ</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-outline-dark btn-sm py-0 px-2 active" id="btnWeightBalanced" onclick="setVendorPriority('balanced')">
                            <i class="fas fa-scale-balanced me-1"></i> Seimbang AI
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="btnWeightPrice" onclick="setVendorPriority('price')">
                            <i class="fas fa-tag me-1"></i> Prioritas Harga
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2 text-dark" id="btnWeightLead" onclick="setVendorPriority('leadtime')">
                            <i class="fas fa-shipping-fast me-1"></i> Prioritas Lead Time
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" id="btnWeightQuality" onclick="setVendorPriority('quality')">
                            <i class="fas fa-award me-1"></i> Prioritas Kualitas
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Pilih Material PR yang Dievaluasi -->
                    <div class="row g-3 align-items-center mb-3 p-3 bg-light rounded-3 border">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fas fa-cart-shopping text-primary me-1"></i> Bahan Baku pada Rekomendasi Purchase Requisition (PR):
                            </label>
                            <select id="wfVendorMaterialSelect" class="form-select form-select-sm fw-semibold" onchange="renderVendorEvaluation()">
                                <?php foreach ($materials as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" data-cost="<?= (float)$m['unit_cost'] ?>" data-moq="<?= (float)$m['moq'] ?>" data-unit="<?= htmlspecialchars($m['unit']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>" data-code="<?= htmlspecialchars($m['code']) ?>">
                                        <?= htmlspecialchars($m['code'] . ' — ' . $m['name']) ?> (Acuan: Rp <?= number_format((float)$m['unit_cost'], 0, ',', '.') ?>/<?= htmlspecialchars($m['unit']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted mb-1">Kuantitas PR yang Diajukan</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="wfVendorPrQty" class="form-control font-monospace fw-bold" value="15000" min="100" step="500" oninput="renderVendorEvaluation()">
                                <span class="input-group-text" id="wfVendorPrUnit">Kg</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Perbandingan Multi-Vendor -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Peringkat &amp; Vendor</th>
                                    <th class="text-end">Harga / Satuan</th>
                                    <th class="text-center">Lead Time</th>
                                    <th class="text-center">Kualitas (Rating)</th>
                                    <th class="text-end">MOQ Vendor</th>
                                    <th class="text-end">Estimasi Nilai PO</th>
                                    <th class="text-center">Skor AI</th>
                                    <th class="text-center">Pilih</th>
                                </tr>
                            </thead>
                            <tbody class="small" id="wfVendorComparisonBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanan: AI Rekomendasi Vendor -> Vendor Disetujui? -> Terbit PO + Notif PPIC -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm border-top border-4 border-purple h-100" style="border-top-color: #9333ea !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="fas fa-wand-magic-sparkles text-purple me-1" style="color:#9333ea;"></i> AI Rekomendasi Vendor &amp; Approval</span>
                    <span class="badge ai-badge">NODE 13 &ndash; 16</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <!-- Kotak Rekomendasi AI -->
                        <div class="p-3 rounded-3 ai-recommendation-card mb-3">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Vendor Terbaik Pilihan AI:</div>
                            <h5 class="fw-bold text-dark mb-1" id="wfRecommendedVendorName">-</h5>
                            <div class="d-flex flex-wrap gap-2 mb-2" id="wfRecommendedVendorBadges"></div>
                            <p class="small text-secondary mb-0 lh-base" id="wfRecommendedVendorReason"></p>
                        </div>

                        <!-- Decision Box: Vendor Disetujui? -->
                        <div class="p-3 rounded-3 border bg-light mb-3">
                            <div class="fw-bold small text-dark mb-2">
                                <i class="fas fa-gavel text-warning me-1"></i> Keputusan: Vendor Disetujui?
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-sm py-2 fw-semibold shadow-sm" onclick="approveVendorAndIssuePo()">
                                    <i class="fas fa-check-circle me-1"></i> YA &mdash; Setujui Vendor &amp; Terbitkan PO Resmi
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="rejectVendorAndReevaluate()">
                                    <i class="fas fa-rotate-left me-1"></i> TIDAK &mdash; Tolak &amp; Evaluasi Kandidat Vendor Berikutnya
                                </button>
                            </div>
                        </div>

                        <!-- Feedback Notifikasi ke PPIC Bahwa Sudah PO -->
                        <div id="wfPoIssuedNotificationBox" class="alert alert-info border-start border-4 border-info d-none mb-0">
                            <div class="fw-bold small text-dark mb-1">
                                <i class="fas fa-bell text-primary me-1"></i> PO Terbit &amp; Notifikasi Terkirim ke PPIC!
                            </div>
                            <div class="small text-secondary" id="wfPoIssuedNotificationText"></div>
                            <div class="mt-2 d-flex gap-2">
                                <a href="/purchasing/pr" id="wfRealPrActionLink" class="btn btn-primary btn-sm py-0 px-2">
                                    <i class="fas fa-external-link-alt me-1"></i> Proses di Modul PR/PO Nyata
                                </a>
                                <button type="button" class="btn btn-success btn-sm py-0 px-2" onclick="activateWorkflowStage(5)">
                                    Lanjut Terima Barang <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mt-3">
                        <i class="fas fa-info-circle text-primary me-1"></i> Saat PO diterbitkan, sistem otomatis menjalankan cabang flowchart: <strong>Kirimkan Notifikasi ke PPIC Bahwa Sudah PO</strong>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- TAHAP 5: BARANG DITERIMA, UPDATE STOK, UPDATE DATA AI & PERIODE BERIKUT-->
<!-- ====================================================================== -->
<div id="workflowStage5" class="workflow-stage-pane d-none">
    <div class="row g-4">
        <!-- Node: Barang Diterima dan Update Stok -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-info">
                <div class="card-header bg-white py-3">
                    <span class="badge bg-info text-dark me-2">NODE 17</span>
                    <span class="fw-bold text-dark">Barang Diterima dan Update Stok</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="small text-muted mb-3">
                            Divisi Gudang menerima pengiriman fisik bahan baku dari vendor sesuai PO, mencatat nomor Batch/Lot, dan tim QC memverifikasi COA sehingga stok bebas gudang bertambah.
                        </p>
                        <div class="p-3 bg-light rounded-3 border mb-3" id="wfInboundStatusCard">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Status Penerimaan Fisik:</span>
                                <span class="badge bg-warning text-dark" id="wfInboundBadge">Menunggu Konfirmasi</span>
                            </div>
                            <div class="small text-dark fw-semibold" id="wfInboundDetailText">
                                Klik tombol konfirmasi di bawah untuk mensimulasikan penerimaan barang &amp; pembaruan saldo stok gudang.
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-info text-white btn-sm py-2 fw-semibold" onclick="simulateGoodsReceived()">
                            <i class="fas fa-truck-ramp-box me-1"></i> Konfirmasi Barang Diterima &amp; Update Stok
                        </button>
                        <a href="/purchasing/po" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-warehouse me-1"></i> Buka Halaman Penerimaan PO Aktual
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Node: Update Data AI -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
                <div class="card-header bg-white py-3">
                    <span class="badge bg-primary me-2">NODE 18</span>
                    <span class="fw-bold text-dark">Update Data AI (Learning Dataset)</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="small text-muted mb-3">
                            Seluruh parameter hasil siklus berjalan (baik dari jalur <em>Stok Aman</em> maupun setelah <em>Penerimaan PO</em>) diperbarui ke dalam basis data AI PPIC:
                        </p>
                        <ul class="list-group list-group-flush small mb-3" id="wfAiUpdateChecklist">
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-check-circle text-success me-2"></i> Aktualisasi Proyeksi Sales 12 Bulan</span>
                                <span class="badge bg-light text-dark border font-monospace">Tersinkron</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-check-circle text-success me-2"></i> Parameter Harga &amp; Lead Time Vendor</span>
                                <span class="badge bg-light text-dark border font-monospace" id="wfAiSyncVendorBadge">Siap Update</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-check-circle text-success me-2"></i> Saldo Persediaan &amp; Safety Stock Baru</span>
                                <span class="badge bg-light text-dark border font-monospace" id="wfAiSyncStockBadge">Siap Update</span>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm py-2 fw-semibold w-100 shadow-sm" onclick="simulateAiDatasetUpdate()">
                        <i class="fas fa-brain me-1"></i> Jalankan Update Data AI Sekarang
                    </button>
                </div>
            </div>
        </div>

        <!-- Node: Forecast Periode Berikutnya -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-success">
                <div class="card-header bg-white py-3">
                    <span class="badge bg-success me-2">NODE 19 &bull; SELESAI / SIKLUS BARU</span>
                    <span class="fw-bold text-dark">Forecast Periode Berikutnya</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="small text-muted mb-3">
                            Siklus perencanaan selesai. Data AI yang telah diperbarui menjadi baseline akurat untuk meramal kebutuhan sales, material, dan harga pasar pada periode berikutnya.
                        </p>
                        <div class="p-3 rounded-3 ai-recommendation-card mb-3">
                            <div class="fw-bold small text-dark mb-1">
                                <i class="fas fa-circle-check text-success me-1"></i> Status Siklus Flowchart:
                            </div>
                            <div class="small text-secondary" id="wfCycleCompletionText">
                                Siap melanjutkan ke siklus <strong>Forecast Periode Berikutnya</strong> dengan baseline stok dan evaluasi vendor terbaru.
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-sm py-2 fw-semibold shadow-sm" onclick="startNextForecastPeriod()">
                            <i class="fas fa-rotate me-1"></i> Mulai Forecast Periode Berikutnya (Siklus Baru)
                        </button>
                        <a href="/ai/forecast" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-chart-area me-1"></i> Lihat Dashboard AI Forecasting &amp; Harga Pasar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const WF_PRODUCTS = <?= json_encode($products_workflow) ?>;
const WF_MATERIALS = <?= json_encode($materials) ?>;
const WF_SUPPLIERS = <?= json_encode($suppliers) ?>;
const WF_MONTH_LABELS = <?= json_encode($month_labels) ?>;

let wfSalesChart = null;
let wfCurrentProductIndex = 0;
let wfForceHasBom = null; // null = ikuti data asli produk
let wfRdApproved = false;
let wfVendorPriority = 'balanced';
let wfSelectedVendorIndex = 0;
let wfRejectedVendorIds = [];

function activateWorkflowStage(stageNum) {
    const stageNames = {
        1: 'Tahap Aktif: 1. Input Forecast Sales 12 Bulan & Cek Finish Good',
        2: 'Tahap Aktif: 2. Mengecek Formula Material (AI & Evaluasi R&D)',
        3: 'Tahap Aktif: 3. Hitung Kebutuhan Material 12 Bulan & Safety Stock',
        4: 'Tahap Aktif: 4. Purchasing Evaluasi Vendor & AI Rekomendasi Vendor',
        5: 'Tahap Aktif: 5. Barang Diterima, Update Data AI & Periode Berikutnya'
    };

    document.querySelectorAll('.workflow-stage-pane').forEach((el, idx) => {
        el.classList.toggle('d-none', (idx + 1) !== stageNum);
    });

    for (let i = 1; i <= 5; i++) {
        const btn = document.getElementById('tabBtnStage' + i);
        const node = document.getElementById('flowNode' + i);
        if (btn) {
            btn.classList.toggle('active', i === stageNum);
        }
        if (node) {
            node.classList.toggle('border-primary', i === stageNum);
            node.classList.toggle('border-2', i === stageNum);
            node.classList.toggle('border-secondary', i !== stageNum);
        }
    }

    const badge = document.getElementById('activeFlowStageBadge');
    if (badge && stageNames[stageNum]) {
        badge.textContent = stageNames[stageNum];
    }

    if (stageNum === 4) {
        renderVendorEvaluation();
    }
}

function onSelectWorkflowProduct() {
    const sel = document.getElementById('wfProductSelect');
    wfCurrentProductIndex = sel ? parseInt(sel.value, 10) || 0 : 0;
    wfForceHasBom = null;
    wfRdApproved = false;

    const prod = WF_PRODUCTS[wfCurrentProductIndex] || {
        id: 0,
        code: 'BELUM-ADA',
        name: 'Belum Ada Produk Jadi (Input via Master Finish Good)',
        unit: 'Kg',
        standard_batch_size: 5000,
        fg_stock: 0,
        fg_safety_buffer: 0,
        has_bom: false,
        bom: null,
        monthly_forecast: [0,0,0,0,0,0,0,0,0,0,0,0]
    };

    document.getElementById('wfBatchSizeDisplay').value = Number(prod.standard_batch_size).toLocaleString('id-ID');
    document.getElementById('wfUnitDisplay').textContent = prod.unit;
    document.getElementById('wfFgStockInput').value = prod.fg_stock;
    document.getElementById('wfFgBufferInput').value = prod.fg_safety_buffer;

    const inputs = document.querySelectorAll('.wf-month-input');
    inputs.forEach((inp, idx) => {
        inp.value = prod.monthly_forecast[idx] ?? 0;
    });

    recalculateWorkflow();
}

function applySalesPreset(type) {
    const prod = WF_PRODUCTS[wfCurrentProductIndex];
    if (!prod) return;
    const base = prod.monthly_forecast[0] || 20000;
    const inputs = document.querySelectorAll('.wf-month-input');

    inputs.forEach((inp, idx) => {
        let val = base;
        if (type === 'seasonal') {
            const mult = [0.95, 0.95, 1.00, 1.05, 1.15, 1.25, 1.25, 1.15, 1.05, 1.00, 1.10, 1.15][idx] || 1.0;
            val = Math.round((base * mult) / 500) * 500;
        } else if (type === 'growth') {
            val = Math.round((base * (1 + idx * 0.025)) / 500) * 500;
        } else {
            val = Math.round(base / 500) * 500;
        }
        inp.value = val;
    });

    recalculateWorkflow();
}

function forceBomBranch(hasBom) {
    wfForceHasBom = hasBom;
    if (!hasBom) {
        wfRdApproved = false;
    }
    recalculateWorkflow();
}

function generateAiNewFormula() {
    const prod = WF_PRODUCTS[wfCurrentProductIndex];
    const draft = (prod && prod.ai_draft_bom) ? prod.ai_draft_bom : {
        version: 'v1.0-AI-DRAFT',
        batch_size: prod ? prod.standard_batch_size : 5000,
        unit: prod ? prod.unit : 'Kg',
        yield_pct: 98.5,
        reaction_note: 'Formulasi hasil sintesis AI PPIC: Reaksi dijalankan pada suhu 85°C–90°C selama 90 menit hingga larutan homogen.',
        items: [
            { material_id: WF_MATERIALS[0]?.id || 1, material_code: WF_MATERIALS[0]?.code || 'BB-001', material_name: WF_MATERIALS[0]?.name || 'Bahan Utama A', quantity_per_batch: 1500, unit: 'Kg' },
            { material_id: WF_MATERIALS[1]?.id || 2, material_code: WF_MATERIALS[1]?.code || 'BB-002', material_name: WF_MATERIALS[1]?.name || 'Bahan Reaktan B', quantity_per_batch: 2200, unit: 'Kg' },
            { material_id: WF_MATERIALS[5]?.id || 6, material_code: WF_MATERIALS[5]?.code || 'BB-006', material_name: WF_MATERIALS[5]?.name || 'Aditif Stabilizer C', quantity_per_batch: 500, unit: 'Kg' }
        ]
    };

    document.getElementById('wfAiDraftPlaceholder').classList.add('d-none');
    document.getElementById('wfAiDraftContent').classList.remove('d-none');

    const tbody = document.getElementById('wfAiDraftTableBody');
    tbody.innerHTML = draft.items.map(it => `
        <tr>
            <td><strong>${it.material_name}</strong> <span class="text-muted font-monospace">(${it.material_code})</span></td>
            <td class="text-end font-monospace fw-bold text-primary">${Number(it.quantity_per_batch).toLocaleString('id-ID')} ${it.unit}</td>
        </tr>
    `).join('');

    document.getElementById('wfAiDraftReactionNote').innerHTML = `<i class="fas fa-flask text-primary me-1"></i> <strong>Parameter Reaksi AI:</strong> ${draft.reaction_note}`;
    document.getElementById('wfRdFlowStatusBadge').textContent = 'Draft AI Siap Dievaluasi R&D';
    document.getElementById('wfRdApprovalBadge').className = 'badge bg-warning text-dark';
    document.getElementById('wfRdApprovalBadge').textContent = 'Menunggu Approval R&D';
    document.getElementById('btnRdApproveFormula').disabled = false;
}

function approveRdFormula() {
    wfRdApproved = true;
    document.getElementById('wfRdApprovalBadge').className = 'badge bg-success';
    document.getElementById('wfRdApprovalBadge').textContent = 'Disetujui R&D';
    document.getElementById('wfRdFlowStatusBadge').className = 'badge bg-success text-white px-3 py-2 font-monospace';
    document.getElementById('wfRdFlowStatusBadge').textContent = 'Formula Baru Disetujui R&D';
    document.getElementById('flowStatus2').textContent = 'Formula AI Disetujui R&D';
    recalculateWorkflow();
    activateWorkflowStage(3);
}

function recalculateWorkflow() {
    const prod = WF_PRODUCTS[wfCurrentProductIndex] || {
        id: 0,
        code: '-',
        name: 'Belum Ada Produk Jadi',
        unit: 'Kg',
        standard_batch_size: 5000,
        fg_stock: 0,
        fg_safety_buffer: 0,
        has_bom: false,
        bom: null
    };

    const monthlyValues = Array.from(document.querySelectorAll('.wf-month-input')).map(inp => Math.max(0, parseFloat(inp.value) || 0));
    const totalSales12M = monthlyValues.reduce((a, b) => a + b, 0);
    const fgStock = Math.max(0, parseFloat(document.getElementById('wfFgStockInput').value) || 0);
    const fgBuffer = Math.max(0, parseFloat(document.getElementById('wfFgBufferInput').value) || 0);
    const batchSize = Math.max(1, parseFloat(prod.standard_batch_size) || 5000);

    const netProduction = Math.max(0, totalSales12M + fgBuffer - fgStock);
    const requiredBatches = Math.ceil(netProduction / batchSize);

    document.getElementById('wfTotalSales12M').textContent = totalSales12M.toLocaleString('id-ID') + ' ' + prod.unit;
    document.getElementById('wfFgBufferLabel').textContent = fgBuffer.toLocaleString('id-ID') + ' ' + prod.unit;
    document.getElementById('wfFgStockLabel').textContent = fgStock.toLocaleString('id-ID') + ' ' + prod.unit;
    document.getElementById('wfNetProduction12M').textContent = netProduction.toLocaleString('id-ID') + ' ' + prod.unit;
    document.getElementById('wfRequiredBatches12M').textContent = requiredBatches.toLocaleString('id-ID') + ' Batch';
    document.getElementById('wfChartSummaryBadge').textContent = 'Total Sales 12 Bln: ' + totalSales12M.toLocaleString('id-ID') + ' ' + prod.unit;
    document.getElementById('flowStatus1').textContent = requiredBatches + ' Batch (' + totalSales12M.toLocaleString('id-ID') + ' ' + prod.unit + ')';

    // Update Chart Tahap 1
    updateSales12MChart(monthlyValues, fgStock, prod.unit);

    // Tahap 2: Cek Formula Material (Ada vs Tidak Ada)
    const effectiveHasBom = (wfForceHasBom !== null) ? wfForceHasBom : (prod.has_bom || wfRdApproved);
    const bomBadge = document.getElementById('wfBomBadge');
    if (effectiveHasBom) {
        bomBadge.className = 'badge bg-success-subtle text-success border w-100 py-2';
        bomBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Formula Ada';
        document.getElementById('wfBranchFormulaExists').classList.remove('d-none');
        document.getElementById('wfBranchFormulaMissing').classList.add('d-none');
        document.getElementById('flowStatus2').textContent = wfRdApproved ? 'Formula AI Disetujui R&D' : 'Formula BOM Tersedia';
    } else {
        bomBadge.className = 'badge bg-warning-subtle text-warning-emphasis border w-100 py-2';
        bomBadge.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Belum Ada BOM';
        document.getElementById('wfBranchFormulaExists').classList.add('d-none');
        document.getElementById('wfBranchFormulaMissing').classList.remove('d-none');
        document.getElementById('flowStatus2').textContent = 'Tidak Ada -> AI Buat Formula';
    }

    document.getElementById('wfFormulaProdName1').textContent = prod.name + ' (' + prod.code + ')';
    document.getElementById('wfFormulaProdName2').textContent = prod.name + ' (' + prod.code + ')';

    const activeBom = prod.bom || prod.ai_draft_bom || {
        version: 'v1.0-AI',
        items: [
            { material_id: WF_MATERIALS[0]?.id || 1, material_code: WF_MATERIALS[0]?.code || 'BB-001', material_name: WF_MATERIALS[0]?.name || 'Bahan Baku 1', quantity_per_batch: 1500, material_unit: 'Kg' },
            { material_id: WF_MATERIALS[1]?.id || 2, material_code: WF_MATERIALS[1]?.code || 'BB-002', material_name: WF_MATERIALS[1]?.name || 'Bahan Baku 2', quantity_per_batch: 2000, material_unit: 'Kg' }
        ]
    };

    document.getElementById('wfActiveBomVersionBadge').textContent = 'BOM ' + (activeBom.version || 'v1.0');

    const bomItems = activeBom.items || [];
    const bomTbody = document.getElementById('wfBomItemsTableBody');
    bomTbody.innerHTML = bomItems.map(item => {
        const qtyPerBatch = parseFloat(item.quantity_per_batch) || 0;
        const total12M = qtyPerBatch * requiredBatches;
        const u = item.material_unit || item.unit || 'Kg';
        return `
            <tr>
                <td class="font-monospace text-muted">${item.material_code || '-'}</td>
                <td class="fw-semibold text-dark">${item.material_name}</td>
                <td class="text-end font-monospace">${qtyPerBatch.toLocaleString('id-ID')} ${u}</td>
                <td class="text-end font-monospace fw-bold text-primary">${total12M.toLocaleString('id-ID')} ${u}</td>
            </tr>
        `;
    }).join('');

    document.getElementById('wfAiSalesMaterialNarrative').innerHTML =
        `Berdasarkan target penjualan 12 bulan untuk <strong>${prod.name}</strong> sebesar <strong>${totalSales12M.toLocaleString('id-ID')} ${prod.unit}</strong> ` +
        `dan stok Finish Good gudang <strong>${fgStock.toLocaleString('id-ID')} ${prod.unit}</strong>, pabrik memerlukan produksi bersih <strong>${netProduction.toLocaleString('id-ID')} ${prod.unit}</strong> ` +
        `(setara <strong>${requiredBatches} batch</strong> @ ${batchSize.toLocaleString('id-ID')} ${prod.unit}).<br><br>` +
        `AI merekomendasikan penguncian pasokan kontrak bertahap untuk ${bomItems.length} komponen material utama guna mengantisipasi puncak permintaan kuartal II–III.`;

    // Tahap 3: Hitung Kebutuhan Material 12 Bulan & Analisis Minimum Stock + Safety Stock
    renderMrp12MTable(bomItems, requiredBatches);
}

function renderMrp12MTable(bomItems, requiredBatches) {
    if (!WF_MATERIALS || WF_MATERIALS.length === 0) {
        document.getElementById('wfMrp12MTableBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Belum ada data Master Material. <a href="/master/materials" class="fw-semibold">Tambah Master Material &amp; Safety Stock &rarr;</a>
                </td>
            </tr>
        `;
        document.getElementById('wfSafeMatCountBadge').textContent = '0 Bahan';
        document.getElementById('wfDeficitMatCountBadge').textContent = '0 Bahan';
        document.getElementById('wfMrpDeficitSummaryBadge').textContent = 'Belum Ada Data Material';
        return;
    }
    const reqByMatId = {};
    bomItems.forEach(it => {
        const mid = parseInt(it.material_id, 10);
        reqByMatId[mid] = (parseFloat(it.quantity_per_batch) || 0) * requiredBatches;
    });

    let safeCount = 0;
    let deficitCount = 0;
    let firstDeficitMat = null;

    const rowsHtml = WF_MATERIALS.map(m => {
        const mid = parseInt(m.id, 10);
        // Jika bahan termasuk dalam BOM produk terpilih, gunakan hasil eksplosi 12 bulan; jika tidak, tampilkan proyeksi tahunan reguler
        const isDirectBom = reqByMatId[mid] !== undefined;
        const req12M = isDirectBom ? reqByMatId[mid] : Math.round((parseFloat(m.reorder_point) || 10000) * 3.5);
        const minStock = parseFloat(m.min_stock) || 0;
        const safetyStock = parseFloat(m.safety_stock) || 0;
        const availStock = parseFloat(m.available_stock) || 0;
        const incomingPo = parseFloat(m.incoming_po_stock) || 0;
        const totalCover = availStock + incomingPo;
        const moq = parseFloat(m.moq) || 1000;

        // Untuk evaluasi bulanan/kuartal terdekat + safety stock vs stok saat ini
        const immediateHorizonReq = Math.round(req12M / 4) + safetyStock;
        const deficit = Math.max(0, immediateHorizonReq - totalCover);
        const isSufficient = deficit <= 0 && availStock > minStock;
        const recommendedPrQty = deficit > 0 ? Math.max(moq, Math.ceil(deficit / 500) * 500) : 0;

        if (isSufficient) {
            safeCount++;
        } else {
            deficitCount++;
            if (!firstDeficitMat || isDirectBom) {
                firstDeficitMat = { id: mid, qty: recommendedPrQty };
            }
        }

        return `
            <tr class="${isDirectBom ? 'table-primary bg-opacity-10' : ''}">
                <td>
                    <div class="fw-semibold text-dark">
                        ${m.name}
                        ${isDirectBom ? '<span class="badge bg-primary-subtle text-primary border ms-1" style="font-size:10px;">BOM Produk Ini</span>' : ''}
                    </div>
                    <small class="text-muted font-monospace">${m.code}</small>
                </td>
                <td class="text-end font-monospace fw-bold text-dark">
                    ${req12M.toLocaleString('id-ID')} ${m.unit}
                    <div class="text-muted" style="font-size:10px;">Q1+Safety: ${immediateHorizonReq.toLocaleString('id-ID')}</div>
                </td>
                <td class="text-end font-monospace text-muted">
                    Min: ${minStock.toLocaleString('id-ID')}<br>
                    Safety: <strong>${safetyStock.toLocaleString('id-ID')}</strong>
                </td>
                <td class="text-end font-monospace ${availStock <= safetyStock ? 'text-danger fw-bold' : 'text-success fw-bold'}">
                    ${totalCover.toLocaleString('id-ID')} ${m.unit}
                </td>
                <td class="text-center">
                    ${isSufficient
                        ? '<span class="badge bg-success-subtle text-success border"><i class="fas fa-check me-1"></i> Ya (Aman)</span>'
                        : '<span class="badge bg-danger-subtle text-danger border"><i class="fas fa-xmark me-1"></i> Tidak (Kurang)</span>'}
                </td>
                <td class="text-end font-monospace ${deficit > 0 ? 'text-danger fw-bold' : 'text-muted'}">
                    ${deficit > 0 ? deficit.toLocaleString('id-ID') + ' ' + m.unit : '-'}
                </td>
                <td class="text-center">
                    ${recommendedPrQty > 0
                        ? `<button type="button" class="btn btn-primary btn-sm py-0 px-2" onclick="jumpToVendorEval(${mid}, ${recommendedPrQty})">
                               <i class="fas fa-handshake me-1"></i> PR ${recommendedPrQty.toLocaleString('id-ID')} &rarr; Vendor
                           </button>`
                        : '<span class="text-muted small"><i class="fas fa-check text-success"></i> Stok Cukup</span>'}
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('wfMrp12MTableBody').innerHTML = rowsHtml;
    document.getElementById('wfSafeMatCountBadge').textContent = safeCount + ' Bahan Aman';
    document.getElementById('wfDeficitMatCountBadge').textContent = deficitCount + ' Bahan Defisit';
    document.getElementById('wfMrpDeficitSummaryBadge').textContent = `${deficitCount} Perlu PR / ${safeCount} Stok Aman`;
    document.getElementById('flowStatus3').textContent = `${deficitCount} Defisit -> Rekomendasi PR`;
}

function jumpToVendorEval(materialId, prQty) {
    const sel = document.getElementById('wfVendorMaterialSelect');
    const qtyInput = document.getElementById('wfVendorPrQty');
    if (sel) sel.value = String(materialId);
    if (qtyInput && prQty > 0) qtyInput.value = prQty;
    wfRejectedVendorIds = [];
    wfSelectedVendorIndex = 0;
    activateWorkflowStage(4);
}

function setVendorPriority(mode) {
    wfVendorPriority = mode;
    ['Balanced', 'Price', 'Lead', 'Quality'].forEach(k => {
        const btn = document.getElementById('btnWeight' + k);
        if (btn) btn.classList.remove('active');
    });
    const map = { balanced: 'Balanced', price: 'Price', leadtime: 'Lead', quality: 'Quality' };
    const activeBtn = document.getElementById('btnWeight' + (map[mode] || 'Balanced'));
    if (activeBtn) activeBtn.classList.add('active');
    wfSelectedVendorIndex = 0;
    renderVendorEvaluation();
}

function renderVendorEvaluation() {
    const tbody = document.getElementById('wfVendorComparisonBody');
    if (!WF_SUPPLIERS || WF_SUPPLIERS.length === 0) {
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        Belum ada data Vendor terdaftar. <a href="/master/suppliers" class="fw-semibold">Tambah Data Vendor (Harga, Lead Time, Kualitas, MOQ) &rarr;</a>
                    </td>
                </tr>
            `;
        }
        document.getElementById('wfRecommendedVendorName').textContent = 'Belum Ada Vendor';
        document.getElementById('wfRecommendedVendorBadges').innerHTML = '';
        document.getElementById('wfRecommendedVendorReason').innerHTML = 'Silakan tambahkan data Vendor terlebih dahulu pada menu Purchasing &rarr; Data Vendor.';
        return;
    }
    const sel = document.getElementById('wfVendorMaterialSelect');
    if (!sel || !sel.selectedOptions[0]) return;
    const opt = sel.selectedOptions[0];
    const baseCost = parseFloat(opt.dataset.cost) || 4500;
    const baseMoq = parseFloat(opt.dataset.moq) || 5000;
    const unit = opt.dataset.unit || 'Kg';
    const matName = opt.dataset.name || 'Bahan Baku';
    const matId = parseInt(sel.value, 10) || 1;
    const prQty = Math.max(100, parseFloat(document.getElementById('wfVendorPrQty').value) || 10000);

    document.getElementById('wfVendorPrUnit').textContent = unit;

    // Bangun kandidat perbandingan vendor berdasarkan data supplier aktual
    const priceMultipliers = [0.98, 1.00, 0.95, 1.03, 0.97];
    const moqMultipliers = [1.0, 0.8, 1.2, 1.0, 1.5];

    let candidates = WF_SUPPLIERS.map((s, idx) => {
        const sid = parseInt(s.id, 10) || (idx + 1);
        const pMult = priceMultipliers[(idx + matId) % priceMultipliers.length];
        const mMult = moqMultipliers[(idx + matId) % moqMultipliers.length];
        const unitPrice = Math.round((baseCost * pMult) / 10) * 10;
        const leadDays = parseInt(s.lead_time_days, 10) || 5;
        const rating = parseFloat(s.rating) || 4.7;
        const vendorMoq = Math.max(500, Math.round((baseMoq * mMult) / 500) * 500);
        const effectiveQty = Math.max(prQty, vendorMoq);
        const totalPoCost = effectiveQty * unitPrice;

        // Skor per dimensi (0-100)
        const priceScore = Math.max(60, Math.min(99, Math.round(100 - ((unitPrice - baseCost * 0.94) / (baseCost * 0.15)) * 25)));
        const leadScore = Math.max(60, Math.min(99, Math.round(100 - (leadDays - 2) * 5.5)));
        const qualityScore = Math.round((rating / 5.0) * 100);
        const moqScore = prQty >= vendorMoq ? 96 : 78;

        let totalScore = 0;
        if (wfVendorPriority === 'price') {
            totalScore = Math.round(priceScore * 0.55 + qualityScore * 0.20 + leadScore * 0.15 + moqScore * 0.10);
        } else if (wfVendorPriority === 'leadtime') {
            totalScore = Math.round(leadScore * 0.55 + qualityScore * 0.20 + priceScore * 0.15 + moqScore * 0.10);
        } else if (wfVendorPriority === 'quality') {
            totalScore = Math.round(qualityScore * 0.55 + leadScore * 0.20 + priceScore * 0.15 + moqScore * 0.10);
        } else {
            totalScore = Math.round(priceScore * 0.30 + leadScore * 0.30 + qualityScore * 0.25 + moqScore * 0.15);
        }

        const isRejected = wfRejectedVendorIds.includes(sid);

        return {
            id: sid,
            code: s.code,
            name: s.name,
            unitPrice,
            leadDays,
            rating,
            vendorMoq,
            effectiveQty,
            totalPoCost,
            totalScore: isRejected ? (totalScore - 40) : totalScore,
            rawScore: totalScore,
            isRejected
        };
    });

    candidates.sort((a, b) => b.totalScore - a.totalScore);
    if (wfSelectedVendorIndex >= candidates.length) wfSelectedVendorIndex = 0;

    const chosen = candidates[wfSelectedVendorIndex] || candidates[0];

    const tbody = document.getElementById('wfVendorComparisonBody');
    tbody.innerHTML = candidates.map((c, idx) => {
        const isSelected = idx === wfSelectedVendorIndex;
        const isTopAi = idx === 0 && !c.isRejected;
        return `
            <tr class="${isSelected ? 'table-primary' : (c.isRejected ? 'table-light text-muted opacity-75' : '')}">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge ${isTopAi ? 'ai-badge' : 'bg-secondary-subtle text-dark border'}">#${idx + 1}</span>
                        <div>
                            <div class="fw-bold text-dark">
                                ${c.name}
                                ${isTopAi ? '<span class="badge bg-success-subtle text-success border ms-1" style="font-size:10px;"><i class="fas fa-sparkles me-1"></i>Rekomendasi AI</span>' : ''}
                                ${c.isRejected ? '<span class="badge bg-danger-subtle text-danger border ms-1" style="font-size:10px;">Ditolak (Evaluasi Ulang)</span>' : ''}
                            </div>
                            <small class="text-muted font-monospace">${c.code}</small>
                        </div>
                    </div>
                </td>
                <td class="text-end font-monospace fw-semibold">Rp ${c.unitPrice.toLocaleString('id-ID')}</td>
                <td class="text-center"><span class="badge bg-light text-dark border font-monospace">${c.leadDays} Hari</span></td>
                <td class="text-center">
                    <span class="badge bg-warning-subtle text-dark border">
                        <i class="fas fa-star text-warning me-1"></i>${c.rating.toFixed(1)} / 5.0
                    </span>
                </td>
                <td class="text-end font-monospace">${c.vendorMoq.toLocaleString('id-ID')} ${unit}</td>
                <td class="text-end font-monospace fw-bold text-dark">Rp ${c.totalPoCost.toLocaleString('id-ID')}</td>
                <td class="text-center">
                    <span class="badge ${c.rawScore >= 90 ? 'bg-success' : 'bg-primary'} font-monospace">${c.rawScore}/100</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm ${isSelected ? 'btn-primary' : 'btn-outline-secondary'} py-0 px-2" onclick="selectVendorCandidate(${idx})">
                        ${isSelected ? '<i class="fas fa-check"></i> Dipilih' : 'Pilih'}
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    if (chosen) {
        document.getElementById('wfRecommendedVendorName').textContent = chosen.name;
        document.getElementById('wfRecommendedVendorBadges').innerHTML = `
            <span class="badge bg-white text-dark border font-monospace">Harga: Rp ${chosen.unitPrice.toLocaleString('id-ID')}/${unit}</span>
            <span class="badge bg-white text-dark border font-monospace">Lead Time: ${chosen.leadDays} Hari</span>
            <span class="badge bg-white text-dark border font-monospace">Kualitas: ${chosen.rating}/5.0</span>
            <span class="badge bg-white text-dark border font-monospace">MOQ: ${chosen.vendorMoq.toLocaleString('id-ID')} ${unit}</span>
        `;
        document.getElementById('wfRecommendedVendorReason').innerHTML =
            `AI merekomendasikan <strong>${chosen.name}</strong> untuk pengadaan <strong>${chosen.effectiveQty.toLocaleString('id-ID')} ${unit} ${matName}</strong> ` +
            `dengan skor evaluasi tertinggi (<strong>${chosen.rawScore}/100</strong>). Estimasi nilai kontrak PO sebesar <strong>Rp ${chosen.totalPoCost.toLocaleString('id-ID')}</strong> ` +
            `dengan jaminan pengiriman ${chosen.leadDays} hari kerja dan histori COA lulus uji QC.`;

        document.getElementById('wfRealPrActionLink').href = `/purchasing/pr/create-from-ai?material_id=${matId}&qty=${chosen.effectiveQty}`;
    }
}

function selectVendorCandidate(idx) {
    wfSelectedVendorIndex = idx;
    renderVendorEvaluation();
}

function rejectVendorAndReevaluate() {
    const sel = document.getElementById('wfVendorMaterialSelect');
    const tbody = document.getElementById('wfVendorComparisonBody');
    if (!sel) return;

    // Tandai vendor terpilih saat ini sebagai ditolak, lalu putar kembali ke kandidat berikutnya (sesuai panah "Vendor Disetujui? -> Tidak -> Purchasing Evaluasi Vendor")
    const currentVendorName = document.getElementById('wfRecommendedVendorName').textContent;
    const matched = WF_SUPPLIERS.find(s => s.name === currentVendorName);
    if (matched) {
        const sid = parseInt(matched.id, 10);
        if (!wfRejectedVendorIds.includes(sid)) {
            wfRejectedVendorIds.push(sid);
        }
        if (wfRejectedVendorIds.length >= WF_SUPPLIERS.length) {
            wfRejectedVendorIds = [];
        }
    }
    wfSelectedVendorIndex = 0;
    document.getElementById('wfPoIssuedNotificationBox').classList.add('d-none');
    document.getElementById('flowStatus4').textContent = 'Evaluasi Ulang Vendor';
    renderVendorEvaluation();
}

function approveVendorAndIssuePo() {
    const vendorName = document.getElementById('wfRecommendedVendorName').textContent;
    const sel = document.getElementById('wfVendorMaterialSelect');
    const opt = sel ? sel.selectedOptions[0] : null;
    const matName = opt ? opt.dataset.name : 'Bahan Baku';
    const unit = opt ? opt.dataset.unit : 'Kg';
    const qty = Math.max(100, parseFloat(document.getElementById('wfVendorPrQty').value) || 10000);
    const poNumber = 'PO-' + new Date().toISOString().slice(0, 7).replace('-', '') + '-AI01';

    const box = document.getElementById('wfPoIssuedNotificationBox');
    const txt = document.getElementById('wfPoIssuedNotificationText');
    box.classList.remove('d-none');
    txt.innerHTML =
        `Dokumen <strong>${poNumber}</strong> resmi diterbitkan ke <strong>${vendorName}</strong> untuk <strong>${qty.toLocaleString('id-ID')} ${unit} ${matName}</strong>.<br>` +
        `<span class="badge bg-success-subtle text-success border mt-1"><i class="fas fa-paper-plane me-1"></i> Notifikasi otomatis telah dikirimkan ke Divisi PPIC bahwa bahan sudah PO!</span>`;

    document.getElementById('flowStatus4').textContent = 'PO Terbit + Notif PPIC';
    document.getElementById('wfAiSyncVendorBadge').className = 'badge bg-success-subtle text-success border font-monospace';
    document.getElementById('wfAiSyncVendorBadge').textContent = 'PO ' + poNumber + ' Tercatat';
}

function simulateGoodsReceived() {
    const badge = document.getElementById('wfInboundBadge');
    const detail = document.getElementById('wfInboundDetailText');
    badge.className = 'badge bg-success';
    badge.textContent = 'Barang Diterima & Stok Bertambah';
    detail.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Fisik barang telah diterima di Gudang PT Dunia Kimia Jaya Gresik. Saldo stok bahan baku & batch QC berhasil diperbarui.';
    document.getElementById('wfAiSyncStockBadge').className = 'badge bg-success-subtle text-success border font-monospace';
    document.getElementById('wfAiSyncStockBadge').textContent = 'Stok Gudang Terupdate';
    document.getElementById('flowStatus5').textContent = 'Stok & AI Terupdate';
}

function simulateAiDatasetUpdate() {
    document.getElementById('wfAiSyncVendorBadge').className = 'badge bg-primary text-white font-monospace';
    document.getElementById('wfAiSyncVendorBadge').textContent = 'AI Dataset Updated';
    document.getElementById('wfAiSyncStockBadge').className = 'badge bg-primary text-white font-monospace';
    document.getElementById('wfAiSyncStockBadge').textContent = 'AI Dataset Updated';
    document.getElementById('wfCycleCompletionText').innerHTML =
        '<strong><i class="fas fa-sparkles text-primary me-1"></i> Update Data AI Selesai!</strong> Model AI PPIC kini telah mempelajari realisasi forecast 12 bulan, evaluasi harga/lead time vendor terbaru, dan level stok terkini.';
    document.getElementById('flowStatus5').textContent = 'Siap Periode Berikutnya';
}

function startNextForecastPeriod() {
    activateWorkflowStage(1);
    applySalesPreset('growth');
}

function updateSales12MChart(monthlyValues, fgStock, unit) {
    const ctx = document.getElementById('wfSales12MChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const fgLine = new Array(monthlyValues.length).fill(fgStock);

    if (wfSalesChart) {
        wfSalesChart.data.datasets[0].data = monthlyValues;
        wfSalesChart.data.datasets[0].label = 'Forecast Sales Bulanan (' + unit + ')';
        wfSalesChart.data.datasets[1].data = fgLine;
        wfSalesChart.update();
        return;
    }

    wfSalesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: WF_MONTH_LABELS,
            datasets: [
                {
                    type: 'bar',
                    label: 'Forecast Sales Bulanan (' + unit + ')',
                    data: monthlyValues,
                    backgroundColor: 'rgba(37, 99, 235, 0.78)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    type: 'line',
                    label: 'Stok Finish Good Saat Ini',
                    data: fgLine,
                    borderColor: '#16a34a',
                    backgroundColor: '#16a34a',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (v) => Number(v).toLocaleString('id-ID'),
                        font: { size: 10 }
                    }
                },
                x: {
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    onSelectWorkflowProduct();
    renderVendorEvaluation();
});
</script>

@include('layouts.footer')
