<?php

namespace Tests\Unit;

use App\Services\RawMaterialWorkbookImportService;
use App\Services\SalesForecastWorkbookImportService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class SalesForecastWorkbookImportServiceTest extends TestCase
{
    public function test_parses_only_month_sheets_with_valid_material_forecast_rows(): void
    {
        $sheets = [
            'Forecast Mei 2026' => [
                ['Nomor Material' => '11002', 'Nama Material' => 'OIL', 'Forecast' => '2340'],
            ],
            'September' => [
                ['Nomor Material' => '11002', 'Nama Material' => 'OIL', 'Forecast' => '1900'],
            ],
            'Petunjuk' => [
                ['Keterangan' => 'Sheet petunjuk'],
            ],
        ];

        $parsed = SalesForecastWorkbookImportService::parseWorkbookSheets($sheets);

        self::assertSame([5, 9], array_keys($parsed));
        self::assertSame('11002', $parsed[5][0]['code']);
        self::assertSame(2340.0, $parsed[5][0]['quantity']);
        self::assertSame(1900.0, $parsed[9][0]['quantity']);
    }

    public function test_merge_preserves_months_not_present_in_the_workbook(): void
    {
        $merged = SalesForecastWorkbookImportService::mergeForecastMonths(
            ['m1' => 15, 'm5' => 200, 'm12' => 50],
            [5 => 300, 9 => 900]
        );

        self::assertSame(15.0, $merged['m1']);
        self::assertSame(300.0, $merged['m5']);
        self::assertSame(0.0, $merged['m6']);
        self::assertSame(900.0, $merged['m9']);
        self::assertSame(50.0, $merged['m12']);
        self::assertSame(1265.0, $merged['total_12m_qty']);
    }

    public function test_rejects_duplicate_material_rows_in_a_month_sheet(): void
    {
        $sheets = [
            'Mei' => [
                ['Nomor Material' => '11002', 'Nama Material' => 'OIL', 'Forecast' => '2340'],
                ['Nomor Material' => '11002', 'Nama Material' => 'OIL', 'Forecast' => '100'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        SalesForecastWorkbookImportService::parseWorkbookSheets($sheets);
    }

    public function test_reads_monthly_forecast_rows_from_xlsx(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'forecast-'.bin2hex(random_bytes(6)).'.xlsx';
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Mei" sheetId="1" r:id="rId1"/></sheets></workbook>'
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            .'Target="worksheets/sheet1.xml"/></Relationships>'
        );
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'.$this->inlineCell('A1', 'Nomor Material').$this->inlineCell('B1', 'Nama Material').$this->inlineCell('C1', 'Forecast').'</row>'
            .'<row r="2">'.$this->inlineCell('A2', '11002').$this->inlineCell('B2', 'OIL').'<c r="C2"><v>2340</v></c></row>'
            .'</sheetData></worksheet>'
        );
        $zip->close();

        try {
            $sheets = RawMaterialWorkbookImportService::readWorkbookSheets($path);
            $parsed = SalesForecastWorkbookImportService::parseWorkbookSheets($sheets);

            self::assertSame('11002', $parsed[5][0]['code']);
            self::assertSame('OIL', $parsed[5][0]['name']);
            self::assertSame(2340.0, $parsed[5][0]['quantity']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function inlineCell(string $reference, string $value): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t>'
            .htmlspecialchars($value, ENT_XML1, 'UTF-8')
            .'</t></is></c>';
    }
}
