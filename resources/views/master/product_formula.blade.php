@php
$pageTitle = 'Kebutuhan Formula - ' . $product['name'];
$compositionPercentage = $bom ? (float) $bom['total_formula_percentage'] : 0;
$compositionComplete = abs($compositionPercentage - 100) <= 0.01;
$defaultOutput = $bom ? (float) $bom['default_target_output'] : 0;
$catalogFormulaActive = $bom && !empty($bom['source_formula_id']);
@endphp
@include('layouts.header')

<div class="material-detail-page product-formula-page">
    <a href="/master/products" class="material-detail-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Produk Jadi
    </a>

    <header class="material-detail-heading">
        <div>
            <span class="material-detail-code"><?= htmlspecialchars($product['code']) ?></span>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p>Perhitungan kebutuhan material untuk produk jadi ini.</p>
        </div>
        <span class="formula-status-badge <?= $bom ? 'is-active' : 'is-draft' ?>">
            <?= $bom ? 'BOM aktif' : 'Belum ada BOM aktif' ?>
        </span>
    </header>

    <section class="material-detail-summary">
        <div>
            <span>Stok Finish Good</span>
            <strong><?= number_format((float) $product['finished_good_stock'], 2, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?></strong>
        </div>
        <div>
            <span>Batch standar produk</span>
            <strong><?= number_format((float) $product['standard_batch_size'], 2, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?></strong>
        </div>
        <div>
            <span>Formula aktif</span>
            <strong><?= $bom ? htmlspecialchars($bom['version']) : '-' ?></strong>
        </div>
        <div>
            <span>Sumber katalog</span>
            <strong><?= htmlspecialchars($bom ? ($bom['source_formula_code'] ?? 'BOM manual') : '-') ?></strong>
        </div>
    </section>

    <section class="material-detail-section">
        <div class="material-detail-section-heading">
            <div>
                <h2>Atur formula dari halaman Finish Good</h2>
                <p>Pilih cara menyusun formula. Material yang dipilih berasal dari master Raw Material.</p>
            </div>
        </div>

        <?php if (empty($materials)): ?>
            <div class="alert alert-warning">Belum ada material aktif. Daftarkan bahan baku terlebih dahulu di Data Raw Material.</div>
        <?php else: ?>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $catalogFormulaActive ? '' : 'active' ?>" id="manualFormulaTabButton" data-bs-toggle="tab" data-bs-target="#manualFormulaTab" type="button" role="tab">
                        Susun manual
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $catalogFormulaActive ? 'active' : '' ?>" id="catalogFormulaTabButton" data-bs-toggle="tab" data-bs-target="#catalogFormulaTab" type="button" role="tab">
                        Pilih katalog Vaslina
                    </button>
                </li>
            </ul>
            <div class="tab-content product-formula-editor">
                <div class="tab-pane fade <?= $catalogFormulaActive ? '' : 'show active' ?>" id="manualFormulaTab" role="tabpanel" aria-labelledby="manualFormulaTabButton">
                    <form action="/master/products/formula/store" method="POST" id="manualFormulaForm">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-4">
                                <label for="manualFormulaVersion" class="form-label fw-semibold">Versi formula</label>
                                <input
                                    id="manualFormulaVersion"
                                    type="text"
                                    name="version"
                                    class="form-control"
                                    maxlength="20"
                                    value="<?= htmlspecialchars($bom && empty($bom['source_formula_id']) ? $bom['version'] : 'manual-v1', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-8">
                                <div class="product-formula-basis">
                                    Basis <?= number_format((float) $product['standard_batch_size'], 2, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?> per batch.
                                    Satuan bahan harus sama dengan satuan produk.
                                </div>
                            </div>
                        </div>

                        <div id="manualCompositionRows">
                            <?php
                            $manualInitialItems = $bom && empty($bom['source_formula_id']) ? $bom['items'] : [];
                            $manualRowCount = max(3, count($manualInitialItems));
                            for ($rowIndex = 0; $rowIndex < $manualRowCount; $rowIndex++):
                                $initialItem = $manualInitialItems[$rowIndex] ?? null;
                            ?>
                                <div class="row g-2 align-items-end mb-2 product-formula-manual-row">
                                    <div class="col-md-7">
                                        <label class="form-label small text-muted">Material</label>
                                        <select name="items[<?= $rowIndex ?>][material_id]" class="form-select form-select-sm">
                                            <option value="">-- Pilih material --</option>
                                            <?php foreach ($materials as $material): ?>
                                                <?php $sameUnit = strcasecmp(trim($material['unit']), trim($product['unit'])) === 0; ?>
                                                <option
                                                    value="<?= (int) $material['id'] ?>"
                                                    <?= $initialItem && (int) $initialItem['material_id'] === (int) $material['id'] ? 'selected' : '' ?>
                                                    <?= $sameUnit ? '' : 'disabled' ?>
                                                >
                                                    <?= htmlspecialchars($material['code'] . ' - ' . $material['name'] . ' (' . $material['unit'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">Persentase</label>
                                        <div class="input-group input-group-sm">
                                            <input
                                                type="number"
                                                name="items[<?= $rowIndex ?>][percentage]"
                                                class="form-control"
                                                min="0.0001"
                                                max="100"
                                                step="0.0001"
                                                value="<?= $initialItem ? htmlspecialchars(number_format((float) $initialItem['formula_percentage'], 4, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>"
                                                placeholder="0.0000"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remove-manual-row>Hapus bahan</button>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="addManualMaterialRow">Tambah bahan</button>
                                <span class="small text-muted ms-2" id="manualCompositionTotal">Total persentase: 0%</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan formula manual</button>
                        </div>
                        <div class="small text-warning mt-2" id="manualCompositionWarning" hidden>
                            Total tidak 100%; nilai akan disimpan apa adanya dan tidak dinormalisasi.
                        </div>
                        <?php if ($bom): ?>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="replace_active_bom" value="1" id="replaceManualBom" required>
                                <label class="form-check-label" for="replaceManualBom">
                                    Ganti BOM aktif <?= htmlspecialchars($bom['version']) ?> dengan formula manual ini.
                                </label>
                            </div>
                        <?php endif; ?>
                    </form>

                    <template id="manualMaterialRowTemplate">
                        <div class="row g-2 align-items-end mb-2 product-formula-manual-row">
                            <div class="col-md-7">
                                <label class="form-label small text-muted">Material</label>
                                <select name="items[__INDEX__][material_id]" class="form-select form-select-sm">
                                    <option value="">-- Pilih material --</option>
                                    <?php foreach ($materials as $material): ?>
                                        <?php $sameUnit = strcasecmp(trim($material['unit']), trim($product['unit'])) === 0; ?>
                                        <option value="<?= (int) $material['id'] ?>" <?= $sameUnit ? '' : 'disabled' ?>>
                                            <?= htmlspecialchars($material['code'] . ' - ' . $material['name'] . ' (' . $material['unit'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Persentase</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="items[__INDEX__][percentage]" class="form-control" min="0.0001" max="100" step="0.0001" placeholder="0.0000">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remove-manual-row>Hapus bahan</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="tab-pane fade <?= $catalogFormulaActive ? 'show active' : '' ?>" id="catalogFormulaTab" role="tabpanel" aria-labelledby="catalogFormulaTabButton">
                    <?php if (empty($formula_catalog)): ?>
                        <div class="alert alert-warning mb-0">Katalog formula Vaslina belum tersedia.</div>
                    <?php else: ?>
                        <form action="/master/products/formula/link-catalog" method="POST" id="catalogFormulaForm">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <div class="mb-3">
                                <label for="catalogFormulaSelect" class="form-label fw-semibold">Varian formula katalog</label>
                                <select id="catalogFormulaSelect" name="formula_id" class="form-select" required>
                                    <option value="">-- Pilih varian formula --</option>
                                    <?php foreach ($formula_catalog as $catalogFormula): ?>
                                        <option
                                            value="<?= (int) $catalogFormula['id'] ?>"
                                            <?= $bom && (int) ($bom['source_formula_id'] ?? 0) === (int) $catalogFormula['id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($catalogFormula['product_name'] . ' - ' . $catalogFormula['formula_code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="alert alert-info py-2" id="catalogFormulaSummary">
                                Pilih varian untuk melihat bahan dan persentase dari workbook.
                            </div>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Bahan katalog</th>
                                            <th class="text-end">Persentase</th>
                                            <th>Padankan ke material</th>
                                        </tr>
                                    </thead>
                                    <tbody id="catalogFormulaItems"></tbody>
                                </table>
                            </div>
                            <?php if ($bom): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="replace_active_bom" value="1" id="replaceCatalogBom" required>
                                    <label class="form-check-label" for="replaceCatalogBom">
                                        Ganti BOM aktif <?= htmlspecialchars($bom['version']) ?> dengan varian katalog ini.
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm">Gunakan formula katalog</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$bom): ?>
        <section class="material-detail-section">
            <div class="alert alert-warning mb-0">Produk ini belum memiliki BOM aktif. Simpan formula manual atau pilih formula katalog di atas untuk mulai menghitung kebutuhan.</div>
        </section>
    <?php else: ?>
        <?php if (!$compositionComplete): ?>
            <div class="alert alert-warning">
                Total komposisi formula <strong><?= number_format($compositionPercentage, 4, ',', '.') ?>%</strong>, bukan 100%.
                Angka ditampilkan dan dihitung apa adanya; formula tidak dinormalisasi. Pastikan total ini memang sesuai sumber sebelum dipakai untuk produksi.
            </div>
        <?php endif; ?>

        <section class="material-detail-section">
            <div class="material-detail-section-heading">
                <div>
                    <h2>Hitung kebutuhan material</h2>
                    <p>Masukkan target hasil jadi; kebutuhan bahan diskalakan menurut batch BOM dan yield.</p>
                </div>
            </div>
            <div class="row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="targetOutput" class="form-label fw-semibold">Target produksi</label>
                    <div class="input-group">
                        <input
                            id="targetOutput"
                            type="number"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            value="<?= htmlspecialchars(number_format($defaultOutput, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-batch-size="<?= htmlspecialchars((string) $bom['batch_size'], ENT_QUOTES, 'UTF-8') ?>"
                            data-yield="<?= htmlspecialchars((string) $bom['yield_pct'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                        <span class="input-group-text"><?= htmlspecialchars($product['unit']) ?></span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="product-formula-basis">
                        Batch BOM <?= number_format((float) $bom['batch_size'], 2, ',', '.') ?> <?= htmlspecialchars($bom['unit']) ?>
                        · Yield <?= number_format((float) $bom['yield_pct'], 2, ',', '.') ?>%
                        · Estimasi hasil per batch <?= number_format((float) $bom['expected_batch_output'], 2, ',', '.') ?> <?= htmlspecialchars($product['unit']) ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table material-storage-table align-middle mb-0" id="formulaRequirementsTable">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th class="text-end">Komposisi</th>
                            <th class="text-end">Kebutuhan</th>
                            <th class="text-end">Stok tersedia</th>
                            <th class="text-end">Kekurangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bom['items'] as $item): ?>
                            <tr
                                data-requirement-row
                                data-quantity-per-batch="<?= htmlspecialchars((string) $item['quantity_per_batch'], ENT_QUOTES, 'UTF-8') ?>"
                                data-available-stock="<?= htmlspecialchars((string) $item['available_stock'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td>
                                    <strong><?= htmlspecialchars($item['material_name']) ?></strong>
                                    <div class="material-storage-meta">
                                        <?= htmlspecialchars($item['material_code']) ?> · <?= htmlspecialchars($item['material_unit']) ?>
                                    </div>
                                </td>
                                <td class="text-end font-monospace"><?= number_format((float) $item['formula_percentage'], 4, ',', '.') ?>%</td>
                                <td class="text-end font-monospace" data-required-quantity data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= number_format((float) $item['required_quantity'], 2, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= number_format((float) $item['available_stock'], 2, ',', '.') ?> <?= htmlspecialchars($item['material_unit']) ?>
                                </td>
                                <td class="text-end font-monospace" data-shortage-quantity data-unit="<?= htmlspecialchars($item['material_unit'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= number_format((float) $item['shortage_quantity'], 2, ',', '.') ?> <?= htmlspecialchars($item['material_unit']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0">
                Ini adalah simulasi kebutuhan dan kekurangan berdasarkan stok material yang tercatat; perhitungan ini tidak mengubah stok atau membuat transaksi produksi.
            </p>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const materialList = <?= json_encode($materials, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    const formulaCatalog = <?= json_encode($formula_catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    const productUnit = <?= json_encode($product['unit'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    const productUnitKey = String(productUnit).trim().toLowerCase();
    const manualRows = document.getElementById('manualCompositionRows');
    const manualTemplate = document.getElementById('manualMaterialRowTemplate');
    const addManualRowButton = document.getElementById('addManualMaterialRow');
    const manualTotal = document.getElementById('manualCompositionTotal');
    const manualWarning = document.getElementById('manualCompositionWarning');
    let nextManualIndex = manualRows ? manualRows.querySelectorAll('.product-formula-manual-row').length : 0;

    function updateManualTotal() {
        if (!manualRows || !manualTotal) return;
        const total = Array.from(manualRows.querySelectorAll('input[name$="[percentage]"]'))
            .reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        manualTotal.textContent = `Total persentase: ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(total)}%`;
        if (manualWarning) manualWarning.hidden = Math.abs(total - 100) <= 0.01;
    }

    if (addManualRowButton && manualTemplate && manualRows) {
        addManualRowButton.addEventListener('click', () => {
            const html = manualTemplate.innerHTML.replace(/__INDEX__/g, String(nextManualIndex++));
            manualRows.insertAdjacentHTML('beforeend', html);
            updateManualTotal();
        });
        manualRows.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-manual-row]');
            if (button) {
                button.closest('.product-formula-manual-row').remove();
                updateManualTotal();
            }
        });
        manualRows.addEventListener('input', updateManualTotal);
        manualRows.addEventListener('change', updateManualTotal);
        updateManualTotal();
    }

    const catalogSelect = document.getElementById('catalogFormulaSelect');
    const catalogItemsBody = document.getElementById('catalogFormulaItems');
    const catalogSummary = document.getElementById('catalogFormulaSummary');
    function renderCatalogItems() {
        if (!catalogSelect || !catalogItemsBody || !catalogSummary) return;
        catalogItemsBody.replaceChildren();
        const selectedFormula = formulaCatalog.find(formula => String(formula.id) === catalogSelect.value);
        if (!selectedFormula) {
            catalogSummary.textContent = 'Pilih varian untuk melihat bahan dan persentase dari workbook.';
            catalogSummary.classList.remove('alert-warning');
            catalogSummary.classList.add('alert-info');
            return;
        }

        const totalPercentage = Number(selectedFormula.total_fraction) * 100;
        const totalMatches = Math.abs(totalPercentage - 100) <= 0.01;
        catalogSummary.textContent = `Formula ${selectedFormula.formula_code}: total ${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 4, maximumFractionDigits: 4 }).format(totalPercentage)}%. Nilai sumber tidak dinormalisasi.`;
        catalogSummary.classList.toggle('alert-info', totalMatches);
        catalogSummary.classList.toggle('alert-warning', !totalMatches);
        selectedFormula.items.forEach(item => {
            const row = document.createElement('tr');
            const materialCell = document.createElement('td');
            materialCell.textContent = item.material_code;
            const percentageCell = document.createElement('td');
            percentageCell.className = 'text-end font-monospace';
            percentageCell.textContent = `${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 4, maximumFractionDigits: 4 }).format(Number(item.fraction) * 100)}%`;
            const selectCell = document.createElement('td');
            const materialSelect = document.createElement('select');
            materialSelect.className = 'form-select form-select-sm';
            materialSelect.name = `material_ids[${item.id}]`;
            materialSelect.required = true;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- Pilih material --';
            materialSelect.appendChild(placeholder);

            const matchingMaterials = materialList.filter(material => {
                const matches = String(material.code).trim().toLowerCase() === String(item.material_code).trim().toLowerCase()
                    || String(material.name).trim().toLowerCase() === String(item.material_code).trim().toLowerCase();
                return matches && String(material.unit).trim().toLowerCase() === productUnitKey;
            });
            materialList.forEach(material => {
                const option = document.createElement('option');
                option.value = material.id;
                option.textContent = `${material.code} - ${material.name} (${material.unit})`;
                if (String(material.unit).trim().toLowerCase() !== productUnitKey) option.disabled = true;
                if (matchingMaterials.length === 1 && String(matchingMaterials[0].id) === String(material.id)) option.selected = true;
                materialSelect.appendChild(option);
            });
            selectCell.appendChild(materialSelect);
            row.append(materialCell, percentageCell, selectCell);
            catalogItemsBody.appendChild(row);
        });
    }

    if (catalogSelect) {
        catalogSelect.addEventListener('change', renderCatalogItems);
        renderCatalogItems();
    }

    const targetInput = document.getElementById('targetOutput');
    const table = document.getElementById('formulaRequirementsTable');
    if (!targetInput || !table) return;

    const numberFormat = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function updateRequirements() {
        const target = Number(targetInput.value);
        const batchSize = Number(targetInput.dataset.batchSize);
        const yieldPercentage = Number(targetInput.dataset.yield);
        if (!Number.isFinite(target) || target <= 0 || !Number.isFinite(batchSize) || batchSize <= 0
            || !Number.isFinite(yieldPercentage) || yieldPercentage <= 0) {
            table.querySelectorAll('[data-required-quantity], [data-shortage-quantity]').forEach(cell => {
                cell.textContent = '-';
            });
            return;
        }

        const scale = target / (batchSize * yieldPercentage / 100);
        table.querySelectorAll('[data-requirement-row]').forEach(row => {
            const perBatch = Number(row.dataset.quantityPerBatch);
            const available = Number(row.dataset.availableStock);
            if (!Number.isFinite(perBatch) || !Number.isFinite(available)) return;
            const required = perBatch * scale;
            const shortage = Math.max(0, required - available);
            const requiredCell = row.querySelector('[data-required-quantity]');
            const shortageCell = row.querySelector('[data-shortage-quantity]');
            requiredCell.textContent = `${numberFormat.format(required)} ${requiredCell.dataset.unit}`;
            shortageCell.textContent = `${numberFormat.format(shortage)} ${shortageCell.dataset.unit}`;
        });
    }

    targetInput.addEventListener('input', updateRequirements);
});
</script>

@include('layouts.footer')
