<?php

namespace Tests\Unit;

use App\Http\Controllers\SalesForecastController;
use PHPUnit\Framework\TestCase;

class SalesForecastEvaluationTest extends TestCase
{
    public function test_overforecast_uses_forecast_as_error_denominator(): void
    {
        $metrics = SalesForecastController::calculateEvaluationMetrics(1000, 800);

        self::assertSame(200.0, $metrics['difference']);
        self::assertSame(20.0, $metrics['error_percentage']);
        self::assertSame('Overforecast', $metrics['evaluation']);
    }

    public function test_underforecast_uses_realization_as_error_denominator(): void
    {
        $metrics = SalesForecastController::calculateEvaluationMetrics(1000, 1200);

        self::assertSame(-200.0, $metrics['difference']);
        self::assertSame(16.67, $metrics['error_percentage']);
        self::assertSame('Underforecast', $metrics['evaluation']);
    }

    public function test_zero_forecast_and_realization_is_balanced(): void
    {
        $metrics = SalesForecastController::calculateEvaluationMetrics(0, 0);

        self::assertSame(0.0, $metrics['error_percentage']);
        self::assertSame('Sesuai', $metrics['evaluation']);
    }
}
