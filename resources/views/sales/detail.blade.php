@php
$record = $record ?? [];
$isForecast = ($source ?? '') === 'forecast';
$pageTitle = $isForecast ? 'Detail Forecast Sales' : 'Detail Dokumen Sales';
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
@endphp
@include('layouts.header')

<div class="container-fluid py-3">
    <a href="/forecast-sales?section=needs" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Sales &amp; Forecast
    </a>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">
                <i class="<?= $isForecast ? 'fas fa-chart-line text-primary' : 'fas fa-file-invoice text-primary' ?> me-2"></i>
                <?= $isForecast ? 'Detail Forecast' : 'Detail Dokumen Sales' ?>
            </h5>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <?php if ($isForecast): ?>
                    <div class="col-12 col-md-6"><div class="text-muted small">Nomor material</div><div class="fw-semibold"><?= $escape($record['product_code'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Periode</div><div class="fw-semibold"><?= $escape($record['period'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Model</div><div class="fw-semibold"><?= $escape($record['method'] ?? '') ?></div></div>
                    <div class="col-12"><div class="text-muted small">Catatan</div><div><?= nl2br($escape($record['notes'] ?: 'Tidak ada catatan.')) ?></div></div>
                <?php else: ?>
                    <div class="col-12 col-md-6"><div class="text-muted small">Dokumen</div><div class="fw-semibold"><?= $escape($record['doc_number'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Jenis</div><div class="fw-semibold"><?= ($record['doc_type'] ?? '') === 'sales_order' ? 'Sales Order (SO)' : 'Quotation' ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Nomor material</div><div class="fw-semibold"><?= $escape($record['product_code'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Periode</div><div class="fw-semibold"><?= $escape($record['sale_month'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Pelanggan</div><div class="fw-semibold"><?= $escape($record['customer_name'] ?? '') ?></div></div>
                    <div class="col-12 col-md-6"><div class="text-muted small">Total nilai</div><div class="fw-semibold">Rp <?= number_format((float) ($record['total_amount'] ?? 0), 0, ',', '.') ?></div></div>
                    <div class="col-12"><div class="text-muted small">Catatan</div><div><?= nl2br($escape($record['notes'] ?: 'Tidak ada catatan.')) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($isForecast): ?>
        <?php $evaluationClass = $evaluation['evaluation'] === 'Overforecast' ? 'danger' : ($evaluation['evaluation'] === 'Underforecast' ? 'warning text-dark' : 'success'); ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-scale-balanced text-primary me-2"></i>Evaluasi Forecast</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-6 col-lg"><div class="text-muted small">Jumlah Forecast</div><div class="fw-semibold"><?= number_format((float) $evaluation['forecast_quantity'], 0, ',', '.') ?> <?= $escape($evaluation['product_unit']) ?></div></div>
                    <div class="col-6 col-lg"><div class="text-muted small">Jumlah Realisasi</div><div class="fw-semibold"><?= number_format((float) $evaluation['realization_quantity'], 0, ',', '.') ?> <?= $escape($evaluation['product_unit']) ?></div></div>
                    <div class="col-6 col-lg"><div class="text-muted small">Selisih</div><div class="fw-semibold"><?= number_format((float) $evaluation['difference'], 0, ',', '.') ?> <?= $escape($evaluation['product_unit']) ?></div></div>
                    <div class="col-6 col-lg"><div class="text-muted small">Persentase Error</div><div class="fw-semibold"><?= number_format((float) $evaluation['error_percentage'], 2, ',', '.') ?>%</div></div>
                    <div class="col-12"><div class="text-muted small mb-1">Keterangan Evaluasi</div><span class="badge bg-<?= $evaluationClass ?>"><?= $escape($evaluation['evaluation']) ?></span></div>
                    <div class="col-12"><div class="text-muted small">AI Insight</div><div><?= $escape($evaluation['insight']) ?></div></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

@include('layouts.footer')
