@php
$pageTitle = 'Permintaan Pembelian (Purchase Requisition)';
@endphp
@include('layouts.header')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Permintaan Pembelian (Purchase Requisition)</h3>
        <p class="text-muted small mb-0">
            Daftar pengajuan kebutuhan bahan baku yang bersumber dari Rekomendasi AI Gemini, Reorder Point, maupun pengajuan manual PPIC.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createPrModal">
            <i class="fas fa-plus-circle me-1"></i> Buat PR Manual
        </button>
        <a href="/ai/forecast" class="btn btn-dark btn-sm px-3 shadow-sm">
            <i class="fas fa-wand-magic-sparkles me-1 text-warning"></i> Rekomendasi AI
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i> Daftar Purchase Requisition (PR)</span>
        <span class="badge bg-light text-muted border">Total: <?= count($prs) ?> Pengajuan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>No. PR</th>
                    <th>Sumber Pengajuan</th>
                    <th>Prioritas</th>
                    <th>Tgl Dibutuhkan</th>
                    <th class="text-end">Est. Total Biaya</th>
                    <th>Pemohon</th>
                    <th>Disetujui Oleh</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi Alur</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($prs)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Belum ada Purchase Requisition yang diajukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prs as $pr): ?>
                        <tr>
                            <td class="font-monospace fw-semibold text-primary">
                                <?= htmlspecialchars($pr['pr_number']) ?>
                            </td>
                            <td>
                                <?php if ($pr['source'] === 'ai_recommendation'): ?>
                                    <span class="badge ai-badge rounded-pill px-2 py-1"><i class="fas fa-wand-magic-sparkles me-1"></i> Rekomendasi AI</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Manual PPIC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pr['priority'] === 'critical'): ?>
                                    <span class="badge bg-danger">Kritis</span>
                                <?php elseif ($pr['priority'] === 'urgent'): ?>
                                    <span class="badge bg-warning text-dark">Mendesak</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-muted"><?= $pr['required_date'] ? date('d M Y', strtotime($pr['required_date'])) : '-' ?></td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                Rp <?= number_format((float)$pr['total_estimated_cost'], 0, ',', '.') ?>
                            </td>
                            <td><small><?= htmlspecialchars($pr['requester_name'] ?? 'PPIC') ?></small></td>
                            <td>
                                <?php if ($pr['approved_by']): ?>
                                    <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($pr['approver_name']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Menunggu Review</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $statusBadge = match($pr['status']) {
                                    'draft' => 'bg-secondary-subtle text-secondary border',
                                    'submitted' => 'bg-warning-subtle text-warning-emphasis border',
                                    'approved' => 'bg-success-subtle text-success border',
                                    'rejected' => 'bg-danger-subtle text-danger border',
                                    'ordered' => 'bg-info-subtle text-info border',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?> px-2 py-1">
                                    <?= strtoupper($pr['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($pr['status'] === 'submitted'): ?>
                                        <a href="/purchasing/pr/approve?id=<?= $pr['id'] ?>" class="btn btn-success" onclick="return confirm('Setujui Purchase Requisition ini sebagai Manager?')" title="Setujui PR (Approval)">
                                            <i class="fas fa-check me-1"></i> Setujui
                                        </a>
                                        <a href="/purchasing/pr/delete?id=<?= $pr['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Yakin ingin membatalkan & menghapus pengajuan PR ini?')" title="Hapus PR">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php elseif ($pr['status'] === 'approved'): ?>
                                        <a href="/purchasing/po/generate?pr_id=<?= $pr['id'] ?>" class="btn btn-primary" onclick="return confirm('Vendor telah disetujui? Terbitkan Purchase Order resmi ke Supplier & kirim notifikasi ke PPIC?')" title="Terbitkan PO Resmi">
                                            <i class="fas fa-file-contract me-1"></i> Terbitkan PO
                                        </a>
                                        <a href="/purchasing/pr/delete?id=<?= $pr['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus PR ini?')" title="Hapus PR">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php elseif ($pr['status'] === 'ordered'): ?>
                                        <span class="text-muted small"><i class="fas fa-check-double text-info"></i> Sudah Terbit PO</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Create Manual PR -->
<div class="modal fade" id="createPrModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/purchasing/pr/create" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i> Pengajuan Purchase Requisition (PR)</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Bahan Baku Kimia</label>
                        <select name="material_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Bahan Baku --</option>
                            <?php foreach ($materials as $m): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['code']) ?> &mdash; <?= htmlspecialchars($m['name']) ?> (Harga Est: Rp <?= number_format($m['unit_cost'], 0) ?>/<?= $m['unit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jumlah Kuantitas</label>
                            <input type="number" step="0.01" name="quantity" class="form-control form-control-sm" placeholder="Contoh: 5000" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tingkat Prioritas</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="normal">Normal</option>
                                <option value="urgent">Mendesak (Urgent)</option>
                                <option value="critical">Kritis (Emergency)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Tanggal Dibutuhkan</label>
                        <input type="date" name="required_date" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Justifikasi / Catatan Alasan Pembelian</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Contoh: Antisipasi lonjakan jadwal produksi koagulan minggu ke-3..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Ajukan PR</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layouts.footer')
