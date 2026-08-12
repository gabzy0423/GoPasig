<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Services\OfficialRouteCoordinateImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class OfficialRouteCoordinateImporterTest extends TestCase
{
    use RefreshDatabase;

    private array $sheets = [
        'SPED to Bridgetowne' => null,
        'Bridgetowne to SPED' => null,
        'SPED to Ligaya' => ['Route 2', 'outbound'],
        'Ligaya to SPED' => ['Route 2', 'inbound'],
        'SPED to One San Miguel Ave' => ['Route 3', 'outbound'],
        'One San Miguel Ave to SPED' => ['Route 3', 'inbound'],
        'SPED - Nagpayong' => ['Route 4', 'outbound'],
        'Nagpayong - SPED' => ['Route 4', 'inbound'],
    ];

    public function test_dry_run_parses_all_official_production_sheets_without_database_changes(): void
    {
        $this->createCanonicalVariants();
        $before = RouteVariantStop::count();
        $path = $this->makeWorkbook();

        $plan = app(OfficialRouteCoordinateImporter::class)->buildPlan($path);

        $this->assertTrue($plan['ready']);
        $this->assertCount(6, $plan['sheets']);
        $this->assertSame($before, RouteVariantStop::count());
        $this->assertSame(2, $plan['sheets'][0]['planned_creates']);
    }

    public function test_apply_preserves_sequence_direction_and_duplicate_rows(): void
    {
        $this->createCanonicalVariants();
        $path = $this->makeWorkbook([
            'SPED to Ligaya' => [
                ['Kapasigan 1 (Landbank)', 14.5619547869153, 121.076773781850],
                ['Kapasigan 2 (after Meralco)', 14.5619547869153, 121.076773781850],
            ],
        ]);

        $importer = app(OfficialRouteCoordinateImporter::class);
        $plan = $importer->buildPlan($path);
        $importer->apply($plan);

        $outbound = RouteVariant::where('route_id', Route::where('name', 'Route 2')->value('id'))
            ->where('direction', 'outbound')->firstOrFail();
        $inbound = RouteVariant::where('route_id', $outbound->route_id)->where('direction', 'inbound')->firstOrFail();
        $this->assertSame(['Kapasigan 1 (Landbank)', 'Kapasigan 2 (after Meralco)'], $outbound->stops()->pluck('name')->all());
        $this->assertSame([1, 2], $outbound->stops()->pluck('sequence')->all());
        $this->assertCount(2, $inbound->stops);
        $this->assertSame(100, $outbound->stops()->first()->radius_meters);
    }

    public function test_normalization_preserves_meaningful_suffixes(): void
    {
        $this->createCanonicalVariants();
        $path = $this->makeWorkbook([
            'SPED to Ligaya' => [
                ["Kapasigan 1\n (Landbank)", 14.5, 121.0],
                ['Rotonda  (BPI)', 14.51, 121.01],
            ],
        ]);

        $rows = collect(app(OfficialRouteCoordinateImporter::class)->buildPlan($path)['sheets'])->firstWhere('worksheet', 'SPED to Ligaya')['rows'];
        $this->assertSame('Kapasigan 1 (Landbank)', $rows[0]['normalized_name']);
        $this->assertSame('Rotonda (BPI)', $rows[1]['normalized_name']);
        $this->assertSame("Kapasigan 1\n (Landbank)", $rows[0]['raw_name']);
    }

    public function test_invalid_coordinates_block_apply_and_preserve_existing_rows(): void
    {
        $this->createCanonicalVariants();
        $existing = RouteVariantStop::create([
            'route_variant_id' => RouteVariant::where('direction', 'outbound')->firstOrFail()->id,
            'name' => 'Existing', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1,
        ]);
        $path = $this->makeWorkbook(['SPED to Ligaya' => [['Bad', 140.0, 121.0]]]);
        $importer = app(OfficialRouteCoordinateImporter::class);
        $plan = $importer->buildPlan($path);

        $this->assertFalse($plan['ready']);
        $this->expectException(RuntimeException::class);
        $importer->apply($plan);
        $this->assertDatabaseHas('route_variant_stops', ['id' => $existing->id, 'name' => 'Existing']);
    }

    public function test_missing_variant_fails_without_legacy_fallback(): void
    {
        $this->createCanonicalVariants(['Route 2']);
        $plan = app(OfficialRouteCoordinateImporter::class)->buildPlan($this->makeWorkbook());

        $this->assertFalse($plan['ready']);
        $this->assertStringContainsString('Canonical route missing', implode('|', $plan['errors']));
    }

    public function test_bridgetowne_worksheets_are_excluded_from_operational_import(): void
    {
        $this->createCanonicalVariants();
        $path = $this->makeWorkbook([
            'SPED to Bridgetowne' => [['SPED', 14.58849243, 121.1050783], ['Bridgetowne', 14.59245877, 121.086492]],
            'Bridgetowne to SPED' => [['Bridgetowne', 14.59245877, 121.086492], ['SPED', 14.58849243, 121.1050783]],
        ]);

        $plan = app(OfficialRouteCoordinateImporter::class)->buildPlan($path);

        $this->assertTrue($plan['ready']);
        $this->assertCount(6, $plan['sheets']);
        $this->assertSame([], collect($plan['sheets'])->where('canonical_route', 'Route 1')->values()->all());
        $this->assertTrue(collect($plan['warnings'])->contains(fn (string $warning) => str_contains($warning, 'SPED to Bridgetowne')));
        $this->assertTrue(collect($plan['warnings'])->contains(fn (string $warning) => str_contains($warning, 'Bridgetowne to SPED')));
    }
    public function test_repeated_apply_is_idempotent_and_unrelated_variant_is_preserved(): void
    {
        $this->createCanonicalVariants();
        $unrelated = Route::create(['name' => 'Route A', 'status' => 'Active']);
        $otherVariant = RouteVariant::create(['route_id' => $unrelated->id, 'direction' => 'outbound']);
        RouteVariantStop::create(['route_variant_id' => $otherVariant->id, 'name' => 'Legacy', 'lat' => 14.5, 'lng' => 121, 'sequence' => 1]);
        $importer = app(OfficialRouteCoordinateImporter::class);
        $path = $this->makeWorkbook();
        $importer->apply($importer->buildPlan($path));
        $first = RouteVariantStop::whereHas('routeVariant.route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))->pluck('id')->sort()->values();
        $importer->apply($importer->buildPlan($path));
        $second = RouteVariantStop::whereHas('routeVariant.route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))->pluck('id')->sort()->values();

        $this->assertSame($first->all(), $second->all());
        $this->assertDatabaseHas('route_variant_stops', ['route_variant_id' => $otherVariant->id, 'name' => 'Legacy']);
    }

    private function createCanonicalVariants(?array $onlyRoutes = null): void
    {
        foreach ($onlyRoutes ?? Route::canonicalProductionNames() as $routeName) {
            $route = Route::create(['name' => $routeName, 'status' => 'Active']);
            foreach (['outbound', 'inbound'] as $direction) {
                RouteVariant::create(['route_id' => $route->id, 'direction' => $direction]);
            }
        }
    }

    private function makeWorkbook(array $overrides = []): string
    {
        $sheetNames = array_keys($this->sheets);
        $zip = new ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 'rd3_') . '.xlsx';
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $workbookSheets = $relationships = ''; $sheetXml = [];
        foreach ($sheetNames as $index => $sheetName) {
            $id = $index + 1;
            $workbookSheets .= '<sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $relationships .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            $rows = $overrides[$sheetName] ?? [['Origin', 14.5, 121.0], ['Destination', 14.51, 121.01]];
            $xmlRows = '<row r="1"><c r="A1" t="inlineStr"><is><t>stop name</t></is></c><c r="B1" t="inlineStr"><is><t>latitude</t></is></c><c r="C1" t="inlineStr"><is><t>longitude</t></is></c></row>';
            foreach ($rows as $rowIndex => $row) {
                $r = $rowIndex + 2;
                $xmlRows .= '<row r="' . $r . '"><c r="A' . $r . '" t="inlineStr"><is><t>' . htmlspecialchars($row[0], ENT_XML1) . '</t></is></c><c r="B' . $r . '"><v>' . $row[1] . '</v></c><c r="C' . $r . '"><v>' . $row[2] . '</v></c></row>';
            }
            $sheetXml[$id] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xmlRows . '</sheetData></worksheet>';
        }
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $workbookSheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>');
        foreach ($sheetXml as $id => $xml) $zip->addFromString('xl/worksheets/sheet' . $id . '.xml', $xml);
        $zip->close();
        return $path;
    }
}
