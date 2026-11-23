<?php
declare(strict_types=1);

session_start();

// Autoload classes
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Laravel global view() helper function
if (!function_exists('view')) {
    function view(string $view, array $data = []): string {
        return \App\Services\BladeEngine::render($view, $data);
    }
}

// Load configuration & environment
\App\Config\AppConfig::loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Auto-migrate & pastikan referensi struktural dasar tersedia
try {
    \App\Database\Migration::run();
    \App\Database\Seeder::ensureBaseSystemReferences();
} catch (\Throwable $e) {
    die("Inisialisasi Database Error: " . $e->getMessage());
}

// Role switching simulation
if (isset($_GET['switch_role'])) {
    $role = htmlspecialchars($_GET['switch_role']);
    $role = match($role) {
        'Purchasing' => 'Purchasing',
        'RD', 'R&D' => 'RD',
        default => 'PPIC'
    };

    $_SESSION['user_division'] = $role;
    $_SESSION['is_super_admin'] = ($role === 'PPIC');
    $_SESSION['user_role'] = match($role) {
        'Purchasing' => 'Purchasing (Evaluasi Vendor & PO)',
        'RD' => 'R&D (Evaluasi Formula BOM)',
        default => 'PPIC (Super Admin)'
    };
    $_SESSION['user_name'] = match($role) {
        'Purchasing' => 'Purchasing Officer',
        'RD' => 'R&D Specialist',
        default => 'PPIC Officer'
    };
    $_SESSION['user_id'] = match($role) {
        'Purchasing' => 4,
        'RD' => 7,
        default => 2 // PPIC
    };

    if ($_SESSION['is_super_admin']) {
        $_SESSION['flash_success'] = "🛡️ Mode Super Admin Aktif: Anda login sebagai PPIC (Akses Penuh ke Seluruh Alur Flowchart).";
    } else {
        $divName = \App\Services\AccessControl::getDivisionLabel($role);
        $_SESSION['flash_success'] = "🔒 Berhasil beralih role ke: {$divName}.";
    }

    $cleanUri = \App\Services\AccessControl::getDefaultRouteForCurrentRole();
    header("Location: {$cleanUri}");
    exit;
}

if (!isset($_SESSION['user_division'])) {
    $_SESSION['user_division'] = 'PPIC';
    $_SESSION['is_super_admin'] = true;
    $_SESSION['user_id'] = 2;
    $_SESSION['user_name'] = 'PPIC Officer';
    $_SESSION['user_role'] = 'PPIC (Super Admin)';
}

// Otomatis cek dan kirim notifikasi email jika persediaan bahan baku akan habis dalam 15-30 hari (cooldown 24 jam)
try {
    \App\Services\EmailNotificationService::autoCheckAndSendIfDue();
} catch (\Throwable $e) {
    error_log("Auto notification check failed: " . $e->getMessage());
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ACCESS CONTROL ENFORCEMENT
if (!\App\Services\AccessControl::checkAccess($uri)) {
    $targetDiv = \App\Services\AccessControl::getDivisionForUri($uri);
    $targetName = \App\Services\AccessControl::getDivisionLabel($targetDiv);
    $myRoleName = $_SESSION['user_role'] ?? 'Pengguna';
    $_SESSION['flash_error'] = "⛔ Akses Ditolak! Halaman ini merupakan wewenang {$targetName}. Anda saat ini dalam peran {$myRoleName} (hanya berhak melihat, menginput & menghapus data di divisinya sendiri). Hanya PPIC (Super Admin) yang berhak mengakses semua divisi.";
    $fallback = \App\Services\AccessControl::getDefaultRouteForCurrentRole();
    header("Location: {$fallback}");
    exit;
}

try {
    // ROUTING
    if ($uri === '/' || $uri === '/dashboard') {
        $data = \App\Http\Controllers\DashboardController::index();
        echo view('dashboard.index', $data);
        exit;
    }

    // DATA SALES, QUOTATION/SO & FORECAST SALES 12 BULAN
    if ($uri === '/forecast-sales') {
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $pid = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;
        $needsStartDate = isset($_GET['start_date']) && is_string($_GET['start_date']) ? $_GET['start_date'] : null;
        $needsProductId = isset($_GET['needs_product_id']) && is_scalar($_GET['needs_product_id'])
            ? (int) $_GET['needs_product_id']
            : null;
        $historyStartDate = isset($_GET['history_start_date']) && is_string($_GET['history_start_date']) ? $_GET['history_start_date'] : null;
        $historyProductId = isset($_GET['history_product_id']) && is_scalar($_GET['history_product_id'])
            ? (int) $_GET['history_product_id']
            : null;
        $data = \App\Http\Controllers\SalesForecastController::index(
            $year,
            $pid,
            $needsStartDate,
            $needsProductId,
            $historyStartDate,
            $historyProductId
        );
        echo view('sales.forecast', $data);
        exit;
    }

    if ($uri === '/forecast-sales/store-sales' && $method === 'POST') {
        \App\Http\Controllers\SalesForecastController::storeSalesHistory($_POST, (int) $_SESSION['user_id']);
        $yr = (int) ($_POST['redirect_year'] ?? date('Y'));
        $section = in_array($_POST['redirect_section'] ?? '', ['needs', 'history'], true) ? $_POST['redirect_section'] : 'needs';
        $_SESSION['flash_success'] = "Data penjualan / Quotation / SO berhasil dicatat!";
        header("Location: /forecast-sales?year={$yr}&section={$section}");
        exit;
    }

    if ($uri === '/forecast-sales/status') {
        $id = (int) ($_GET['id'] ?? 0);
        $status = (string) ($_GET['status'] ?? 'confirmed_so');
        $yr = (int) ($_GET['year'] ?? date('Y'));
        $section = in_array($_GET['section'] ?? '', ['needs', 'history'], true) ? $_GET['section'] : 'overview';
        \App\Http\Controllers\SalesForecastController::updateSalesStatus($id, $status, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Status dokumen sales berhasil diperbarui!";
        header("Location: /forecast-sales?year={$yr}&section={$section}");
        exit;
    }

    if ($uri === '/forecast-sales/delete-sales') {
        $id = (int) ($_GET['id'] ?? 0);
        $yr = (int) ($_GET['year'] ?? date('Y'));
        $section = in_array($_GET['section'] ?? '', ['needs', 'history'], true) ? $_GET['section'] : 'overview';
        \App\Http\Controllers\SalesForecastController::deleteSalesHistory($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Data penjualan berhasil dihapus.";
        header("Location: /forecast-sales?year={$yr}&section={$section}");
        exit;
    }

    if ($uri === '/forecast-sales/import-csv' && $method === 'POST') {
        if (!empty($_FILES['sales_csv']['tmp_name'])) {
            $csv = file_get_contents($_FILES['sales_csv']['tmp_name']);
            $count = \App\Services\SalesForecastService::importSalesCsv($csv, (int) $_SESSION['user_id']);
            $_SESSION['flash_success'] = "Berhasil mengimpor {$count} baris data penjualan aktual dari CSV! Klik 'Generate Forecast 12 Bln Otomatis' untuk menghitung proyeksi.";
        } else {
            $_SESSION['flash_error'] = "File CSV penjualan tidak ditemukan atau gagal diunggah.";
        }
        header('Location: /forecast-sales?section=history');
        exit;
    }

    if ($uri === '/forecast-sales/download-template') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_data_penjualan_dkj.csv"');
        echo "product_code,sale_month,quantity,unit_price,customer_name,notes\n";
        echo "PRD-001,2026-01,2500,12000,PT Pelanggan A,Penjualan reguler\n";
        echo "PRD-001,2026-02,2800,12000,PT Pelanggan A,Penjualan reguler\n";
        echo "PRD-001,2026-03,3100,12000,PT Pelanggan B,Kenaikan permintaan\n";
        exit;
    }

    if ($uri === '/forecast-sales/generate' && $method === 'POST') {
        $yr = (int) ($_POST['forecast_year'] ?? date('Y'));
        $cnt = \App\Http\Controllers\SalesForecastController::generateForecast($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Berhasil menghitung Forecast Sales 12 Bulan ({$yr}) otomatis untuk {$cnt} produk dari riwayat penjualan aktual!";
        header("Location: /forecast-sales?year={$yr}&section=forecast");
        exit;
    }

    if ($uri === '/forecast-sales/store-forecast' && $method === 'POST') {
        $yr = (int) ($_POST['forecast_year'] ?? date('Y'));
        \App\Http\Controllers\SalesForecastController::storeForecastManual($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Forecast Sales 12 Bulan ({$yr}) berhasil disimpan!";
        header("Location: /forecast-sales?year={$yr}&section=forecast");
        exit;
    }

    if ($uri === '/forecast-sales/delete-forecast') {
        $id = (int) ($_GET['id'] ?? 0);
        $yr = (int) ($_GET['year'] ?? date('Y'));
        $section = ($_GET['section'] ?? '') === 'forecast' ? 'forecast' : 'overview';
        \App\Http\Controllers\SalesForecastController::deleteForecast($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Data Forecast Sales berhasil dihapus.";
        header("Location: /forecast-sales?year={$yr}&section={$section}");
        exit;
    }

    // EMAIL & DIVISION NOTIFICATIONS
    if ($uri === '/notifications/mark-read-ajax') {
        $activeDiv = \App\Services\AccessControl::getCurrentRole();
        \App\Services\NotificationService::markAllAsRead($activeDiv);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'unread' => 0, 'division' => $activeDiv]);
        exit;
    }

    if ($uri === '/notifications/mark-read') {
        $activeDiv = \App\Services\AccessControl::getCurrentRole();
        \App\Services\NotificationService::markAllAsRead($activeDiv);
        $_SESSION['flash_success'] = "Semua notifikasi untuk Divisi {$activeDiv} telah ditandai sebagai sudah dibaca.";
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: {$referer}");
        exit;
    }

    if ($uri === '/notifications/send-stock-alert') {
        $result = \App\Services\EmailNotificationService::sendCriticalStockAlert();
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = "Gagal mengirim email: " . ($result['error'] ?? 'Terjadi kesalahan sistem.');
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: {$referer}");
        exit;
    }

    if ($uri === '/notifications/test-email' && $method === 'POST') {
        $recipient = trim($_POST['test_email'] ?? '');
        $result = \App\Services\EmailNotificationService::sendCriticalStockAlert($recipient ?: null);
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = "Gagal kirim email uji coba: " . ($result['error'] ?? 'Periksa kredensial SMTP Anda.');
        }
        header('Location: /settings');
        exit;
    }

    // PURCHASING & PR / PO
    if ($uri === '/purchasing/pr') {
        $data = \App\Http\Controllers\PurchasingController::prIndex();
        echo view('purchasing.pr', $data);
        exit;
    }

    if ($uri === '/purchasing/pr/create' && $method === 'POST') {
        $prId = \App\Http\Controllers\PurchasingController::createPrManual($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Purchase Requisition manual berhasil diajukan dan menunggu approval Manager.";
        header('Location: /purchasing/pr');
        exit;
    }

    if ($uri === '/purchasing/pr/delete') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\PurchasingController::deletePr($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Purchase Requisition berhasil dibatalkan dan dihapus.";
        header('Location: /purchasing/pr');
        exit;
    }

    if ($uri === '/purchasing/pr/create-from-ai') {
        $matId = (int) ($_GET['material_id'] ?? 0);
        $qty = (float) ($_GET['qty'] ?? 0);
        $prId = \App\Http\Controllers\AiForecastingController::createPrFromAi($matId, $qty, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Purchase Requisition berhasil dibuat langsung dari Rekomendasi AI Gemini!";
        header('Location: /purchasing/pr');
        exit;
    }

    if ($uri === '/purchasing/pr/approve') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\PurchasingController::approvePr($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Purchase Requisition berhasil disetujui (Approved). Siap diterbitkan PO.";
        header('Location: /purchasing/pr');
        exit;
    }

    if ($uri === '/purchasing/po') {
        $data = \App\Http\Controllers\PurchasingController::poIndex();
        echo view('purchasing.po', $data);
        exit;
    }

    if ($uri === '/purchasing/po/generate') {
        $prId = (int) ($_GET['pr_id'] ?? 0);
        $poId = \App\Http\Controllers\PurchasingController::generatePo($prId, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Purchase Order resmi berhasil diterbitkan ke supplier rekanan.";
        header("Location: /purchasing/po/show?id={$poId}");
        exit;
    }

    if ($uri === '/purchasing/po/show') {
        $id = (int) ($_GET['id'] ?? 0);
        $data = \App\Http\Controllers\PurchasingController::poShow($id);
        echo view('purchasing.po_show', $data);
        exit;
    }

    if ($uri === '/purchasing/po/receive' && $method === 'POST') {
        $poId = (int) $_POST['po_id'];
        \App\Http\Controllers\PurchasingController::receivePo($poId, $_POST['items'] ?? [], (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Penerimaan fisik barang berhasil dikonfirmasi! Stok fisik gudang telah bertambah.";
        header("Location: /purchasing/po/show?id={$poId}");
        exit;
    }

    // AI FORECASTING
    if ($uri === '/ai/forecast') {
        $selectedMid = isset($_GET['material_id']) ? (int) $_GET['material_id'] : null;
        $lookback = isset($_GET['lookback']) ? (int) $_GET['lookback'] : 90;
        $data = \App\Http\Controllers\AiForecastingController::index($selectedMid, $lookback);
        echo view('ai.forecast', $data);
        exit;
    }

    if ($uri === '/ai/price-history/store' && $method === 'POST') {
        \App\Http\Controllers\AiForecastingController::storeMarketPrice($_POST, (int) $_SESSION['user_id']);
        $matId = (int) ($_POST['material_id'] ?? 1);
        $_SESSION['flash_success'] = "Data harga pasar bahan baku berhasil dicatat! Ramalan harga bulan depan telah diperbarui secara otomatis.";
        header("Location: /ai/forecast?material_id={$matId}#price-forecast-section");
        exit;
    }

    if ($uri === '/api/ai/price-forecast') {
        header('Content-Type: application/json');
        $matId = (int) ($_GET['material_id'] ?? 0);
        try {
            $forecast = \App\Http\Controllers\AiForecastingController::forecastPrice($matId);
            echo json_encode(['success' => true, 'price_forecast' => $forecast]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($uri === '/api/ai/analyze') {
        header('Content-Type: application/json');
        $matId = (int) ($_GET['material_id'] ?? 0);
        try {
            $analysis = \App\Http\Controllers\AiForecastingController::analyze($matId);
            echo json_encode(['success' => true, 'analysis' => $analysis]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // MASTER DATA
    if ($uri === '/master/materials') {
        $data = \App\Http\Controllers\MasterController::materials();
        echo view('master.materials', $data);
        exit;
    }

    if ($uri === '/master/materials/store' && $method === 'POST') {
        \App\Http\Controllers\MasterController::storeMaterial($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Master bahan baku baru berhasil ditambahkan.";
        header('Location: /master/materials');
        exit;
    }

    if ($uri === '/master/materials/update-stock' && $method === 'POST') {
        \App\Http\Controllers\MasterController::updateMaterialStock($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Saldo stok bahan baku berhasil diperbarui!";
        header('Location: /master/materials');
        exit;
    }

    if ($uri === '/master/materials/delete') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\MasterController::deleteMaterial($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Master bahan baku berhasil dihapus.";
        header('Location: /master/materials');
        exit;
    }

    if ($uri === '/master/products') {
        $data = \App\Http\Controllers\MasterController::products();
        echo view('master.products', $data);
        exit;
    }

    if ($uri === '/master/products/store' && $method === 'POST') {
        \App\Http\Controllers\MasterController::storeProduct($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Master produk jadi baru berhasil ditambahkan.";
        header('Location: /master/products');
        exit;
    }

    if ($uri === '/master/products/delete') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\MasterController::deleteProduct($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Master produk jadi berhasil dihapus.";
        header('Location: /master/products');
        exit;
    }

    if ($uri === '/master/boms') {
        $data = \App\Http\Controllers\MasterController::boms();
        echo view('master.boms', $data);
        exit;
    }

    if ($uri === '/master/boms/ai-advisor') {
        $data = \App\Http\Controllers\MasterController::aiFormulationAdvisor((int)($_GET['product_id'] ?? 0));
        echo view('master.bom_advisor', $data);
        exit;
    }

    if ($uri === '/master/boms/apply-ai-formulation' && $method === 'POST') {
        \App\Http\Controllers\MasterController::applyAiFormulation($_POST, (int)$_SESSION['user_id']);
        $_SESSION['flash_success'] = "Formula BOM alternatif dari AI Gemini berhasil disetujui & dipublikasikan ke sistem produksi!";
        header('Location: /master/boms');
        exit;
    }

    if ($uri === '/master/boms/store' && $method === 'POST') {
        \App\Http\Controllers\MasterController::storeBom($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Formula BOM baru berhasil disimpan.";
        header('Location: /master/boms');
        exit;
    }

    if ($uri === '/master/boms/delete') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\MasterController::deleteBom($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Formula BOM berhasil dihapus.";
        header('Location: /master/boms');
        exit;
    }

    if ($uri === '/master/suppliers') {
        $data = \App\Http\Controllers\MasterController::suppliers();
        echo view('master.suppliers', $data);
        exit;
    }

    if ($uri === '/master/suppliers/store' && $method === 'POST') {
        \App\Http\Controllers\MasterController::storeSupplier($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Supplier rekanan baru berhasil ditambahkan.";
        header('Location: /master/suppliers');
        exit;
    }

    if ($uri === '/master/suppliers/delete') {
        $id = (int) ($_GET['id'] ?? 0);
        \App\Http\Controllers\MasterController::deleteSupplier($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Supplier rekanan berhasil dihapus.";
        header('Location: /master/suppliers');
        exit;
    }

    // SETTINGS & DATA MANAGEMENT
    if ($uri === '/settings') {
        $data = \App\Http\Controllers\SettingsController::index();
        echo view('settings.index', $data);
        exit;
    }

    if ($uri === '/settings/save' && $method === 'POST') {
        \App\Http\Controllers\SettingsController::saveSettings($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Pengaturan Google Gemini berhasil disimpan.";
        header('Location: /settings');
        exit;
    }

    if ($uri === '/settings/save-mail' && $method === 'POST') {
        \App\Http\Controllers\SettingsController::saveMailSettings($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Pengaturan SMTP Email & Notifikasi Stok berhasil disimpan.";
        header('Location: /settings');
        exit;
    }

    if ($uri === '/settings/wipe-data' && $method === 'POST') {
        \App\Http\Controllers\SettingsController::wipeAllData((int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Seluruh data transaksi dan master telah dikosongkan. Sistem siap menerima data aktual.";
        header('Location: /settings');
        exit;
    }

    if ($uri === '/settings/import-csv' && $method === 'POST') {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);
            $count = \App\Http\Controllers\SettingsController::importMaterialsCsv($content, (int) $_SESSION['user_id']);
            $_SESSION['flash_success'] = "Berhasil mengimpor {$count} data bahan baku dari file CSV!";
        } else {
            $_SESSION['flash_error'] = "File CSV tidak ditemukan atau gagal diunggah.";
        }
        header('Location: /settings');
        exit;
    }

    if ($uri === '/settings/download-template') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_bahan_baku_dkj.csv"');
        echo "code,name,unit,min_stock,safety_stock,reorder_point,lead_time_days,moq,unit_cost\n";
        echo "BB-101,Bauksit Halus Teknis,Kg,10000,15000,25000,5,5000,4500\n";
        echo "BB-102,Asam Klorida 32% Bulk,Kg,8000,12000,20000,3,8000,1800\n";
        echo "BB-103,Kostik Cair 48%,Kg,6000,10000,16000,4,5000,6300\n";
        exit;
    }

    // 404
    http_response_code(404);
    echo "<h1>404 Halaman Tidak Ditemukan</h1><p><a href='/'>Kembali ke Dashboard</a></p>";

} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Terjadi Kesalahan Sistem</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
