<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class OfficialRouteCoordinateImporter
{
    public const DEFAULT_RADIUS_METERS = 100;

    private const WORKSHEET_MAP = [
        'SPED to Ligaya' => ['route' => 'Route 2', 'direction' => 'OUT', 'variant_direction' => 'outbound'],
        'Ligaya to SPED' => ['route' => 'Route 2', 'direction' => 'IN', 'variant_direction' => 'inbound'],
        'SPED - Nagpayong' => ['route' => 'Route 4', 'direction' => 'OUT', 'variant_direction' => 'outbound'],
        'Nagpayong - SPED' => ['route' => 'Route 4', 'direction' => 'IN', 'variant_direction' => 'inbound'],
        'SPED to One San Miguel Ave' => ['route' => 'Route 3', 'direction' => 'OUT', 'variant_direction' => 'outbound'],
        'One San Miguel Ave to SPED' => ['route' => 'Route 3', 'direction' => 'IN', 'variant_direction' => 'inbound'],
    ];

    private const EXCLUDED_WORKSHEETS = [
        'SPED to Bridgetowne',
        'Bridgetowne to SPED',
        'SPED to Bridgetown',
        'Bridgetown to SPED',
    ];

    public function buildPlan(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $workbook = $this->readWorkbook($path);
        $errors = [];
        $warnings = [];
        $sheets = [];

        foreach (self::WORKSHEET_MAP as $sheetName => $mapping) {
            if (!isset($workbook[$sheetName])) {
                $errors[] = "Required worksheet missing: {$sheetName}";
                continue;
            }

            $route = Route::where('name', $mapping['route'])->first();
            $variant = $route
                ? RouteVariant::where('route_id', $route->id)
                    ->where('direction', $mapping['variant_direction'])
                    ->first()
                : null;

            if (!$route) {
                $errors[] = "Canonical route missing for {$sheetName}: {$mapping['route']}";
            }
            if ($route && !$variant) {
                $errors[] = "Canonical variant missing for {$sheetName}: {$mapping['variant_direction']}";
            }

            $rows = $workbook[$sheetName]['rows'];
            foreach ($rows as $row) {
                if ($row['duplicate_coordinate']) {
                    $warnings[] = "EXACT COORDINATE DUPLICATE - PRESERVED PENDING BENEFICIARY CONFIRMATION: {$sheetName} row {$row['source_row']}";
                }
            }

            $current = $variant ? $variant->stops()->get()->keyBy('sequence') : collect();
            $diff = $this->diffRows($rows, $current);

            $sheets[] = [
                'worksheet' => $sheetName,
                'canonical_route' => $mapping['route'],
                'direction' => $mapping['direction'],
                'variant_direction' => $mapping['variant_direction'],
                'route_id' => $route?->id,
                'variant_id' => $variant?->id,
                'rows' => $rows,
                'current_row_count' => $current->count(),
                'planned_creates' => $diff['creates'],
                'planned_updates' => $diff['updates'],
                'planned_unchanged' => $diff['unchanged'],
                'planned_removals' => $diff['removals'],
                'status' => (!$route || !$variant || $this->hasInvalidRows($rows)) ? 'BLOCKED' : 'READY',
            ];
        }

        foreach (self::EXCLUDED_WORKSHEETS as $sheetName) {
            if (isset($workbook[$sheetName])) {
                $warnings[] = "EXCLUDED WORKSHEET - NO CANONICAL ROUTE MAPPING: {$sheetName}";
            }
        }

        return [
            'source' => realpath($path) ?: $path,
            'sheets' => $sheets,
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'ready' => empty($errors) && collect($sheets)->every(fn ($sheet) => $sheet['status'] === 'READY'),
        ];
    }

    public function apply(array $plan): array
    {
        if (!$plan['ready']) {
            throw new RuntimeException('Cannot apply an invalid official route workbook plan.');
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan['sheets'] as $sheet) {
                $variant = RouteVariant::findOrFail($sheet['variant_id']);
                $desiredSequences = collect($sheet['rows'])->pluck('sequence');

                $variant->stops()->whereNotIn('sequence', $desiredSequences)->delete();

                foreach ($sheet['rows'] as $row) {
                    $stop = $variant->stops()->where('sequence', $row['sequence'])->first();
                    $attributes = [
                        'sequence' => $row['sequence'],
                        'name' => $row['normalized_name'],
                        'lat' => $row['lat'],
                        'lng' => $row['lng'],
                        'radius_meters' => self::DEFAULT_RADIUS_METERS,
                        'coordinate_status' => 'verified',
                        'coordinate_source' => 'official beneficiary workbook',
                    ];

                    if (!$stop) {
                        $attributes['stop_type'] = 'designated_stop';
                        $attributes['canonical_stop_id'] = null;
                        $stop = $variant->stops()->make($attributes);
                    } else {
                        $stop->fill($attributes);
                    }

                    $stop->save();
                }
            }
        });

        Cache::forget('routes_all');
        Cache::forget('stops_all');
        Cache::forget('commuter_dashboard_aggregate');
        Cache::forget('commuter_route_stops_aggregate');

        return $this->buildPlan($plan['source']);
    }

    private function diffRows(array $rows, $current): array
    {
        $creates = 0;
        $updates = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $existing = $current->get($row['sequence']);
            if (!$existing) {
                $creates++;
                continue;
            }

            $same = $this->normalizeName($existing->name) === $row['normalized_name']
                && round((float) $existing->lat, 7) === round((float) $row['lat'], 7)
                && round((float) $existing->lng, 7) === round((float) $row['lng'], 7)
                && (int) ($existing->radius_meters ?? self::DEFAULT_RADIUS_METERS) === self::DEFAULT_RADIUS_METERS;
            $same ? $unchanged++ : $updates++;
        }

        return [
            'creates' => $creates,
            'updates' => $updates,
            'unchanged' => $unchanged,
            'removals' => $current->keys()->diff(collect($rows)->pluck('sequence'))->count(),
        ];
    }

    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open XLSX workbook: {$path}");
        }

        try {
            $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
            $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
            $sharedStrings = $this->readSharedStrings($zip);
            $relationshipMap = [];

            foreach ($rels->Relationship as $relationship) {
                $relationshipMap[(string) $relationship['Id']] = (string) $relationship['Target'];
            }

            $result = [];
            $namespaces = $workbook->getDocNamespaces(true);
            $workbook->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

            foreach ($workbook->xpath('//main:sheets/main:sheet') as $sheet) {
                $name = $this->normalizeName((string) $sheet['name']);
                $relationshipId = (string) $sheet->attributes('r', true)->id;
                $target = ltrim($relationshipMap[$relationshipId] ?? '', '/');
                $target = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                $xml = simplexml_load_string($zip->getFromName($target));
                $result[$name] = ['rows' => $this->readSheetRows($xml, $sharedStrings)];
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function readSheetRows(SimpleXMLElement $xml, array $sharedStrings): array
    {
        $namespaces = $xml->getDocNamespaces(true);
        $xml->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $header = null;
        $rawRows = [];

        foreach ($xml->xpath('//main:sheetData/main:row') as $row) {
            $row->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = [];
            foreach ($row->xpath('./main:c') as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnNumber($match[0] ?? '');
                $values[$column] = $this->cellValue($cell, $sharedStrings);
            }
            $rawRows[] = ['source_row' => (int) $row['r'], 'values' => $values];

            if ($header === null) {
                $candidate = array_map(fn ($value) => strtolower($this->normalizeName((string) $value)), $values);
                $nameColumn = $this->findHeaderColumn($candidate, ['stop name', 'stop', 'name', 'pick up point', 'pickup point']);
                $latColumn = $this->findHeaderColumn($candidate, ['latitude', 'lat']);
                $lngColumn = $this->findHeaderColumn($candidate, ['longitude', 'long', 'lng', 'lon']);
                if ($nameColumn !== null && $latColumn !== null && $lngColumn !== null) {
                    $header = compact('nameColumn', 'latColumn', 'lngColumn');
                }
            }
        }

        if ($header === null) {
            throw new RuntimeException('Worksheet is missing a recognizable stop name/latitude/longitude header.');
        }

        $rows = [];
        $coordinates = [];
        foreach ($rawRows as $raw) {
            if ($raw['source_row'] <= $this->headerRow($rawRows, $header)) {
                continue;
            }

            $name = $raw['values'][$header['nameColumn']] ?? null;
            $lat = $raw['values'][$header['latColumn']] ?? null;
            $lng = $raw['values'][$header['lngColumn']] ?? null;
            if ($name === null && $lat === null && $lng === null) {
                continue;
            }

            $invalid = $name === null || trim((string) $name) === '' || !is_numeric($lat) || !is_numeric($lng)
                || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180;
            $coordinateKey = is_numeric($lat) && is_numeric($lng) ? sprintf('%.7f|%.7f', (float) $lat, (float) $lng) : null;
            $rows[] = [
                'source_row' => $raw['source_row'],
                'raw_name' => (string) ($name ?? ''),
                'normalized_name' => $this->normalizeName((string) ($name ?? '')),
                'lat' => is_numeric($lat) ? (float) $lat : null,
                'lng' => is_numeric($lng) ? (float) $lng : null,
                'invalid' => $invalid,
                'invalid_reason' => $invalid ? 'Name/coordinate is missing, malformed, or outside valid bounds.' : null,
                'duplicate_coordinate' => false,
                'sequence' => count($rows) + 1,
            ];
            if ($coordinateKey !== null) {
                $coordinates[$coordinateKey][] = count($rows) - 1;
            }
        }

        foreach ($coordinates as $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $index) {
                    $rows[$index]['duplicate_coordinate'] = true;
                }
            }
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');
        if ($contents === false) return [];
        $xml = simplexml_load_string($contents);
        $namespaces = $xml->getDocNamespaces(true);
        $xml->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];
        foreach ($xml->si as $item) {
            $item->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';
            foreach ($item->xpath('.//main:t') as $part) $text .= (string) $part;
            $values[] = $text;
        }
        return $values;
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell['t'];
        if ($type === 'inlineStr') return (string) ($cell->is->t ?? '');
        $value = (string) ($cell->v ?? '');
        return $type === 's' ? ($sharedStrings[(int) $value] ?? '') : ($value === '' ? null : $value);
    }

    private function findHeaderColumn(array $values, array $candidates): ?int
    {
        foreach ($values as $column => $value) {
            if (in_array($value, $candidates, true)) return $column;
        }
        return null;
    }

    private function headerRow(array $rows, array $header): int
    {
        foreach ($rows as $row) {
            $values = array_map(fn ($value) => strtolower($this->normalizeName((string) $value)), $row['values']);
            if (($values[$header['nameColumn']] ?? null) !== null && ($values[$header['latColumn']] ?? null) !== null && ($values[$header['lngColumn']] ?? null) !== null) {
                return $row['source_row'];
            }
        }
        return 0;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) $number = ($number * 26) + ord($letter) - 64;
        return $number;
    }

    private function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function hasInvalidRows(array $rows): bool
    {
        return collect($rows)->contains(fn ($row) => $row['invalid'] || $row['normalized_name'] === '');
    }
}
