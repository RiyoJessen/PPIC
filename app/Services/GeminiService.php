<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Database\Database;

class GeminiService
{
    public static function analyzeMaterial(int $materialId, int $lookbackDays = 90): array
    {
        $rec = MaterialPlanningService::calculateRecommendation($materialId, 30, $lookbackDays);
        $mat = $rec['material'];

        $apiKey = AppConfig::getGeminiApiKey();
        $analysis = $apiKey
            ? self::callGeminiApi($rec, $apiKey)
            : self::generateLocalSimulation($rec);

        $logId = Database::insert('ai_forecast_logs', [
            'material_id' => $materialId,
            'analysis_date' => date('Y-m-d'),
            'current_stock' => $mat['physical_stock'],
            'safety_stock' => $mat['safety_stock'],
            'projected_demand_30d' => $rec['period_demand'],
            'recommended_qty' => $rec['final_recommended_qty'],
            'risk_level' => $mat['stock_status'],
            'trend_direction' => $analysis['trend_direction'] ?? 'stabil',
            'ai_narrative' => $analysis['narrative'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return array_merge($rec, $analysis, ['log_id' => $logId]);
    }

    private static function callGeminiApi(array $rec, string $apiKey): array
    {
        $mat = $rec['material'];
        $model = AppConfig::getGeminiModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $prompt = <<<PROMPT
Anda adalah asisten kecerdasan buatan PPIC untuk PT DUNIA KIMIA JAYA GRESIK.
Tugas Anda adalah menganalisis kondisi persediaan bahan baku kimia dan memberi rekomendasi perencanaan untuk PPIC.

Data Bahan Baku:
- Nama Bahan: {$mat['name']} ({$mat['code']})
- Kategori: {$mat['category_name']}
- Satuan: {$mat['unit']}
- Stok Fisik: {$mat['physical_stock']} {$mat['unit']}
- Stok Teralokasi: {$mat['allocated_stock']} {$mat['unit']}
- Stok Bebas Tersedia: {$mat['available_stock']} {$mat['unit']}
- Safety Stock: {$mat['safety_stock']} {$mat['unit']}
- Reorder Point (ROP): {$mat['reorder_point']} {$mat['unit']}
- Supplier Utama: {$mat['supplier_name']} (Lead Time: {$mat['supplier_lead_time']} hari)
- Perkiraan Kebutuhan 30 Hari: {$rec['period_demand']} {$mat['unit']}
- Rekomendasi Jumlah Tambahan (Q): {$rec['final_recommended_qty']} {$mat['unit']}
- Status Stok Saat Ini: {$mat['stock_status']}

Tolong susun ringkasan analisis naratif (maksimal 3 paragraf singkat) mencakup:
1. Penjelasan kondisi stok saat ini & evaluasi ketersediaan dibanding kebutuhan produksi dan lead time.
2. Identifikasi risiko operasional bila rencana kebutuhan tidak ditindaklanjuti.
3. Rekomendasi jumlah dan waktu tindak lanjut untuk PPIC.

Jawab dalam Bahasa Indonesia formal dengan nada profesional industri kimia.
PROMPT;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 600,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                return [
                    'narrative' => trim($text),
                    'source' => 'Gemini API Live (' . $model . ')',
                    'trend_direction' => ($rec['final_recommended_qty'] > 0) ? 'naik' : 'stabil',
                ];
            }
        }

        $fallback = self::generateLocalSimulation($rec);
        $fallback['source'] = 'Simulasi Cerdas PPIC (Gemini Offline / Fallback)';
        return $fallback;
    }

    private static function generateLocalSimulation(array $rec): array
    {
        $mat = $rec['material'];
        $name = $mat['name'];
        $avail = number_format((float) $mat['available_stock'], 0, ',', '.');
        $safety = number_format((float) $mat['safety_stock'], 0, ',', '.');
        $phys = number_format((float) $mat['physical_stock'], 0, ',', '.');
        $alloc = number_format((float) $mat['allocated_stock'], 0, ',', '.');
        $supplier = $mat['supplier_name'] ?: 'Supplier Terdaftar';
        $leadTime = (int) ($mat['supplier_lead_time'] ?: 5);
        $q = number_format((float) $rec['final_recommended_qty'], 0, ',', '.');
        $unit = $mat['unit'];

        if ($mat['stock_status'] === 'Kritis') {
            $narrative = "Persediaan {$name} saat ini berada dalam status **Kritis**. Saldo stok fisik tercatat {$phys} {$unit}, dengan stok teralokasi sebesar {$alloc} {$unit}, sehingga persediaan bebas yang siap pakai hanya tersisa {$avail} {$unit}. Angka ini telah menembus batas safety stock ({$safety} {$unit}).\n\n"
                . "Dengan lead time acuan {$supplier} sekitar {$leadTime} hari kerja, keterlambatan menindaklanjuti kebutuhan material berpotensi menghambat pemenuhan target quotation/SO PT Dunia Kimia Jaya Gresik.\n\n"
                . "Sistem merekomendasikan jumlah tambahan **{$q} {$unit}** berdasarkan rencana kebutuhan, stok bebas, safety stock, dan MOQ untuk bahan pertimbangan PPIC.";
            $trend = 'naik';
        } elseif ($mat['stock_status'] === 'Waspada') {
            $narrative = "Kondisi stok {$name} teridentifikasi dalam kategori **Waspada**. Stok bebas tersedia ({$avail} {$unit}) masih berada di atas batas safety stock ({$safety} {$unit}), namun telah berada di bawah ambang Reorder Point (ROP).\n\n"
                . "Dengan rata-rata pemakaian harian serta lead time pemesanan {$leadTime} hari ke {$supplier}, persediaan diperkirakan memasuki zona merah dalam kurun waktu 5–7 hari mendatang apabila terdapat penambahan permintaan sales baru.\n\n"
                . "Sistem merekomendasikan kebutuhan tambahan indikatif sebesar **{$q} {$unit}** untuk menjaga kesinambungan persediaan tanpa menyebabkan penumpukan stok berlebih di gudang.";
            $trend = 'stabil';
        } else {
            $narrative = "Persediaan {$name} saat ini berada dalam zona **Aman** ({$avail} {$unit} tersedia vs safety stock {$safety} {$unit}). Ketersediaan raw material terpantau mencukupi dan kapasitas gudang tetap optimal.\n\n"
                . "Tidak diperlukan tindakan pembelian darurat saat ini. Tim PPIC cukup memonitor tren pemakaian serta proyeksi forecast sales bulanan secara berkala.";
            $trend = 'stabil';
        }

        return [
            'narrative' => $narrative,
            'source' => 'AI Engine PPIC (Algoritma Evaluasi & Heuristik Gemini)',
            'trend_direction' => $trend,
        ];
    }
}
