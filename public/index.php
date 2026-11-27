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
    \App\Services\FormulaCatalogService::ensureSeeded();
} catch (\Throwable $e) {
    die("Inisialisasi Database Error: " . $e->getMessage());
}

// Role switching simulation
if (isset($_GET['switch_role'])) {
    $role = htmlspecialchars($_GET['switch_role']);
    $role = match($role) {
        'RD', 'R&D' => 'RD',
        default => 'PPIC'
    };

    $_SESSION['user_division'] = $role;
    $_SESSION['is_super_admin'] = ($role === 'PPIC');
    $_SESSION['user_role'] = match($role) {
        'RD' => 'R&D (Evaluasi Formula BOM)',
        default => 'PPIC (Super Admin)'
    };
    $_SESSION['user_name'] = match($role) {
        'RD' => 'R&D Specialist',
        default => 'PPIC Officer'
    };
    $_SESSION['user_id'] = match($role) {
        'RD' => 7,
        default => 2 // PPIC
    };

    if ($_SESSION['is_super_admin']) {
        $_SESSION['flash_success'] = "Anda login sebagai PPIC";
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
if (!in_array($_SESSION['user_division'], ['PPIC', 'RD'], true)) {
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
        $sourceFilter = isset($_GET['source']) && is_string($_GET['source']) ? $_GET['source'] : null;
        $periodStart = isset($_GET['period_start']) && is_string($_GET['period_start']) ? $_GET['period_start'] : null;
        $periodEnd = isset($_GET['period_end']) && is_string($_GET['period_end']) ? $_GET['period_end'] : null;
        $search = isset($_GET['search']) && is_string($_GET['search']) ? $_GET['search'] : null;
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? (int) $_GET['page'] : null;
        $data = \App\Http\Controllers\SalesForecastController::index(
            $year,
            $pid,
            $needsStartDate,
            $needsProductId,
            $historyStartDate,
            $historyProductId,
            $sourceFilter,
            $periodStart,
            $periodEnd,
            $search,
            $page
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

    if ($uri === '/forecast-sales/import-xlsx' && $method === 'POST') {
        $yr = (int) ($_POST['forecast_year'] ?? 0);
        $upload = $_FILES['forecast_file'] ?? null;
        try {
            if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Pilih file Excel forecast yang valid untuk diunggah.');
            }
            if (strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') {
                throw new \InvalidArgumentException('Format file tidak didukung. Unggah file Excel .xlsx.');
            }
            if ((int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('Ukuran file maksimal 10 MB.');
            }

            $result = \App\Services\SalesForecastWorkbookImportService::import(
                (string) $upload['tmp_name'],
                $yr,
                (int) $_SESSION['user_id']
            );
            $newProducts = (int) ($result['created_products'] ?? 0);
            $_SESSION['flash_success'] = "Berhasil mengimpor forecast {$yr} untuk {$result['products']} produk pada bulan "
                .implode(', ', $result['months']).'. '
                .($newProducts > 0 ? "{$newProducts} produk baru juga dibuat dari data Excel." : '');
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('Gagal mengimpor workbook forecast: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Impor forecast gagal karena terjadi kesalahan. Pastikan workbook Excel sesuai format dan coba lagi.';
        }
        header("Location: /forecast-sales?year={$yr}&section=needs");
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
        header("Location: /forecast-sales?year={$yr}&section=needs");
        exit;
    }

    if ($uri === '/forecast-sales/store-forecast' && $method === 'POST') {
        $yr = (int) ($_POST['forecast_year'] ?? date('Y'));
        \App\Http\Controllers\SalesForecastController::storeForecastManual($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Forecast Sales 12 Bulan ({$yr}) berhasil disimpan!";
        header("Location: /forecast-sales?year={$yr}&section=needs");
        exit;
    }

    if ($uri === '/forecast-sales/delete-forecast') {
        $id = (int) ($_GET['id'] ?? 0);
        $yr = (int) ($_GET['year'] ?? date('Y'));
        \App\Http\Controllers\SalesForecastController::deleteForecast($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Data Forecast Sales berhasil dihapus.";
        header("Location: /forecast-sales?year={$yr}&section=needs");
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
    if ($uri === '/master/overview') {
        $data = \App\Http\Controllers\MasterController::materialOverview();
        echo view('master.overview', $data);
        exit;
    }

    if ($uri === '/master/storage/raw-materials') {
        $data = \App\Http\Controllers\MasterController::materials();
        $data['storageType'] = 'raw';
        echo view('master.storage', $data);
        exit;
    }

    if ($uri === '/master/storage/finish-goods') {
        $data = \App\Http\Controllers\MasterController::products();
        $data['storageType'] = 'finish';
        echo view('master.storage', $data);
        exit;
    }

    if ($uri === '/master/storage/formulas') {
        $data = \App\Http\Controllers\MasterController::boms();
        $data['storageType'] = 'formula';
        echo view('master.storage', $data);
        exit;
    }

    if ($uri === '/master/storage/forecast') {
        $data = \App\Http\Controllers\MasterController::materialOverview();
        $data['storageType'] = 'forecast';
        echo view('master.storage', $data);
        exit;
    }

    if ($uri === '/master/storage/sales') {
        $data = \App\Http\Controllers\MasterController::materialOverview();
        $data['storageType'] = 'sales';
        echo view('master.storage', $data);
        exit;
    }

    if ($uri === '/master/materials') {
        $data = \App\Http\Controllers\MasterController::materials();
        echo view('master.materials', $data);
        exit;
    }

    if ($uri === '/master/materials/detail') {
        $data = \App\Http\Controllers\MasterController::materialDetail((int) ($_GET['id'] ?? 0));
        echo view('master.material_detail', $data);
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

    if ($uri === '/master/products/formula') {
        $data = \App\Http\Controllers\MasterController::productFormulaDetail((int) ($_GET['id'] ?? 0));
        echo view('master.product_formula', $data);
        exit;
    }

    if ($uri === '/master/products/formula/store' && $method === 'POST') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $errorLocation = $productId > 0 ? '/master/products/formula?id=' . $productId : '/master/products';
        try {
            $productId = \App\Http\Controllers\MasterController::storeManualProductFormula(
                $_POST,
                (int) $_SESSION['user_id']
            );
            $_SESSION['flash_success'] = 'Formula manual berhasil disimpan dan diaktifkan.';
            header('Location: /master/products/formula?id=' . $productId);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . $errorLocation);
        } catch (\Throwable $e) {
            error_log('Gagal menyimpan formula manual Finish Good: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Formula manual gagal disimpan. Periksa kembali bahan dan persentasenya.';
            header('Location: ' . $errorLocation);
        }
        exit;
    }

    if ($uri === '/master/products/formula/link-catalog' && $method === 'POST') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $errorLocation = $productId > 0 ? '/master/products/formula?id=' . $productId : '/master/products';
        try {
            $productId = \App\Http\Controllers\MasterController::linkFormulaToProduct(
                $_POST,
                (int) $_SESSION['user_id']
            );
            $_SESSION['flash_success'] = 'Formula katalog berhasil dihubungkan dan diaktifkan.';
            header('Location: /master/products/formula?id=' . $productId);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . $errorLocation);
        } catch (\Throwable $e) {
            error_log('Gagal menghubungkan formula katalog dari Finish Good: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Formula katalog gagal dihubungkan. Periksa pemetaan material dan satuannya.';
            header('Location: ' . $errorLocation);
        }
        exit;
    }

    if ($uri === '/master/products/store' && $method === 'POST') {
        try {
            $productId = \App\Http\Controllers\MasterController::storeProduct($_POST, (int) $_SESSION['user_id']);
            $_SESSION['flash_success'] = "Master produk jadi baru berhasil ditambahkan.";
            header('Location: /master/products/formula?id=' . $productId);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /master/products');
        } catch (\Throwable $e) {
            error_log('Gagal menambahkan produk jadi: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Produk jadi gagal ditambahkan. Periksa kembali kode dan data produk.';
            header('Location: /master/products');
        }
        exit;
    }

    if ($uri === '/master/products/update-stock' && $method === 'POST') {
        \App\Http\Controllers\MasterController::updateProductStock($_POST, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = "Stok Finish Good berhasil diperbarui.";
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

    if ($uri === '/master/formulas/detail') {
        $data = \App\Http\Controllers\MasterController::formulaCatalogDetail(
            (int) ($_GET['id'] ?? 0),
            (int) ($_GET['product_id'] ?? 0)
        );
        echo view('master.formula_detail', $data);
        exit;
    }

    if ($uri === '/master/formulas/link-to-finished-good' && $method === 'POST') {
        $formulaId = (int) ($_POST['formula_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $errorLocation = $formulaId > 0
            ? '/master/formulas/detail?id=' . $formulaId . ($productId > 0 ? '&product_id=' . $productId : '')
            : '/master/boms';
        try {
            $productId = \App\Http\Controllers\MasterController::linkFormulaToProduct(
                $_POST,
                (int) $_SESSION['user_id']
            );
            $_SESSION['flash_success'] = 'Formula katalog berhasil dihubungkan ke Produk Jadi.';
            header('Location: /master/products/formula?id=' . $productId);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . $errorLocation);
        } catch (\Throwable $e) {
            error_log('Gagal menghubungkan formula katalog ke Produk Jadi: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Formula gagal dihubungkan. Periksa pemetaan material dan satuannya.';
            header('Location: ' . $errorLocation);
        }
        exit;
    }

    if ($uri === '/master/boms') {
        $data = \App\Http\Controllers\MasterController::boms((int) ($_GET['product_id'] ?? 0));
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
        $_SESSION['flash_success'] = "Formula BOM tersimpan berhasil diaktifkan kembali setelah validasi R&D.";
        header('Location: /master/boms');
        exit;
    }

    if ($uri === '/master/boms/store' && $method === 'POST') {
        try {
            \App\Http\Controllers\MasterController::storeBom($_POST, (int) $_SESSION['user_id']);
            $_SESSION['flash_success'] = "Formula BOM baru berhasil disimpan.";
            header('Location: /master/boms');
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /master/boms');
        } catch (\Throwable $e) {
            error_log('Gagal menyimpan formula BOM: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Formula gagal disimpan. Periksa kembali data bahan dan jumlah per batch.';
            header('Location: /master/boms');
        }
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

    if (($uri === '/master/materials/import-csv' || $uri === '/settings/import-csv') && $method === 'POST') {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            try {
                $uploadPath = (string) $_FILES['csv_file']['tmp_name'];
                $extension = strtolower(pathinfo((string) ($_FILES['csv_file']['name'] ?? ''), PATHINFO_EXTENSION));
                if ($extension === 'xlsx') {
                    $count = \App\Http\Controllers\SettingsController::importMaterialsXlsx($uploadPath, (int) $_SESSION['user_id']);
                } elseif ($extension === 'csv') {
                    $content = file_get_contents($uploadPath);
                    if ($content === false) {
                        throw new \RuntimeException('File CSV gagal dibaca.');
                    }
                    $count = \App\Http\Controllers\SettingsController::importMaterialsCsv($content, (int) $_SESSION['user_id']);
                } else {
                    throw new \InvalidArgumentException('Format file tidak didukung. Pilih file Excel .xlsx atau CSV.');
                }
                $_SESSION['flash_success'] = "Berhasil mengimpor {$count} data bahan baku.";
            } catch (\Throwable $e) {
                $_SESSION['flash_error'] = 'Impor data bahan baku gagal: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "File Excel/CSV tidak ditemukan atau gagal diunggah.";
        }
        header('Location: /master/materials');
        exit;
    }

    if ($uri === '/settings/import-rm-workbooks' && $method === 'POST') {
        $leadTimePath = $_FILES['lead_time_file']['tmp_name'] ?? '';
        $usagePath = $_FILES['usage_file']['tmp_name'] ?? '';
        if ($leadTimePath === '') {
            $_SESSION['flash_error'] = 'File lead time dan minimum stok wajib dipilih.';
        } else {
            try {
                $result = \App\Http\Controllers\SettingsController::importRawMaterialWorkbooks(
                    $leadTimePath,
                    $usagePath !== '' ? $usagePath : null,
                    (int) $_SESSION['user_id']
                );
                $_SESSION['flash_success'] = "Berhasil menyesuaikan {$result['materials']} master bahan baku, {$result['vendor_rows']} data vendor per material, {$result['suppliers']} supplier, dan {$result['usage_rows']} transaksi pemakaian dari workbook.";
            } catch (\Throwable $e) {
                $_SESSION['flash_error'] = 'Impor workbook gagal: ' . $e->getMessage();
            }
        }
        header('Location: /settings');
        exit;
    }

    if ($uri === '/master/materials/download-template' || $uri === '/settings/download-template') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_bahan_baku_dkj.csv"');
        echo "code,name,unit,min_stock,lead_time_days,moq,unit_cost\n";
        echo "BB-101,Bauksit Halus Teknis,Kg,25000,5,5000,4500\n";
        echo "BB-102,Asam Klorida 32% Bulk,Kg,20000,3,8000,1800\n";
        echo "BB-103,Kostik Cair 48%,Kg,16000,4,5000,6300\n";
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
