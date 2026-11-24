<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SalesForecastController;
use App\Http\Controllers\AiForecastingController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\SettingsController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index']);

// Input data Sales, Quotation/SO & Forecast Sales 12 Bulan
Route::prefix('forecast-sales')->name('forecast-sales.')->group(function () {
    Route::get('/', [SalesForecastController::class, 'index'])->name('index');
    Route::post('/store-sales', [SalesForecastController::class, 'storeSalesHistory'])->name('store-sales');
    Route::get('/status', [SalesForecastController::class, 'updateSalesStatus'])->name('status');
    Route::get('/delete-sales', [SalesForecastController::class, 'deleteSalesHistory'])->name('delete-sales');
    Route::post('/import-csv', [SalesForecastController::class, 'importSalesCsv'])->name('import-csv');
    Route::post('/generate', [SalesForecastController::class, 'generateForecast'])->name('generate');
    Route::post('/store-forecast', [SalesForecastController::class, 'storeForecastManual'])->name('store-forecast');
    Route::get('/delete-forecast', [SalesForecastController::class, 'deleteForecast'])->name('delete-forecast');
});

// AI Analisis Material, Harga Pasar & Update Data AI
Route::prefix('ai')->name('ai.')->group(function () {
    Route::get('/forecast', [AiForecastingController::class, 'index'])->name('forecast');
    Route::post('/price-history/store', [AiForecastingController::class, 'storeMarketPrice'])->name('price-history.store');
});

// Master Finish Good, Formula, dan Raw Material
Route::prefix('master')->name('master.')->group(function () {
    Route::get('/overview', [MasterController::class, 'materialOverview'])->name('overview');

    Route::get('/products', [MasterController::class, 'products'])->name('products.index');
    Route::post('/products/store', [MasterController::class, 'storeProduct'])->name('products.store');
    Route::get('/products/delete', [MasterController::class, 'deleteProduct'])->name('products.delete');

    Route::get('/boms', [MasterController::class, 'boms'])->name('boms.index');
    Route::post('/boms/store', [MasterController::class, 'storeBom'])->name('boms.store');
    Route::get('/boms/delete', [MasterController::class, 'deleteBom'])->name('boms.delete');
    Route::get('/boms/ai-advisor', [MasterController::class, 'aiFormulationAdvisor'])->name('boms.ai-advisor');
    Route::post('/boms/apply-ai-formulation', [MasterController::class, 'applyAiFormulation'])->name('boms.apply-ai-formulation');

    Route::get('/materials', [MasterController::class, 'materials'])->name('materials.index');
    Route::post('/materials/store', [MasterController::class, 'storeMaterial'])->name('materials.store');
    Route::post('/materials/update-stock', [MasterController::class, 'updateMaterialStock'])->name('materials.update-stock');
    Route::get('/materials/delete', [MasterController::class, 'deleteMaterial'])->name('materials.delete');

    Route::get('/suppliers', [MasterController::class, 'suppliers'])->name('suppliers.index');
    Route::post('/suppliers/store', [MasterController::class, 'storeSupplier'])->name('suppliers.store');
    Route::get('/suppliers/delete', [MasterController::class, 'deleteSupplier'])->name('suppliers.delete');
});

// Pencatatan stok dan mutasi gudang
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::post('/manual-receive', [InventoryController::class, 'manualReceiveStock'])->name('manual-receive');
    Route::post('/adjustment', [InventoryController::class, 'adjustStock'])->name('adjustment');
});

// Pengaturan & Notifikasi
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/wipe-data', [SettingsController::class, 'wipeAllData'])->name('settings.wipe');
Route::get('/notifications/mark-read', [DashboardController::class, 'markNotificationsRead'])->name('notifications.mark-read');
Route::post('/notifications/mark-read-ajax', [DashboardController::class, 'markNotificationsReadAjax'])->name('notifications.mark-read-ajax');
