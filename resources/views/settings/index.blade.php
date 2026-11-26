@php
$pageTitle = 'Pengaturan Sistem';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Pengaturan Sistem</h3>
        <p class="text-muted small mb-0">
            Pengaturan notifikasi email dan integrasi Google Gemini API.
        </p>
    </div>
</div>

<div class="row g-4">
    <!-- Section 2: Konfigurasi Gemini AI & Database -->
    <div class="col-12 col-lg-8 mx-auto">
        <!-- Email Notification Settings -->
        <div class="card border-0 shadow-sm border-start border-4 border-danger mb-4">
            <div class="card-header bg-white py-3 fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-envelope-open-text text-danger me-2"></i> Notifikasi Email Stok Kritis &amp; SMTP</span>
                <span class="badge bg-danger-subtle text-danger">Peringatan Otomatis</span>
            </div>
            <div class="card-body">
                <form action="/settings/save-mail" method="POST" class="mb-3">
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small fw-semibold">SMTP Host</label>
                            <input type="text" name="mail_host" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($mail_config['host']) ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Port</label>
                            <input type="number" name="mail_port" class="form-control form-control-sm font-monospace" value="<?= $mail_config['port'] ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username / Email Pengirim</label>
                            <input type="text" name="mail_username" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($mail_config['username']) ?>" placeholder="contoh@gmail.com">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">App Password</label>
                            <input type="password" name="mail_password" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($mail_config['password']) ?>" placeholder="Password aplikasi">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Email Penerima Peringatan (Pisahkan koma)</label>
                        <input type="text" name="mail_recipients" class="form-control form-control-sm" value="<?= htmlspecialchars($mail_config['recipients']) ?>" placeholder="ppic@duniakimia.co.id" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat Pengirim (From Header)</label>
                        <input type="text" name="mail_from" class="form-control form-control-sm" value="<?= htmlspecialchars($mail_config['from_address']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="fas fa-save me-1"></i> Simpan Pengaturan Email
                    </button>
                </form>

                <hr class="my-3">

                <!-- Test & Trigger Alert -->
                <div class="d-flex flex-column gap-2">
                    <a href="/notifications/send-stock-alert" class="btn btn-outline-danger btn-sm text-start" onclick="return confirm('Kirim notifikasi peringatan stok kritis ke semua penerima sekarang?')">
                        <i class="fas fa-paper-plane me-1"></i> <strong>Trigger Sekarang:</strong> Kirim Notifikasi Stok Kritis
                    </a>

                    <form action="/notifications/test-email" method="POST" class="input-group input-group-sm">
                        <input type="email" name="test_email" class="form-control" placeholder="Kirim uji coba ke email Anda..." required>
                        <button type="submit" class="btn btn-outline-secondary">Kirim Tes</button>
                    </form>
                </div>

                <div class="form-text small mt-2" style="font-size: 11px;">
                    <i class="fas fa-info-circle me-1 text-primary"></i> <em>Mode Simulasi Cerdas:</em> Jika kredensial SMTP belum diisi, sistem otomatis mencatat simulasi pengiriman dan menampilkan draft email lengkap di riwayat log.
                </div>
            </div>
        </div>

        <!-- Gemini AI Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="fas fa-key text-purple me-2" style="color: #a855f7;"></i> Integrasi Google Gemini API
            </div>
            <div class="card-body">
                <form action="/settings/save" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Google Gemini API Key</label>
                        <input type="password" name="gemini_api_key" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($api_key ?? '') ?>" placeholder="AIzaSy...">
                        <div class="form-text small">
                            Jika dikosongkan, sistem otomatis menjalankan <strong>simulasi heuristik AI cerdas</strong> berbasis domain industri kimia PT Dunia Kimia Jaya Gresik tanpa memerlukan koneksi eksternal.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Model Gemini</label>
                        <select name="gemini_model" class="form-select form-select-sm">
                            <option value="gemini-1.5-flash" <?= ($model === 'gemini-1.5-flash') ? 'selected' : '' ?>>gemini-1.5-flash (Cepat &amp; Rekomendasi)</option>
                            <option value="gemini-2.0-flash" <?= ($model === 'gemini-2.0-flash') ? 'selected' : '' ?>>gemini-2.0-flash (Generasi Terbaru)</option>
                            <option value="gemini-1.5-pro" <?= ($model === 'gemini-1.5-pro') ? 'selected' : '' ?>>gemini-1.5-pro (Analisis Kompleks)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm w-100">
                        <i class="fas fa-save me-1"></i> Simpan Konfigurasi AI
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Riwayat Notifikasi Email Terkirim -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history text-danger me-2"></i> Riwayat Pengiriman Notifikasi Email Stok Kritis</span>
        <span class="badge bg-light text-muted border">Total: <?= count($email_logs) ?> Log</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Waktu Kirim</th>
                    <th>Penerima</th>
                    <th>Subjek Email</th>
                    <th class="text-center">Jumlah Bahan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi / Preview</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($email_logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat pengiriman notifikasi email.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($email_logs as $el): ?>
                        <tr>
                            <td class="font-monospace text-muted" style="font-size: 11px;">
                                <?= date('d/m/Y H:i:s', strtotime($el['created_at'])) ?>
                            </td>
                            <td><span class="fw-semibold text-dark"><?= htmlspecialchars($el['recipients']) ?></span></td>
                            <td><?= htmlspecialchars($el['subject']) ?></td>
                            <td class="text-center font-monospace fw-bold text-danger">
                                <?= $el['critical_items_count'] ?> Bahan
                            </td>
                            <td class="text-center">
                                <?php if ($el['status'] === 'sent'): ?>
                                    <span class="badge bg-success-subtle text-success border"><i class="fas fa-check-circle me-1"></i> Terkirim (SMTP)</span>
                                <?php elseif ($el['status'] === 'simulated'): ?>
                                    <span class="badge bg-info-subtle text-info border"><i class="fas fa-info-circle me-1"></i> Tersimulasi (Tercatat)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border"><i class="fas fa-times-circle me-1"></i> Gagal</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalEmail<?= $el['id'] ?>">
                                    <i class="fas fa-eye me-1"></i> Lihat Email
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Preview Email (diisolasi menggunakan iframe srcdoc agar CSS email tidak merusak layout halaman) -->
<?php if (!empty($email_logs)): ?>
    <?php foreach ($email_logs as $el): ?>
        <div class="modal fade" id="modalEmail<?= $el['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable text-start">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold"><i class="fas fa-envelope text-primary me-2"></i> Preview Isi Notifikasi Email</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="p-3 bg-light border-bottom small text-muted">
                            <div><strong>Penerima:</strong> <span class="text-dark fw-semibold"><?= htmlspecialchars($el['recipients']) ?></span></div>
                            <div><strong>Subjek:</strong> <?= htmlspecialchars($el['subject']) ?></div>
                            <div><strong>Waktu Dibuat:</strong> <?= date('d M Y, H:i:s', strtotime($el['created_at'])) ?> WIB</div>
                            <div><strong>Status Pengiriman:</strong> <span class="badge bg-dark"><?= strtoupper($el['status']) ?></span></div>
                        </div>
                        <div class="p-3">
                            <div class="border rounded bg-white overflow-hidden">
                                <iframe
                                    srcdoc="<?= htmlspecialchars($el['summary_html'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-100 border-0 d-block"
                                    style="height: 520px;"
                                    sandbox=""
                                    loading="lazy"
                                    title="Preview Email #<?= (int)$el['id'] ?>"
                                ></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

@include('layouts.footer')
