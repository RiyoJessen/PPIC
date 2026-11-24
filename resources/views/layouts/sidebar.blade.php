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
    <div class="sidebar-heading px-3 border-bottom border-secondary">
        <div class="d-flex align-items-center gap-3">
            <img src="/images/dkj.png" alt="Logo PT Dunia Kimia Jaya" class="sidebar-brand-mark">
            <div class="sidebar-brand-copy">
                <div class="sidebar-brand-title">PPIC</div>
                <div class="sidebar-brand-subtitle">PT Dunia Kimia Jaya</div>
            </div>
        </div>
    </div>

    <div class="list-group list-group-flush py-3 flex-grow-1">
        <div class="sidebar-label">
            <span class="sidebar-label-dot bg-primary"></span>
            Utama
        </div>
        <a href="/" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/', $currentUri) ?>">
            <i class="fas fa-border-all sidebar-menu-icon"></i>
            <span>Dashboard Sistem</span>
        </a>
        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_PPIC)): ?>
            <a href="/forecast-sales" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/forecast-sales', $currentUri) ?>">
                <i class="fas fa-chart-line sidebar-menu-icon"></i>
                <span>Sales &amp; Forecast</span>
            </a>
        <?php endif; ?>

        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_RD)): ?>
            <a href="/master/overview" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/overview', $currentUri) ?>">
                <i class="fas fa-boxes-stacked sidebar-menu-icon"></i>
                <span>Material</span>
            </a>
            <a href="/master/materials" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/materials', $currentUri) ?>">
                <i class="fas fa-layer-group sidebar-menu-icon"></i>
                <span>Data Raw Material</span>
                <?php
                $sidebarUnread = \App\Services\NotificationService::getUnreadCount($currentRole);
                ?>
                <?php if ($sidebarUnread > 0): ?>
                    <span class="sidebar-badge"><?= $sidebarUnread > 99 ? '99+' : $sidebarUnread ?></span>
                <?php endif; ?>
            </a>
            <a href="/master/products" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/products', $currentUri) ?>">
                <i class="fas fa-cube sidebar-menu-icon"></i>
                <span>Data Finish Good</span>
            </a>
            <a href="/master/boms" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/master/boms', $currentUri) ?>">
                <i class="fas fa-flask sidebar-menu-icon"></i>
                <span>Data Formula</span>
            </a>
        <?php endif; ?>

        <?php if (AccessControl::canAccessDivision(AccessControl::ROLE_PPIC)): ?>
            <div class="sidebar-label">
                <span class="sidebar-label-dot bg-info"></span>
                Analisis AI
            </div>
            <a href="/ai/forecast" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/ai/forecast', $currentUri) ?>">
                <i class="fas fa-star sidebar-menu-icon"></i>
                <span>Material &amp; Harga</span>
            </a>
        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>
            <div class="sidebar-label">
                <span class="sidebar-label-dot bg-light"></span>
                Konfigurasi
            </div>
            <a href="/settings" class="list-group-item list-group-item-action bg-transparent text-light <?= isActive('/settings', $currentUri) ?>">
                <i class="fas fa-sliders sidebar-menu-icon"></i>
                <span>Pengaturan AI &amp; Data</span>
            </a>
        <?php endif; ?>
        <div class="py-3"></div>
    </div>
</div>
