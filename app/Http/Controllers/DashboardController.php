<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Database\Database;
use App\Services\InventoryService;

class DashboardController extends Controller
{
    public static function index(): array
    {
        $materials = InventoryService::getMaterialStockSummary();

        $kritisCount = 0;
        $waspadaCount = 0;
        $amanCount = 0;
        $kritisItems = [];
        $depleting30Count = 0;
        $depletingItems = [];

        foreach ($materials as &$m) {
            $rec = \App\Services\MaterialPlanningService::calculateRecommendation((int) $m['id']);
            $dailyUsage = (float) $rec['daily_usage'];
            $available = (float) $m['available_stock'];
            $leadTime = (int) $m['lead_time_days'];

            $daysOfSupply = ($dailyUsage > 0) ? round($available / $dailyUsage, 1) : 999.0;
            $depletionDate = date('d M Y', strtotime('+' . max(1, (int)round($daysOfSupply)) . ' days'));
            $reviewDaysLeft = max(0, (int)round($daysOfSupply) - $leadTime);
            $latestReviewDate = date('d M Y', strtotime('+' . $reviewDaysLeft . ' days'));

            $m['daily_usage'] = $dailyUsage;
            $m['days_of_supply'] = $daysOfSupply;
            $m['depletion_date'] = $depletionDate;
            $m['review_days_left'] = $reviewDaysLeft;
            $m['latest_review_date'] = $latestReviewDate;
            $m['is_review_due_today'] = ($reviewDaysLeft === 0);

            if ($daysOfSupply <= 30 || $m['stock_status'] === 'Kritis') {
                $depleting30Count++;
                $depletingItems[] = $m;
            }

            if ($m['stock_status'] === 'Kritis') {
                $kritisCount++;
                $kritisItems[] = $m;
            } elseif ($m['stock_status'] === 'Waspada') {
                $waspadaCount++;
            } else {
                $amanCount++;
            }
        }
        unset($m);

        usort($depletingItems, fn($a, $b) => $a['days_of_supply'] <=> $b['days_of_supply']);
        usort($kritisItems, fn($a, $b) => $a['days_of_supply'] <=> $b['days_of_supply']);

        $earliestDepletionDate = !empty($depletingItems) ? $depletingItems[0]['depletion_date'] : null;
        $earliestDays = !empty($depletingItems) ? $depletingItems[0]['days_of_supply'] : null;
        $reviewsDueToday = count(array_filter($depletingItems, fn($i) => !empty($i['is_review_due_today'])));

        $finishGoodCount = (int) (Database::selectOne("SELECT COUNT(*) as total FROM products")['total'] ?? 0);
        $quotationSoCount = (int) (Database::selectOne("
            SELECT COUNT(*) as total
            FROM sales_histories
            WHERE status IN ('quotation', 'confirmed_so')
        ")['total'] ?? 0);

        $recentAudits = Database::select("
            SELECT a.*, u.name as user_name, u.role as user_role
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.id DESC
            LIMIT 6
        ");

        $recentAiLogs = Database::select("
            SELECT al.*, m.name as material_name, m.unit as material_unit
            FROM ai_forecast_logs al
            JOIN materials m ON m.id = al.material_id
            ORDER BY al.id DESC
            LIMIT 3
        ");

        return [
            'total_materials' => count($materials),
            'kritis_count' => $kritisCount,
            'waspada_count' => $waspadaCount,
            'aman_count' => $amanCount,
            'depleting_30_count' => $depleting30Count,
            'depleting_items' => $depletingItems,
            'earliest_depletion_date' => $earliestDepletionDate,
            'earliest_days' => $earliestDays,
            'reviews_due_today' => $reviewsDueToday,
            'finish_good_count' => $finishGoodCount,
            'quotation_so_count' => $quotationSoCount,
            'materials' => $materials,
            'kritis_items' => $kritisItems,
            'recent_audits' => $recentAudits,
            'recent_ai_logs' => $recentAiLogs,
        ];
    }
}
