<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Database\Database;

class PriceForecastService
{
    private const MONTHS_ID = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
    ];

    private const MONTHS_FULL_ID = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    /**
     * Inisialisasi riwayat harga (hanya berasal dari input harga pasar aktual).
     */
    public static function seedIfEmpty(): void
    {
        // Clean slate: riwayat harga hanya berasal dari input aktual / master bahan yang diinput user
    }

    /**
     * Catat atau perbarui harga pasar aktual untuk bahan baku pada bulan tertentu.
     */
    public static function recordMarketPrice(
        int $materialId,
        string $priceMonth,
        float $marketPrice,
        string $source,
        string $notes,
        int $userId,
        bool $syncMasterCost = true
    ): int {
        if ($materialId <= 0 || $marketPrice <= 0) {
            throw new \InvalidArgumentException("Bahan baku dan harga pasar harus valid (> 0).");
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $priceMonth)) {
            $priceMonth = date('Y-m');
        }

        $recordedDate = ($priceMonth === date('Y-m')) ? date('Y-m-d') : ($priceMonth . '-15');
        $cleanSource = trim($source) !== '' ? trim($source) : 'Input Harga Pasar Manual';
        $cleanNotes = trim($notes);

        $existing = Database::selectOne(
            "SELECT id FROM material_price_histories WHERE material_id = :mid AND price_month = :pm ORDER BY id DESC LIMIT 1",
            ['mid' => $materialId, 'pm' => $priceMonth]
        );

        if ($existing) {
            Database::update('material_price_histories', [
                'recorded_date' => $recordedDate,
                'market_price' => $marketPrice,
                'source' => $cleanSource,
                'notes' => $cleanNotes,
            ], 'id = :id', ['id' => $existing['id']]);
            $recordId = (int) $existing['id'];
        } else {
            $recordId = Database::insert('material_price_histories', [
                'material_id' => $materialId,
                'price_month' => $priceMonth,
                'recorded_date' => $recordedDate,
                'market_price' => $marketPrice,
                'source' => $cleanSource,
                'notes' => $cleanNotes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Sinkronkan harga satuan master bahan jika bulan berjalan atau dicentang
        if ($syncMasterCost) {
            Database::update('materials', [
                'unit_cost' => $marketPrice,
            ], 'id = :id', ['id' => $materialId]);
        }

        $mat = Database::selectOne("SELECT name, code, unit FROM materials WHERE id = :id", ['id' => $materialId]);
        $matLabel = $mat ? "{$mat['name']} ({$mat['code']})" : "ID #{$materialId}";
        $formattedPrice = number_format($marketPrice, 0, ',', '.');

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'RECORD_MARKET_PRICE',
            'module' => 'AI Price Forecast',
            'description' => "Mencatat harga pasar {$matLabel} periode {$priceMonth} sebesar Rp {$formattedPrice}/" . ($mat['unit'] ?? 'Kg') . " (Sumber: {$cleanSource}).",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $recordId;
    }

    /**
     * Hitung peramalan harga pasar bulan depan untuk satu bahan baku.
     */
    public static function forecastMaterialPrice(int $materialId, float $recommendedQty = 0.0, bool $useGemini = false): array
    {
        self::seedIfEmpty();

        $material = Database::selectOne("
            SELECT m.*, c.name as category_name, s.name as supplier_name
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN suppliers s ON s.id = m.preferred_supplier_id
            WHERE m.id = :id
        ", ['id' => $materialId]);

        if (!$material) {
            throw new \RuntimeException("Bahan baku tidak ditemukan.");
        }

        $history = Database::select("
            SELECT * FROM material_price_histories
            WHERE material_id = :mid
            ORDER BY price_month ASC
            LIMIT 12
        ", ['mid' => $materialId]);

        // Fallback jika belum ada histori sama sekali
        $currentPrice = (float) ($material['unit_cost'] ?? 0);
        if (!empty($history)) {
            $lastRow = end($history);
            $currentPrice = (float) $lastRow['market_price'];
            $lastMonthStr = (string) $lastRow['price_month'];
        } else {
            $lastMonthStr = date('Y-m');
            $history = [[
                'price_month' => $lastMonthStr,
                'recorded_date' => date('Y-m-d'),
                'market_price' => $currentPrice,
                'source' => 'Harga Master Bahan',
                'notes' => 'Data tunggal',
            ]];
        }

        $nextMonthTs = strtotime($lastMonthStr . '-01 +1 month');
        $nextMonthYm = date('Y-m', $nextMonthTs);
        $nextMonthLabel = self::formatMonthFull($nextMonthYm);
        $nextMonthShort = self::formatMonthShort($nextMonthYm);

        $prices = array_map(fn($h) => (float) $h['market_price'], $history);
        $n = count($prices);

        // 1. Regresi Linear (OLS) atas indeks waktu 0..(n-1)
        if ($n >= 2) {
            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumX2 = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $prices[$i];
                $sumXY += $i * $prices[$i];
                $sumX2 += $i * $i;
            }
            $denom = ($n * $sumX2) - ($sumX * $sumX);
            $slope = ($denom != 0.0) ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0.0;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
            $linearForecast = $intercept + ($slope * $n);

            // 2. Momentum perubahan bulanan terbaru (Weighted Moving Average dari delta MoM)
            $deltas = [];
            $pctChanges = [];
            for ($i = 1; $i < $n; $i++) {
                $deltas[] = $prices[$i] - $prices[$i - 1];
                if ($prices[$i - 1] > 0) {
                    $pctChanges[] = (($prices[$i] - $prices[$i - 1]) / $prices[$i - 1]) * 100.0;
                }
            }

            $recentDeltas = array_slice($deltas, -3);
            $weights = match (count($recentDeltas)) {
                3 => [0.20, 0.30, 0.50],
                2 => [0.35, 0.65],
                default => [1.0],
            };
            $weightedDelta = 0.0;
            foreach ($recentDeltas as $idx => $d) {
                $weightedDelta += $d * $weights[$idx];
            }
            $momentumForecast = $currentPrice + $weightedDelta;

            // 3. Blended Forecast (60% Tren Linear + 40% Momentum Terkini)
            $rawForecast = (0.60 * $linearForecast) + (0.40 * $momentumForecast);

            // Batasi perubahan ekstrem (±20% MoM) agar realistis
            $minClamp = $currentPrice * 0.80;
            $maxClamp = $currentPrice * 1.20;
            $forecastPrice = max($minClamp, min($maxClamp, $rawForecast));
            $forecastPrice = round($forecastPrice / 10) * 10;

            // 4. Volatilitas (Standar Deviasi % perubahan bulanan) -> Rentang Estimasi & Keyakinan
            $meanPct = !empty($pctChanges) ? array_sum($pctChanges) / count($pctChanges) : 0.0;
            $variance = 0.0;
            foreach ($pctChanges as $pc) {
                $variance += pow($pc - $meanPct, 2);
            }
            $stdDevPct = count($pctChanges) > 1 ? sqrt($variance / (count($pctChanges) - 1)) : 1.5;
            $bandPct = max(1.5, min(8.0, round($stdDevPct * 1.35 + 1.0, 1)));
            $confidencePct = (int) round(max(72, min(94, 93 - ($stdDevPct * 2.8) + min(4, $n / 2))));
        } else {
            $forecastPrice = $currentPrice;
            $bandPct = 3.0;
            $confidencePct = 70;
            $stdDevPct = 2.0;
        }

        $lowPrice = round(($forecastPrice * (1 - $bandPct / 100)) / 10) * 10;
        $highPrice = round(($forecastPrice * (1 + $bandPct / 100)) / 10) * 10;

        $priceDiff = $forecastPrice - $currentPrice;
        $priceChangePct = $currentPrice > 0 ? round(($priceDiff / $currentPrice) * 100, 2) : 0.0;

        // Klasifikasi tren harga dan waktu tindak lanjut
        if ($priceChangePct >= 2.5) {
            $trendDirection = 'naik';
            $trendLabel = 'Naik Signifikan';
            $trendBadge = 'danger';
            $signalCode = 'LOCK_NOW';
            $signalLabel = 'Lock Harga / Beli Sekarang';
            $signalBadge = 'danger';
            $signalDetail = "Harga diproyeksikan naik +{$priceChangePct}% pada {$nextMonthLabel}. Pertimbangkan meninjau kebutuhan dan harga bulan ini.";
        } elseif ($priceChangePct >= 0.8) {
            $trendDirection = 'naik';
            $trendLabel = 'Naik Moderat';
            $trendBadge = 'warning';
            $signalCode = 'BUY_EARLY';
            $signalLabel = 'Pertimbangkan Beli Lebih Awal';
            $signalBadge = 'warning';
            $signalDetail = "Tren harga merangkak naik (+{$priceChangePct}%). Pertimbangkan meninjau kebutuhan produksi bulan depan lebih awal.";
        } elseif ($priceChangePct <= -1.5) {
            $trendDirection = 'turun';
            $trendLabel = 'Turun / Melandai';
            $trendBadge = 'success';
            $signalCode = 'BUY_STAGED';
            $signalLabel = 'Beli Bertahap (Harga Turun)';
            $signalBadge = 'success';
            $signalDetail = "Harga pasar diperkirakan melandai {$priceChangePct}% di {$nextMonthLabel}. Beli secukupnya untuk kebutuhan mendesak, tunda stok berlebih.";
        } else {
            $trendDirection = 'stabil';
            $trendLabel = 'Stabil';
            $trendBadge = 'info';
            $signalCode = 'BUY_NORMAL';
            $signalLabel = 'Beli Sesuai Jadwal ROP';
            $signalBadge = 'info';
            $signalDetail = "Harga pasar relatif stabil ({$priceChangePct}%). Lakukan pengadaan normal sesuai jadwal ROP dan safety stock.";
        }

        // Dampak anggaran terhadap kuantitas rekomendasi Q
        $currentBudget = $recommendedQty * $currentPrice;
        $nextMonthBudget = $recommendedQty * $forecastPrice;
        $budgetDelta = $nextMonthBudget - $currentBudget; // positif = lebih mahal bulan depan (hemat jika beli sekarang)

        // Format seri grafik untuk Chart.js
        $chartLabels = [];
        $chartHistorical = [];
        foreach ($history as $h) {
            $chartLabels[] = self::formatMonthShort((string) $h['price_month']);
            $chartHistorical[] = (float) $h['market_price'];
        }
        $chartLabels[] = $nextMonthShort . ' (Ramalan)';

        $result = [
            'material_id' => $materialId,
            'material' => $material,
            'history' => $history,
            'current_month' => $lastMonthStr,
            'current_month_label' => self::formatMonthFull($lastMonthStr),
            'next_month' => $nextMonthYm,
            'next_month_label' => $nextMonthLabel,
            'next_month_short' => $nextMonthShort,
            'current_price' => $currentPrice,
            'forecast_price' => $forecastPrice,
            'low_price' => $lowPrice,
            'high_price' => $highPrice,
            'price_diff_rp' => $priceDiff,
            'price_change_pct' => $priceChangePct,
            'volatility_pct' => round($stdDevPct, 2),
            'confidence_pct' => $confidencePct,
            'trend_direction' => $trendDirection,
            'trend_label' => $trendLabel,
            'trend_badge' => $trendBadge,
            'signal_code' => $signalCode,
            'signal_label' => $signalLabel,
            'signal_badge' => $signalBadge,
            'signal_detail' => $signalDetail,
            'recommended_qty' => $recommendedQty,
            'current_budget' => $currentBudget,
            'next_month_budget' => $nextMonthBudget,
            'budget_delta' => $budgetDelta,
            'chart_labels' => $chartLabels,
            'chart_historical' => $chartHistorical,
            'model_method' => 'Hybrid OLS Trend + Weighted Momentum',
        ];

        // Narasi analisis pasar (Gemini API Live jika aktif & diminta, atau Heuristik Industri Kimia)
        $apiKey = AppConfig::getGeminiApiKey();
        if ($useGemini && !empty($apiKey)) {
            $aiInfo = self::callGeminiPriceNarrative($result, $apiKey);
        } else {
            $aiInfo = self::generateLocalPriceNarrative($result);
        }

        $result['ai_narrative'] = $aiInfo['narrative'];
        $result['ai_source'] = $aiInfo['source'];
        $result['market_drivers'] = $aiInfo['drivers'];

        return $result;
    }

    /**
     * Hitung ringkasan ramalan harga bulan depan untuk semua bahan baku.
     */
    public static function forecastAllMaterials(array $recommendations): array
    {
        self::seedIfEmpty();

        $forecasts = [];
        $risingCount = 0;
        $fallingCount = 0;
        $stableCount = 0;
        $sumPct = 0.0;
        $totalCurrentBudget = 0.0;
        $totalNextMonthBudget = 0.0;
        $potentialEarlySavings = 0.0;
        $nextMonthLabel = self::formatMonthFull(date('Y-m', strtotime('first day of +1 month')));

        foreach ($recommendations as $rec) {
            $matId = (int) $rec['material']['id'];
            $qty = (float) ($rec['final_recommended_qty'] ?? 0);
            $pf = self::forecastMaterialPrice($matId, $qty, false);
            $forecasts[$matId] = $pf;

            $nextMonthLabel = $pf['next_month_label'];
            $sumPct += $pf['price_change_pct'];
            $totalCurrentBudget += $pf['current_budget'];
            $totalNextMonthBudget += $pf['next_month_budget'];

            if ($pf['budget_delta'] > 0) {
                $potentialEarlySavings += $pf['budget_delta'];
            }

            if ($pf['trend_direction'] === 'naik') {
                $risingCount++;
            } elseif ($pf['trend_direction'] === 'turun') {
                $fallingCount++;
            } else {
                $stableCount++;
            }
        }

        $count = count($forecasts);
        return [
            'items' => $forecasts,
            'summary' => [
                'next_month_label' => $nextMonthLabel,
                'total_materials' => $count,
                'rising_count' => $risingCount,
                'falling_count' => $fallingCount,
                'stable_count' => $stableCount,
                'avg_change_pct' => $count > 0 ? round($sumPct / $count, 2) : 0.0,
                'total_current_budget' => $totalCurrentBudget,
                'total_next_month_budget' => $totalNextMonthBudget,
                'total_budget_delta' => $totalNextMonthBudget - $totalCurrentBudget,
                'potential_early_savings' => $potentialEarlySavings,
            ],
        ];
    }

    private static function callGeminiPriceNarrative(array $pf, string $apiKey): array
    {
        $mat = $pf['material'];
        $model = AppConfig::getGeminiModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $historyText = implode(', ', array_map(
            fn($h) => "{$h['price_month']}: Rp " . number_format((float) $h['market_price'], 0, ',', '.'),
            $pf['history']
        ));

        $curStr = number_format($pf['current_price'], 0, ',', '.');
        $fcastStr = number_format($pf['forecast_price'], 0, ',', '.');
        $lowStr = number_format($pf['low_price'], 0, ',', '.');
        $highStr = number_format($pf['high_price'], 0, ',', '.');

        $prompt = <<<PROMPT
Anda adalah asisten analisis harga bahan baku untuk PPIC PT DUNIA KIMIA JAYA GRESIK.
Berikan analisis singkat (maksimal 2 paragraf ringkas) mengenai proyeksi harga pasar bahan baku bulan depan:

- Bahan Baku: {$mat['name']} ({$mat['code']})
- Kategori: {$mat['category_name']}
- Supplier Utama: {$mat['supplier_name']}
- Riwayat Harga Bulanan: {$historyText}
- Harga Bulan Ini ({$pf['current_month_label']}): Rp {$curStr}/{$mat['unit']}
- Ramalan Harga Bulan Depan ({$pf['next_month_label']}): Rp {$fcastStr}/{$mat['unit']} (Perubahan: {$pf['price_change_pct']}%, Rentang: Rp {$lowStr} – Rp {$highStr})
- Sinyal Sistem: {$pf['signal_label']}

Fokuskan pada:
1. Faktor pemicu tren harga pasar kimia industri yang relevan (energi, kurs, suplai domestik/impor).
2. Rekomendasi waktu meninjau kebutuhan dan harga bagi PPIC.
Gunakan Bahasa Indonesia formal yang ringkas dan profesional.
PROMPT;

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.35, 'maxOutputTokens' => 400],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 12,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $local = self::generateLocalPriceNarrative($pf);
        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                return [
                    'narrative' => trim($text),
                    'source' => 'Gemini API Live (' . $model . ')',
                    'drivers' => $local['drivers'],
                ];
            }
        }

        return $local;
    }

    private static function generateLocalPriceNarrative(array $pf): array
    {
        $mat = $pf['material'];
        $name = $mat['name'];
        $unit = $mat['unit'];
        $code = strtoupper((string) ($mat['code'] ?? ''));
        $cur = number_format($pf['current_price'], 0, ',', '.');
        $fcast = number_format($pf['forecast_price'], 0, ',', '.');
        $low = number_format($pf['low_price'], 0, ',', '.');
        $high = number_format($pf['high_price'], 0, ',', '.');
        $pct = $pf['price_change_pct'];
        $nextMonth = $pf['next_month_label'];
        $supplier = $mat['supplier_name'] ?: 'supplier rekanan';

        $driversMap = [
            'BB-001' => ['Kenaikan harga alumina & bauksit regional Asia', 'Permintaan koagulan PDAM musim puncak', 'Biaya logistik curah kawasan Gresik'],
            'BB-002' => ['Keseimbangan pasokan pabrik klor-alkali domestik', 'Biaya transportasi tangki khusus asam'],
            'BB-003' => ['Pasokan sulfur/smelter domestik relatif stabil', 'Kontrak jangka menengah pabrik lokal'],
            'BB-004' => ['Kenaikan biaya energi listrik proses elektrolisis klor-alkali', 'Ketatnya pasokan NaOH 48% regional Asia Tenggara', 'Fluktuasi kurs USD/IDR'],
            'BB-005' => ['Koreksi harga Soda Ash global akibat peningkatan pasokan Tiongkok', 'Normalisasi biaya freight kontainer'],
            'BB-006' => ['Kenaikan bahan baku dasar Al(OH)3 & HCl', 'Permintaan tinggi sektor pengolahan air industri'],
            'BB-007' => ['Sensitivitas kurs USD/IDR (bahan polimer impor)', 'Fluktuasi harga bahan baku petrokimia akrilamida', 'Biaya pengapalan (ocean freight)'],
            'BB-008' => ['Permintaan musiman desinfektan air bersih', 'Biaya kemasan & distribusi bahan berbahaya'],
            'BB-009' => ['Efisiensi pasokan katalis dari distributor regional', 'Ketersediaan stok pasar membaik'],
            'BB-010' => ['Harga aditif silikon & emulsifier global cenderung datar', 'Kontrak pasokan tahunan stabil'],
        ];

        $drivers = $driversMap[$code] ?? ['Tren harga historis 6 bulan terakhir', 'Dinamika pasokan & biaya logistik kimia industri'];

        if ($pf['trend_direction'] === 'naik') {
            $sign = '+' . number_format($pct, 2, ',', '.');
            $deltaText = $pf['budget_delta'] > 0
                ? ' Jika kuantitas rekomendasi Q dibeli pada bulan ini (lock harga), perusahaan berpotensi menghemat anggaran sekitar **Rp ' . number_format($pf['budget_delta'], 0, ',', '.') . '**.'
                : '';
            $narrative = "Berdasarkan regresi tren historis dan momentum pasar 6 bulan terakhir, harga pasar **{$name}** pada **{$nextMonth}** diproyeksikan naik **{$sign}%** dari Rp {$cur}/{$unit} menjadi **Rp {$fcast}/{$unit}** (rentang estimasi Rp {$low} – Rp {$high}/{$unit}). Tekanan kenaikan terutama dipengaruhi oleh {$drivers[0]}.\n\n"
                . "Rekomendasi PPIC: pertimbangkan meninjau waktu kebutuhan dan harga acuan dari {$supplier} sebelum perubahan harga bulan depan.{$deltaText}";
        } elseif ($pf['trend_direction'] === 'turun') {
            $pctStr = number_format($pct, 2, ',', '.');
            $saveWait = abs($pf['budget_delta']);
            $waitText = $saveWait > 0
                ? ' Pembelian bertahap berpotensi menghemat sekitar **Rp ' . number_format($saveWait, 0, ',', '.') . '** pada porsi kebutuhan non-darurat.'
                : '';
            $narrative = "Model proyeksi menunjukkan tren harga pasar **{$name}** pada **{$nextMonth}** berpotensi melandai **{$pctStr}%** dari Rp {$cur}/{$unit} menuju **Rp {$fcast}/{$unit}** (rentang Rp {$low} – Rp {$high}/{$unit}). Penurunan ini sejalan dengan {$drivers[0]}.\n\n"
                . "Rekomendasi PPIC: hindari kebutuhan berlebih pada harga tinggi; gunakan proyeksi ini sebagai bahan pertimbangan waktu pemenuhan kebutuhan bersama {$supplier}.{$waitText}";
        } else {
            $pctStr = ($pct >= 0 ? '+' : '') . number_format($pct, 2, ',', '.');
            $narrative = "Harga pasar **{$name}** pada **{$nextMonth}** diproyeksikan bergerak **stabil** ({$pctStr}%) di kisaran **Rp {$fcast}/{$unit}** (rentang Rp {$low} – Rp {$high}/{$unit}, dibanding bulan ini Rp {$cur}/{$unit}). Kondisi ini didukung oleh {$drivers[0]}.\n\n"
                . "Rekomendasi PPIC: belum ada urgensi perubahan waktu pemenuhan. Pantau kebutuhan, ROP, MOQ, dan harga acuan {$supplier}.";
        }

        return [
            'narrative' => $narrative,
            'source' => 'AI Price Forecast Engine (OLS Trend + Momentum Pasar Kimia)',
            'drivers' => $drivers,
        ];
    }

    public static function formatMonthShort(string $ym): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
            return $ym;
        }
        return (self::MONTHS_ID[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    public static function formatMonthFull(string $ym): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
            return $ym;
        }
        return (self::MONTHS_FULL_ID[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }
}
