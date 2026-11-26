@php
$pageTitle = 'Detail Bahan Baku - ' . $material['name'];
$statusClass = strtolower($material['stock_status']);
@endphp
@include('layouts.header')

<div class="material-detail-page">
    <a href="/master/materials" class="material-detail-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Data Raw Material
    </a>

    <header class="material-detail-heading">
        <div>
            <span class="material-detail-code"><?= htmlspecialchars($material['code']) ?></span>
            <h1><?= htmlspecialchars($material['name']) ?></h1>
            <p><?= htmlspecialchars($material['category_name'] ?? 'Bahan baku') ?> · <?= htmlspecialchars($material['unit']) ?></p>
        </div>
        <span class="material-storage-status status-<?= htmlspecialchars($statusClass) ?>">
            <?= htmlspecialchars($material['stock_status']) ?>
        </span>
    </header>

    <section class="material-detail-summary">
        <div>
            <span>Stok tersedia</span>
            <strong><?= number_format((float) $material['available_stock'], 0, ',', '.') ?> <?= htmlspecialchars($material['unit']) ?></strong>
        </div>
        <div>
            <span>Minimum stok</span>
            <strong><?= !empty($material['minimum_stock_note']) ? htmlspecialchars($material['minimum_stock_note']) : number_format((float) $material['minimum_stock'], 0, ',', '.') . ' ' . htmlspecialchars($material['unit']) ?></strong>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Vendor dan lead time</h2>
                <p>Setiap vendor ditampilkan sebagai baris terpisah sesuai workbook sumber.</p>
            </div>
            <span><?= count($material['vendor_options'] ?? []) ?> vendor</span>
        </div>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Material / Spesifikasi</th>
                            <th>Vendor</th>
                            <th class="text-center">Lead Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($material['vendor_options'])): ?>
                            <tr><td colspan="3" class="material-storage-empty">Belum ada data vendor untuk material ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($material['vendor_options'] as $vendor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($vendor['material_specification'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($vendor['supplier_name'] ?? 'Vendor tidak terdaftar') ?></td>
                                    <td class="text-center"><?= (int) $vendor['lead_time_days'] ?> hari</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Riwayat kedatangan barang</h2>
                <p>Daftar batch yang pernah dicatat masuk ke persediaan bahan ini.</p>
            </div>
            <span><?= count($arrivals) ?> batch</span>
        </div>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal kedatangan</th>
                            <th>No. batch / lot</th>
                            <th class="text-end">Jumlah diterima</th>
                            <th>Gudang</th>
                            <th>Tanggal kedaluwarsa</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($arrivals)): ?>
                            <tr><td colspan="6" class="material-storage-empty">Belum ada riwayat batch kedatangan yang tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($arrivals as $arrival): ?>
                                <tr>
                                    <td>
                                        <?= !empty($arrival['received_date']) ? htmlspecialchars(date('d-m-Y', strtotime($arrival['received_date']))) : 'Tanggal tidak tercatat' ?>
                                    </td>
                                    <td>
                                        <span class="material-storage-code"><?= htmlspecialchars($arrival['batch_number']) ?></span>
                                        <?php if (!empty($arrival['coa_number'])): ?>
                                            <div class="material-storage-meta">COA: <?= htmlspecialchars($arrival['coa_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $arrival['initial_qty'], 0, ',', '.') ?></strong>
                                        <?= htmlspecialchars($material['unit']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($arrival['warehouse_name'] ?? '-') ?></td>
                                    <td>
                                        <?= !empty($arrival['expired_date']) ? htmlspecialchars(date('d-m-Y', strtotime($arrival['expired_date']))) : '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($arrival['movement_notes'] ?: ($arrival['reference_number'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Produk yang menggunakan bahan ini</h2>
                <p>Produk dan versi formula BOM yang mencantumkan bahan ini beserta jumlah per batch.</p>
            </div>
            <span><?= count($products) ?> formula</span>
        </div>
        <div class="material-storage-table-wrap">
            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Versi formula</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Basis batch produk</th>
                            <th class="text-end">Kebutuhan bahan per batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="5" class="material-storage-empty">Bahan ini belum tercantum pada formula BOM produk mana pun.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                        <div class="material-storage-meta"><?= htmlspecialchars($product['product_code']) ?></div>
                                    </td>
                                    <td><span class="material-storage-code"><?= htmlspecialchars($product['version']) ?></span></td>
                                    <td class="text-center">
                                        <span class="material-storage-status <?= (int) $product['is_active'] === 1 ? 'status-aman' : 'status-waspada' ?>">
                                            <?= (int) $product['is_active'] === 1 ? 'Aktif' : 'Tidak aktif' ?>
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <?= number_format((float) $product['batch_size'], 0, ',', '.') ?> <?= htmlspecialchars($product['batch_unit']) ?>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <strong><?= number_format((float) $product['quantity_per_batch'], 2, ',', '.') ?></strong>
                                        <?= htmlspecialchars($product['material_unit']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

@include('layouts.footer')
