<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Config\AppConfig;
use App\Database\Database;
use App\Services\GeminiService;
use App\Services\InventoryService;
use App\Services\MaterialPlanningService;
use App\Services\PriceForecastService;

class AiForecastingController extends Controller
{
    public static function index(?int $selectedMaterialId = null, int $lookbackDays = 90): array
    {
        $materials = InventoryService::getMaterialStockSummary();

        $logs = Database::select("
            SELECT al.*, m.name as material_name, m.code as material_code, m.unit as material_unit
            FROM ai_forecast_logs al
            JOIN materials m ON m.id = al.material_id
            ORDER BY al.id DESC
            LIMIT 15
        ");

        if (empty($materials)) {
            return [
                'materials' => [],
                'recommendations' => [],
                'active_analysis' => null,
                'active_price_forecast' => null,
                'price_forecasts' => [],
                'price_summary' => null,
                'logs' => $logs,
                'has_api_key' => !empty(AppConfig::getGeminiApiKey()),
                'model_name' => AppConfig::getGeminiModel(),
                'lookback_days' => $lookbackDays,
            ];
        }

        // Calculate recommendations for all materials based on selected lookback horizon
        $recommendations = [];
        foreach ($materials as $m) {
            $rec = MaterialPlanningService::calculateRecommendation((int) $m['id'], 30, $lookbackDays);
            $recommendations[] = $rec;
        }

        // Selected material analysis
        $activeAnalysis = null;
        $targetId = $selectedMaterialId;
        if (!$targetId) {
            // Find first critical item or first item
            $firstCrit = null;
            foreach ($materials as $m) {
                if ($m['stock_status'] === 'Kritis') {
                    $firstCrit = (int) $m['id'];
                    break;
                }
            }
            $targetId = $firstCrit ?: (int) ($materials[0]['id'] ?? 1);
        }
        $activeAnalysis = GeminiService::analyzeMaterial($targetId, $lookbackDays);

        // Next-Month Market Price Forecast for all materials + focused AI narrative for selected material
        $priceForecastAll = PriceForecastService::forecastAllMaterials($recommendations);
        $activeQty = (float) ($activeAnalysis['final_recommended_qty'] ?? 0);
        $activePriceForecast = PriceForecastService::forecastMaterialPrice($targetId, $activeQty, true);

        return [
            'materials' => $materials,
            'recommendations' => $recommendations,
            'active_analysis' => $activeAnalysis,
            'active_price_forecast' => $activePriceForecast,
            'price_forecasts' => $priceForecastAll['items'],
            'price_summary' => $priceForecastAll['summary'],
            'logs' => $logs,
            'has_api_key' => !empty(AppConfig::getGeminiApiKey()),
            'model_name' => AppConfig::getGeminiModel(),
            'lookback_days' => $lookbackDays,
        ];
    }

    public static function analyze(int $materialId): array
    {
        return GeminiService::analyzeMaterial($materialId);
    }

    public static function forecastPrice(int $materialId): array
    {
        $rec = MaterialPlanningService::calculateRecommendation($materialId, 30, 90);
        return PriceForecastService::forecastMaterialPrice($materialId, (float) ($rec['final_recommended_qty'] ?? 0), true);
    }

    public static function storeMarketPrice(array $post, int $userId): void
    {
        $materialId = (int) ($post['material_id'] ?? 0);
        $priceMonth = trim((string) ($post['price_month'] ?? date('Y-m')));
        $marketPrice = (float) ($post['market_price'] ?? 0);
        $source = trim((string) ($post['source'] ?? 'Input Harga Pasar Manual'));
        $notes = trim((string) ($post['notes'] ?? ''));
        $syncMaster = !empty($post['sync_master_cost']);

        PriceForecastService::recordMarketPrice(
            $materialId,
            $priceMonth,
            $marketPrice,
            $source,
            $notes,
            $userId,
            $syncMaster
        );
    }

}
