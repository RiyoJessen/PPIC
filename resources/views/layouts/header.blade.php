<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'PPIC') ?> — PT Dunia Kimia Jaya Gresik</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/css/custom.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    @include('layouts.sidebar')

    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 sticky-top shadow-sm" style="height: 92px;">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary me-3 px-3 py-2" id="menu-toggle" title="Tampilkan / Sembunyikan Sidebar">
                    <i class="fas fa-bars fs-5"></i>
                </button>

                <div class="text-muted fs-6 d-none d-md-block">
                    <i class="fas fa-calendar-day me-1 text-secondary"></i> <?= date('l, d F Y') ?>
                </div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <!-- System Status Badge -->
                <?php if (\App\Services\AccessControl::isSuperAdmin()): ?>
                    <a href="/settings" class="badge fs-6 rounded-pill bg-primary-subtle text-primary border border-primary-subtle text-decoration-none px-3 py-2" title="Pengaturan Sistem & Data">
                        <i class="fas fa-sliders-h me-1"></i> Pengaturan Sistem
                    </a>
                <?php endif; ?>

                <!-- Division Activity Notification Dropdown -->
                <?php
                $currentRole = \App\Services\AccessControl::getCurrentRole();
                $unreadCount = \App\Services\NotificationService::getUnreadCount($currentRole);
                $recentNotifs = \App\Services\NotificationService::getRecent($currentRole, 8);
                ?>
                <div class="dropdown" id="notifDropdownContainer">
                    <button class="btn btn-light border position-relative px-3 py-2 d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notifBellButton" title="Notifikasi Tugas Khusus Divisi <?= htmlspecialchars($currentRole) ?>">
                        <i class="fas fa-bell text-secondary fs-5"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span id="notifBadgeCounter" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white" style="font-size: 10px;">
                                <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                                <span class="visually-hidden">notifikasi baru</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" style="width: 390px; max-width: 92vw;">
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-bell text-primary"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold small text-dark">Notifikasi Divisi <?= htmlspecialchars($currentRole) ?></h6>
                                    <small class="text-muted" style="font-size: 10px;">Khusus tugas &amp; alur Divisi <?= htmlspecialchars($currentRole) ?></small>
                                </div>
                            </div>
                            <span id="notifAllReadBadge" class="badge bg-secondary-subtle text-secondary" style="font-size: 10px; <?= $unreadCount > 0 ? 'display: none;' : '' ?>">Semua Terbaca</span>
                            <?php if ($unreadCount > 0): ?>
                                <a id="notifMarkReadBtn" href="/notifications/mark-read" class="text-decoration-none small text-primary" style="font-size: 11px;">
                                    <i class="fas fa-check-double me-1"></i> Tandai Dibaca
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                            <?php if (empty($recentNotifs)): ?>
                                <div class="p-4 text-center text-muted small">
                                    <i class="fas fa-bell-slash fs-4 mb-2 d-block text-secondary opacity-50"></i>
                                    Belum ada notifikasi baru untuk Divisi <?= htmlspecialchars($currentRole) ?>.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentNotifs as $n): ?>
                                    <?php
                                    $divColor = match($n['division_source']) {
                                        'PPIC' => 'danger',
                                        'Warehouse' => 'info',
                                        'QC' => 'primary',
                                        'RD' => 'warning',
                                        default => 'primary'
                                    };
                                    $divIcon = match($n['division_source']) {
                                        'PPIC' => 'fas fa-calendar-check',
                                        'Warehouse' => 'fas fa-warehouse',
                                        'QC' => 'fas fa-microscope',
                                        'RD' => 'fas fa-flask-vial',
                                        default => 'fas fa-info-circle'
                                    };
                                    $isUnread = (int)$n['is_read'] === 0;
                                    ?>
                                    <a href="<?= htmlspecialchars($n['link'] ?: '/') ?>" class="list-group-item list-group-item-action p-3 border-bottom notif-item <?= $isUnread ? 'bg-primary-subtle bg-opacity-10 notif-unread-item' : '' ?>">
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="badge bg-<?= $divColor ?>-subtle text-<?= $divColor ?> border mt-1 p-2 rounded-circle">
                                                <i class="<?= $divIcon ?>"></i>
                                            </span>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 9px;" title="Divisi Pengirim">
                                                            <i class="fas fa-paper-plane me-1 text-muted"></i><?= strtoupper(htmlspecialchars($n['division_source'])) ?>
                                                        </span>
                                                        <i class="fas fa-arrow-right text-muted" style="font-size: 8px;"></i>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 9px;" title="Divisi Penerima">
                                                            <i class="fas fa-inbox me-1"></i><?= strtoupper(htmlspecialchars($n['target_division'])) ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted" style="font-size: 10px;">
                                                        <?= date('d M, H:i', strtotime($n['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div class="fw-bold small text-dark text-truncate"><?= htmlspecialchars($n['title']) ?></div>
                                                <p class="text-muted small mb-1 lh-sm" style="font-size: 11px;">
                                                    <?= htmlspecialchars($n['message']) ?>
                                                </p>
                                                <div class="text-muted" style="font-size: 10px;">
                                                    <i class="fas fa-user-edit me-1"></i> Oleh: <strong><?= htmlspecialchars($n['user_name'] ?? 'System') ?></strong>
                                                </div>
                                            </div>
                                            <?php if ($isUnread): ?>
                                                <span class="badge bg-primary rounded-circle p-1 notif-unread-dot" title="Belum dibaca" style="width: 8px; height: 8px;"></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 bg-light border-top text-center">
                            <small class="text-muted" style="font-size: 10px;">
                                <i class="fas fa-filter text-primary me-1"></i> Notifikasi terisolasi khusus Divisi <?= htmlspecialchars($currentRole) ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- User Profile / Role -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border px-3 py-2 shadow-xs" type="button" data-bs-toggle="dropdown">
                        <div class="avatar-circle">
                            <?php if (\App\Services\AccessControl::isSuperAdmin()): ?>
                                <i class="fas fa-user-shield fs-4" style="color: #2563eb;" title="Super Admin"></i>
                            <?php else: ?>
                                <i class="fas fa-user-circle text-primary fs-4"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-start d-none d-sm-block">
                            <div class="fw-semibold lh-1 fs-6"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <a class="dropdown-item <?= \App\Services\AccessControl::isSuperAdmin() ? 'active fw-bold' : '' ?>" href="?switch_role=PPIC">
                                <i class="fas fa-shield-alt me-2" style="color: #2563eb;"></i>
                                <strong>PPIC</strong>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= \App\Services\AccessControl::getCurrentRole() === 'RD' ? 'active fw-bold' : '' ?>" href="?switch_role=RD">
                                <i class="fas fa-flask-vial me-2" style="color: #7c3aed;"></i> <strong>R&amp;D</strong> (Evaluasi Formula BOM &amp; Material)
                            </a>
                        </li>
                        <?php if (\App\Services\AccessControl::isSuperAdmin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-secondary" href="/settings">
                                    <i class="fas fa-cog me-2"></i> Pengaturan &amp; Ganti Data
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Flash Alert Messages -->
        <div class="container-fluid px-4 pt-3">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fs-5 me-2"></i>
                    <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle fs-5 me-2"></i>
                    <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
        </div>

        <!-- Main Content Wrapper -->
        <main class="container-fluid px-4 py-3 pb-5">
