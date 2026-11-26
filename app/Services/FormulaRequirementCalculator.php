<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class FormulaRequirementCalculator
{
    public static function calculate(
        float $targetOutput,
        float $batchSize,
        float $yieldPercentage,
        array $items
    ): array {
        if (!is_finite($targetOutput) || $targetOutput <= 0
            || !is_finite($batchSize) || $batchSize <= 0
            || !is_finite($yieldPercentage) || $yieldPercentage <= 0 || $yieldPercentage > 100) {
            throw new InvalidArgumentException('Target produksi, basis batch, atau yield formula tidak valid.');
        }

        $expectedBatchOutput = $batchSize * ($yieldPercentage / 100);
        if (!is_finite($expectedBatchOutput) || $expectedBatchOutput <= 0) {
            throw new InvalidArgumentException('Hasil batch yang diharapkan tidak valid.');
        }
        $scale = $targetOutput / $expectedBatchOutput;
        foreach ($items as &$item) {
            if (!\is_array($item)) {
                throw new InvalidArgumentException('Daftar material formula tidak valid.');
            }
            $quantityPerBatch = $item['quantity_per_batch'] ?? null;
            $availableStock = $item['available_stock'] ?? 0;
            if (!is_numeric($quantityPerBatch) || !is_finite((float) $quantityPerBatch) || (float) $quantityPerBatch < 0
                || !is_numeric($availableStock) || !is_finite((float) $availableStock) || (float) $availableStock < 0) {
                throw new InvalidArgumentException('Jumlah formula atau stok material tidak valid.');
            }

            $requiredQuantity = (float) $quantityPerBatch * $scale;
            if (!is_finite($requiredQuantity)) {
                throw new InvalidArgumentException('Kebutuhan material melebihi batas perhitungan.');
            }
            $item['required_quantity'] = $requiredQuantity;
            $item['shortage_quantity'] = max(0.0, $requiredQuantity - (float) $availableStock);
        }
        unset($item);

        return [
            'expected_batch_output' => $expectedBatchOutput,
            'scale' => $scale,
            'items' => $items,
        ];
    }
}
