@php
$storageType = $storageType ?? 'raw';
$storageConfig = match ($storageType) {
    'finish' => [
        'title' => 'Data Finish Good',
        'subtitle' => 'Daftar penyimpanan produk jadi beserta stok dan batch standarnya.',
        'icon' => 'fa-cube',
        'color' => 'teal',
    ],
    'formula' => [
        'title' => 'Data Formula',
        'subtitle' => 'Daftar formula tersimpan beserta komposisi bahan untuk setiap versi.',
        'icon' => 'fa-flask',
        'color' => 'purple',
    ],
    'forecast' => [
        'title' => 'Data Forecast',
        'subtitle' => 'Daftar hasil peramalan penjualan per produk dan tahun.',
        'icon' => 'fa-chart-line',
        'color' => 'orange',
    ],
    'sales' => [
        'title' => 'Data Penjualan',
        'subtitle' => 'Daftar riwayat transaksi, pelanggan, dan jumlah penjualan.',
        'icon' => 'fa-file-invoice-dollar',
        'color' => 'indigo',
    ],
    default => [
        'title' => 'Data Raw Material',
        'subtitle' => 'Daftar penyimpanan bahan baku beserta jumlah stok yang tersedia.',
        'icon' => 'fa-layer-group',
        'color' => 'blue',
    ],
};
$pageTitle = $storageConfig['title'];
@endphp
@include('layouts.header')

<div class="material-storage-page material-storage-<?= $storageType ?>">
    <div class="material-storage-header">
        <a href="/master/overview" class="material-storage-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Material
        </a>
        <h1>
            <span class="material-storage-icon material-storage-icon-<?= $storageConfig['color'] ?>">
                <i class="fas <?= $storageConfig['icon'] ?>"></i>
            </span>
            <?= $storageConfig['title'] ?>
        </h1>
        <p><?= $storageConfig['subtitle'] ?></p>
    </div>

    <?php if ($storageType === 'raw'): ?>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Bahan</th>
                            <th>Kategori</th>
                            <th class="text-end">Stok Tersedia</th>
                            <th class="text-center">Status</th>
                            <th>Gudang / Kondisi</th>
                            <th class="text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr><td colspan="7" class="material-storage-empty">Belum ada data Raw Material.</td></tr>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <tr>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($material['code']) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($material['name']) ?></strong>
                                        <div class="material-storage-meta"><?= htmlspecialchars($material['supplier_name'] ?? 'Supplier belum ditentukan') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($material['category_name'] ?? '-') ?></td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $material['available_stock'], 0, ',', '.') ?></strong>
                                        <?= htmlspecialchars($material['unit']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="material-storage-status status-<?= strtolower($material['stock_status']) ?>">
                                            <?= htmlspecialchars($material['stock_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($material['storage_condition'] ?: 'Penyimpanan standar') ?></td>
                                    <td class="text-end">
                                        <a href="/master/materials/detail?id=<?= (int) $material['id'] ?>" class="material-storage-detail-link">
                                            Detail <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($storageType === 'finish'): ?>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th class="text-end">Stok Finish Good</th>
                            <th class="text-end">Batch Standar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="5" class="material-storage-empty">Belum ada data Finish Good.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($product['code']) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <div class="material-storage-meta"><?= htmlspecialchars($product['description'] ?: 'Produk jadi') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $product['finished_good_stock'], 0, ',', '.') ?></strong>
                                        <?= htmlspecialchars($product['unit']) ?>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <?= number_format((float) $product['standard_batch_size'], 0, ',', '.') ?>
                                        <?= htmlspecialchars($product['unit']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($storageType === 'forecast'): ?>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Tahun</th>
                            <th>Metode</th>
                            <th class="text-end">Total Forecast</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales_forecasts)): ?>
                            <tr><td colspan="5" class="material-storage-empty">Belum ada data Forecast.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales_forecasts as $forecast): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($forecast['product_name']) ?></strong>
                                        <div class="material-storage-meta"><?= htmlspecialchars($forecast['product_code']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="material-storage-code"><?= (int) $forecast['forecast_year'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($forecast['method']) ?></td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $forecast['total_12m_qty'], 0, ',', '.') ?></strong>
                                        <?= htmlspecialchars($forecast['product_unit']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($forecast['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($storageType === 'sales'): ?>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No. Dokumen</th>
                            <th>Pelanggan</th>
                            <th>Produk</th>
                            <th>Tanggal</th>
                            <th class="text-end">Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales_histories)): ?>
                            <tr><td colspan="6" class="material-storage-empty">Belum ada data Penjualan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales_histories as $sale): ?>
                                <tr>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($sale['doc_number']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($sale['customer_name']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($sale['product_name']) ?>
                                        <div class="material-storage-meta"><?= htmlspecialchars($sale['product_code']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($sale['sale_date']))) ?></td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $sale['quantity'], 0, ',', '.') ?></strong>
                                        <?= htmlspecialchars($sale['product_unit']) ?>
                                    </td>
                                    <td>
                                        <span class="material-storage-status <?= $sale['status'] === 'fulfilled' ? 'status-aman' : 'status-waspada' ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sale['status']))) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Versi</th>
                            <th class="text-end">Basis Batch</th>
                            <th class="text-center">Status</th>
                            <th>Komposisi Raw Material</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($boms)): ?>
                            <tr><td colspan="5" class="material-storage-empty">Belum ada data Formula.</td></tr>
                        <?php else: ?>
                            <?php foreach ($boms as $bom): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($bom['product_name']) ?></strong>
                                        <div class="material-storage-meta"><?= htmlspecialchars($bom['product_code']) ?></div>
                                    </td>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($bom['version']) ?></span></td>
                                    <td class="text-end font-monospace">
                                        <?= number_format((float) $bom['batch_size'], 0, ',', '.') ?>
                                        <?= htmlspecialchars($bom['unit']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="material-storage-status <?= (int) $bom['is_active'] === 1 ? 'status-aman' : 'status-waspada' ?>">
                                            <?= (int) $bom['is_active'] === 1 ? 'Aktif' : 'Draf' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (empty($bom['items'])): ?>
                                            <span class="material-storage-meta">Belum ada komposisi</span>
                                        <?php else: ?>
                                            <?php foreach ($bom['items'] as $item): ?>
                                                <div class="material-composition-item">
                                                    <span><?= htmlspecialchars($item['material_name']) ?></span>
                                                    <strong><?= number_format((float) $item['quantity_per_batch'], 2, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

@include('layouts.footer')
