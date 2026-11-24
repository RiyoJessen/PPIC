<?php
use App\Services\AccessControl;

$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!function_exists('isActive')) {
    function isActive(string $path, string $currentUri): string {
        if ($path === '/' && $currentUri === '/') return 'active';
        if ($path !== '/' && str_starts_with($currentUri, $path)) return 'active';
        return '';
    }
}
$isSuperAdmin = AccessControl::isSuperAdmin();
$currentRole = AccessControl::getCurrentRole();
?>
<div class="sidebar border-end bg-dark text-white" id="sidebar-wrapper">
    <!-- Brand Logo & Role Badge -->
    <div class="sidebar-heading px-4 py-3 border-bottom border-secondary">
        <div class="d-flex align-items-center justify-content-center mb-2">
            <img src="/images/dkj.png" alt="Logo PT Dunia Kimia Jaya" class="flex-shrink-0 rounded bg-white" style="width: 85px; height: 50px; object-fit: contain; transform: scale(1.25); transform-origin: center center;">

        </div>

        <!-- Role Access Indicator -->
        <div class="mt-2">
            <?php if ($isSuperAdmin): ?>
            <?php elseif ($currentRole === AccessControl::ROLE_PURCHASING): ?>
            <?php elseif ($currentRole === AccessControl::ROLE_RD): ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation List (Selaras 100% dengan Flowchart Terbaru) -->
    <div class="list-group list-group-flush py-2 flex-grow-1">
        <div class="sidebar-label px-4 pt-2 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 11px;">
            Utama
        </div>
        <a href="/" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/', $currentUri) ?>">
            <i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard Sistem
        </a>
        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_PPIC)): ?>
            <a href="/forecast-sales" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/forecast-sales', $currentUri) ?>">
                <i class="fas fa-chart-line me-2 text-success"></i>Sales &amp; Forecast
            </a>
        <?php endif; ?>

        <!-- Tahap Formula & Finish Good (R&D) -->
        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_RD)): ?>
            <div class="sidebar-label px-4 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 11px;">
                <i class="fas fa-flask-vial text-warning me-1"></i> Finish Good &amp; Formula (R&amp;D)
            </div>
            <a href="/master/products" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/products', $currentUri) ?>">
                <i class="fas fa-box-open me-2 text-info"></i>Finish Good (Produk)
            </a>
            <a href="/master/boms" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/boms', $currentUri) ?>">
                <i class="fas fa-sitemap me-2 text-warning"></i> Formula &amp; R&amp;D
            </a>
            <a href="/master/materials" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/materials', $currentUri) ?>">
                <i class="fas fa-flask me-2 text-primary"></i> Raw Material &amp; Safety Stock
            </a>
        <?php endif; ?>

        <!-- Tahap Analisis AI & Kebutuhan Material (PPIC) -->
        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_PPIC)): ?>
            <div class="sidebar-label px-4 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 11px;">
                <i class="fas fa-brain text-primary me-1"></i> AI Analisis &amp; Update Data AI
            </div>
            <a href="/ai/forecast" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/ai/forecast', $currentUri) ?>">
                <i class="fas fa-wand-magic-sparkles me-2 text-purple" style="color: #a855f7;"></i> AI Analisis Material &amp; Harga
            </a>
        <?php endif; ?>


        <!-- Pengaturan Sistem (Khusus PPIC / Super Admin) -->
        <?php if ($isSuperAdmin): ?>
            <div class="sidebar-label px-4 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 11px;">
                <i class="fas fa-cog text-secondary me-1"></i> Konfigurasi
            </div>
            <a href="/settings" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/settings', $currentUri) ?>">
                <i class="fas fa-sliders-h me-2 text-secondary"></i> Pengaturan AI &amp; Data
            </a>
        <?php endif; ?>
        <div class="py-3"></div>
    </div>
</div>
