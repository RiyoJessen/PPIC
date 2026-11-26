<?php

namespace Tests\Unit;

use App\Services\FormulaRequirementCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaRequirementCalculatorTest extends TestCase
{
    public function testScalesMaterialRequirementsForTargetOutputAndYield(): void
    {
        $result = FormulaRequirementCalculator::calculate(50, 100, 95, [
            ['quantity_per_batch' => 20, 'available_stock' => 5],
            ['quantity_per_batch' => 80, 'available_stock' => 100],
        ]);

        self::assertEqualsWithDelta(95, $result['expected_batch_output'], 0.000001);
        self::assertEqualsWithDelta(10.526315789, $result['items'][0]['required_quantity'], 0.000001);
        self::assertEqualsWithDelta(5.526315789, $result['items'][0]['shortage_quantity'], 0.000001);
        self::assertEqualsWithDelta(0, $result['items'][1]['shortage_quantity'], 0.000001);
    }

    public function testRejectsInvalidProductionTargets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FormulaRequirementCalculator::calculate(0, 100, 100, []);
    }
}
