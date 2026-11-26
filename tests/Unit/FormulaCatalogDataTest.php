<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormulaCatalogDataTest extends TestCase
{
    public function testWorkbookFormulaVariantsAndProductAssignmentsAreComplete(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR
            . 'data' . DIRECTORY_SEPARATOR . 'vaslina_formulas.json';
        $contents = file_get_contents($path);
        self::assertNotFalse($contents);
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $formulas = $data['formulas'];
        $assignments = $data['assignments'];
        $formulaCodes = array_column($formulas, 'code');

        self::assertCount(68, $formulas);
        self::assertCount(29, $assignments);
        self::assertCount(count(array_unique($formulaCodes)), $formulaCodes);

        $formulaByCode = [];
        foreach ($formulas as $formula) {
            $formulaByCode[$formula['code']] = $formula;
            self::assertNotEmpty($formula['items']);
            foreach ($formula['items'] as $item) {
                self::assertGreaterThan(0, $item['fraction']);
                self::assertLessThanOrEqual(1, $item['fraction']);
            }
        }

        foreach ($assignments as $assignment) {
            self::assertArrayHasKey($assignment['formula_code'], $formulaByCode);
        }

        self::assertEqualsWithDelta(1.0, $this->totalFraction($formulaByCode['3150 (4)']), 0.000001);
        self::assertEqualsWithDelta(0.05, $this->totalFraction($formulaByCode['WPW70']), 0.000001);
        self::assertEqualsWithDelta(1.0000112, $this->totalFraction($formulaByCode['YB01']), 0.0000001);
    }

    private function totalFraction(array $formula): float
    {
        return array_sum(array_column($formula['items'], 'fraction'));
    }
}
