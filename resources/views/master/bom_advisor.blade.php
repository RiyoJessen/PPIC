<?php
$pageTitle = 'AI Formulation Advisor — Asisten Formulasi R&D';
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="mb-4">
    <a href="/master/boms" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Formula BOM
    </a>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="fas fa-wand-magic-sparkles text-purple me-2" style="color: #9333ea;"></i> AI Formulation Advisor &amp; Substitusi Kimia
            </h3>
            <p class="text-muted small mb-0">
                Asisten kecerdasan buatan Gemini untuk tim R&amp;D: Menganalisis ketersediaan stok fisik gudang, mendeteksi bahan habis, dan menghitung rekomendasi formula kimia alternatif secara stoikiometrik.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-purple-subtle text-purple border border-purple px-3 py-2 font-monospace" style="color: #9333ea; border-color: #d8b4fe !important;">
                <i class="fas fa-brain me-1"></i> Powered by Google Gemini AI
            </span>
        </div>
    </div>
</div>

<!-- Product Selector Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/master/boms/ai-advisor" class="row g-3 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Pilih Produk Jadi Target Formulasi:</label>
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_product_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['code']) ?> &mdash; <?= htmlspecialchars($p['name']) ?> (Std: <?= number_format((float)$p['standard_batch_size'], 0, ',', '.') ?> <?= $p['unit'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Status Ketersediaan Bahan Formula:</label>
                <div>
                    <?php if ($has_shortage): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?= count($depleted_items) ?> Bahan Baku Kritis / Tidak Cukup
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i> Seluruh Bahan Baku Formula Standar Cukup
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button type="submit" class="btn btn-primary btn-sm px-3 shadow-xs">
                    <i class="fas fa-sync-alt me-1"></i> Muat Ulang Analisis
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Standard Formula vs Warehouse Stock -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-flask text-primary me-2"></i> Formula Standar Aktif</h6>
                    <small class="text-muted">Versi: <strong><?= htmlspecialchars($bom['version'] ?? 'v1.0') ?></strong> &bull; Batch: <?= number_format((float)($bom['batch_size'] ?? $product['standard_batch_size']), 0, ',', '.') ?> <?= $product['unit'] ?></small>
                </div>
                <span class="badge bg-light text-dark border">Standar DKJ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Bahan Baku</th>
                            <th class="text-end">Kebutuhan</th>
                            <th class="text-end">Stok Bebas</th>
                            <th class="text-center">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($standard_items)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada komposisi bahan baku standar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($standard_items as $si): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($si['material_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($si['material_code']) ?></small>
                                    </td>
                                    <td class="text-end font-monospace fw-semibold">
                                        <?= number_format((float)$si['quantity_per_batch'], 1, ',', '.') ?> <?= $si['unit'] ?>
                                    </td>
                                    <td class="text-end font-monospace <?= $si['is_shortage'] ? 'text-danger fw-bold' : 'text-success' ?>">
                                        <?= number_format((float)$si['available_stock'], 1, ',', '.') ?> <?= $si['unit'] ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($si['is_shortage']): ?>
                                            <span class="badge bg-danger text-white" title="Stok gudang tidak cukup untuk 1 batch penuh">
                                                <i class="fas fa-times-circle me-1"></i> Kurang
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border">
                                                <i class="fas fa-check me-1"></i> Cukup
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Est. Biaya Bahan Standar / Batch:</span>
                    <strong class="text-dark font-monospace fs-6">Rp <?= number_format((float)$standard_total_cost, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: AI Recommendations -->
    <div class="col-lg-7">
        <!-- AI Narrative Box -->
        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f8fafc 0%, #faf5ff 100%); border: 1px solid #e9d5ff !important;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple text-white p-2 rounded-circle" style="background-color: #9333ea;">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </span>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Rekomendasi Formulasi &amp; Substitusi Kimia AI Gemini</h6>
                        <small class="text-muted" style="font-size: 11px;"><?= $ai_analysis['source'] ?? 'AI Engine' ?></small>
                    </div>
                </div>
                <div class="small text-secondary lh-base mt-3" style="white-space: pre-line;">
                    <?= htmlspecialchars($ai_analysis['narrative'] ?? '') ?>
                </div>
            </div>
        </div>

        <!-- Options Tabs / Cards -->
        <h5 class="fw-bold text-dark mb-3">
            <i class="fas fa-lightbulb text-warning me-2"></i> Pilihan Formulasi Kimia yang Disarankan:
        </h5>

        <?php foreach ($ai_analysis['options'] as $idx => $opt): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace me-2">
                            <?= htmlspecialchars($opt['code']) ?>
                        </span>
                        <strong class="text-dark"><?= htmlspecialchars($opt['title']) ?></strong>
                    </div>
                    <div>
                        <?php if ($opt['cost_diff_pct'] > 0): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border">
                                +<?= $opt['cost_diff_pct'] ?>% Biaya
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border">
                                <?= $opt['cost_diff_pct'] ?>% Lebih Hemat
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-3 small">
                    <p class="text-muted mb-3"><?= htmlspecialchars($opt['strategy']) ?></p>

                    <!-- Table of Ingredients -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Komposisi Bahan Pengganti / Penyesuaian</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Est. Subtotal</th>
                                    <th>Catatan Kimia / Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($opt['items'] as $it): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($it['material_name']) ?></td>
                                        <td class="text-end font-monospace fw-bold text-primary">
                                            <?= number_format((float)$it['quantity_per_batch'], 1, ',', '.') ?> <?= $it['unit'] ?>
                                        </td>
                                        <td class="text-end font-monospace text-muted">
                                            Rp <?= number_format((float)$it['subtotal'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-muted" style="font-size: 11px;">
                                            <?= htmlspecialchars($it['status_note'] ?? '-') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Technical Parameters -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <div class="p-2 bg-light rounded border h-100">
                                <strong class="d-block text-dark mb-1" style="font-size: 11px;">
                                    <i class="fas fa-temperature-high text-danger me-1"></i> Penyesuaian Reaksi &amp; Operasional:
                                </strong>
                                <span class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($opt['reaction_parameters']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 bg-light rounded border h-100">
                                <strong class="d-block text-dark mb-1" style="font-size: 11px;">
                                    <i class="fas fa-certificate text-warning me-1"></i> Prediksi Kepatuhan Mutu (COA):
                                </strong>
                                <span class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($opt['coa_prediction']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pt-2 border-top gap-2">
                        <div>
                            <span class="text-muted">Estimasi Biaya Bahan / Batch:</span>
                            <strong class="text-dark font-monospace fs-6 ms-1">Rp <?= number_format((float)$opt['estimated_cost'], 0, ',', '.') ?></strong>
                        </div>
                        <form action="/master/boms/apply-ai-formulation" method="POST" onsubmit="return confirm('Terapkan dan publikasikan formula alternatif ini ke sistem produksi resmi?')">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="version" value="<?= htmlspecialchars($opt['version_code']) ?>">
                            <input type="hidden" name="batch_size" value="<?= $bom['batch_size'] ?? $product['standard_batch_size'] ?>">
                            <input type="hidden" name="unit" value="<?= htmlspecialchars($product['unit']) ?>">
                            <input type="hidden" name="yield_pct" value="<?= $bom['yield_pct'] ?? 98.0 ?>">
                            <input type="hidden" name="scrap_pct" value="<?= $bom['scrap_pct'] ?? 2.0 ?>">
                            <input type="hidden" name="notes" value="Rekomendasi AI Gemini: <?= htmlspecialchars($opt['title']) ?>. <?= htmlspecialchars($opt['reaction_parameters']) ?>">
                            <input type="hidden" name="items_json" value="<?= htmlspecialchars(json_encode($opt['items']), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-success btn-sm px-3 shadow-xs">
                                <i class="fas fa-check-circle me-1"></i> Setujui &amp; Terapkan Formula Ini
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

@include('layouts.footer')
