<?php

namespace App\Services;

use App\Models\Importazione;
use App\Models\JobUnit;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JobUnitImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'unit_code' => ['codice_unita_lavorativa', 'codice_unita', 'unit_code'],
        'name' => ['nome', 'name'],
        'country' => ['paese', 'country'],
        'region' => ['regione', 'region'],
        'province' => ['provincia', 'province'],
        'city' => ['citta', 'city'],
        'address' => ['indirizzo', 'address'],
        'postal_code' => ['codice_postale', 'cap', 'postal_code'],
        'description' => ['breve_descrizione', 'descrizione', 'description'],
    ];

    public function import(Importazione $importazione, string $localFilePath): void
    {
        $rows = $this->rowsFromSpreadsheet($localFilePath);
        $seenUnitCodes = [];

        DB::transaction(function () use ($rows, &$seenUnitCodes): void {
            foreach ($rows as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildPayload($row, $rowNumber);

                if (isset($seenUnitCodes[$payload['unit_code']])) {
                    $this->fail($rowNumber, __('codice unità lavorativa duplicato nel file.'));
                }

                $seenUnitCodes[$payload['unit_code']] = true;

                $this->upsertJobUnit($payload);
            }
        });
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromSpreadsheet(string $localFilePath): array
    {
        $spreadsheet = IOFactory::load($localFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headerRow = $rows[1] ?? [];
        $headerMap = $this->resolveHeaderMap($headerRow);

        foreach (['unit_code', 'name', 'country', 'region', 'city', 'postal_code'] as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                throw ValidationException::withMessages([
                    'file' => __('Colonna obbligatoria mancante: :column', ['column' => $requiredHeader]),
                ]);
            }
        }

        $mappedRows = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $mappedRow = [];

            foreach ($headerMap as $field => $column) {
                $mappedRow[$field] = $this->nullableString($row[$column] ?? null);
            }

            $mappedRows[$rowNumber] = $mappedRow;
        }

        return $mappedRows;
    }

    /**
     * @param  array<string, mixed>  $headerRow
     * @return array<string, string>
     */
    private function resolveHeaderMap(array $headerRow): array
    {
        $normalizedHeaders = collect($headerRow)
            ->filter(fn (mixed $value): bool => $this->nullableString($value) !== null)
            ->mapWithKeys(fn (mixed $value, string $column): array => [$this->normalizeKey((string) $value) => $column])
            ->all();

        $resolved = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalizedHeaders[$alias])) {
                    $resolved[$field] = $normalizedHeaders[$alias];
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, int|string|null>
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        $unitCode = Str::upper((string) $this->requireValue($row['unit_code'] ?? null, $rowNumber, __('codice unità lavorativa obbligatorio.')));
        $countryCode = Str::lower((string) $this->requireValue($row['country'] ?? null, $rowNumber, __('paese obbligatorio.')));

        if ($countryCode !== 'it') {
            $this->fail($rowNumber, __('paese non valido: :value. Usa IT.', ['value' => Str::upper($countryCode)]));
        }

        $countryId = WorldCountry::query()->where('code', 'it')->value('id');

        if ($countryId === null) {
            throw ValidationException::withMessages([
                'file' => __('Archivio geografico incompleto: paese IT non trovato.'),
            ]);
        }

        $regionName = (string) $this->requireValue($row['region'] ?? null, $rowNumber, __('regione obbligatoria.'));
        $region = WorldDivision::query()
            ->where('country_id', $countryId)
            ->where('name', $regionName)
            ->first();

        if ($region === null) {
            $this->fail($rowNumber, __('regione non valida: :value', ['value' => $regionName]));
        }

        $provinceName = $this->nullableString($row['province'] ?? null);
        $province = null;

        if ($provinceName !== null) {
            $province = Province::query()
                ->where('country_id', $countryId)
                ->where('region_id', $region->getKey())
                ->where('name', $provinceName)
                ->first();

            if ($province === null) {
                $this->fail($rowNumber, __('provincia non valida: :value', ['value' => $provinceName]));
            }
        }

        $cityName = (string) $this->requireValue($row['city'] ?? null, $rowNumber, __('città obbligatoria.'));
        $cityQuery = WorldCity::query()
            ->where('country_id', $countryId)
            ->where('division_id', $region->getKey())
            ->where('name', $cityName);

        if ($province !== null) {
            $cityQuery->where('province_id', $province->getKey());
        }

        $city = $cityQuery->first();

        if ($city === null) {
            $this->fail($rowNumber, __('città non valida: :value', ['value' => $cityName]));
        }

        return [
            'unit_code' => $unitCode,
            'name' => $this->requireValue($row['name'] ?? null, $rowNumber, __('nome obbligatorio.')),
            'country_id' => $countryId,
            'region_id' => $region->getKey(),
            'province_id' => $province?->getKey(),
            'city_id' => $city->getKey(),
            'address' => $this->nullableString($row['address'] ?? null),
            'postal_code' => $this->requireValue($row['postal_code'] ?? null, $rowNumber, __('codice postale obbligatorio.')),
            'description' => $this->nullableString($row['description'] ?? null),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $payload
     */
    private function upsertJobUnit(array $payload): void
    {
        $jobUnit = JobUnit::withTrashed()
            ->where('unit_code', $payload['unit_code'])
            ->first();

        if ($jobUnit === null) {
            JobUnit::query()->create($payload);

            return;
        }

        $jobUnit->fill($payload);
        $jobUnit->save();

        if ($jobUnit->trashed()) {
            $jobUnit->restore();
        }
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn (?string $value): bool => $this->nullableString($value) === null);
    }

    private function requireValue(?string $value, int $rowNumber, string $message): string
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            $this->fail($rowNumber, $message);
        }

        return $cleanValue;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleanValue = trim((string) $value);

        return $cleanValue === '' ? null : $cleanValue;
    }

    private function normalizeKey(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    private function fail(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('Riga :row: :message', ['row' => $rowNumber, 'message' => $message]),
        ]);
    }
}
