<?php

namespace App\Services;

use App\Models\Importazione;
use App\Models\JobTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JobTaskImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'name' => ['nome', 'name'],
        'description' => ['breve_descrizione', 'descrizione', 'description'],
        'code' => ['codice', 'code'],
    ];

    public function import(Importazione $importazione, string $localFilePath): void
    {
        $rows = $this->rowsFromSpreadsheet($localFilePath);
        $seenCodes = [];

        DB::transaction(function () use ($rows, &$seenCodes): void {
            foreach ($rows as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildPayload($row, $rowNumber);

                if (isset($seenCodes[$payload['code']])) {
                    $this->fail($rowNumber, __('codice mansione duplicato nel file.'));
                }

                $seenCodes[$payload['code']] = true;

                $this->upsertJobTask($payload);
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

        foreach (['name', 'code'] as $requiredHeader) {
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
     * @return array{name: string, description: ?string, code: string}
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        return [
            'name' => $this->requireValue($row['name'] ?? null, $rowNumber, __('nome obbligatorio.')),
            'description' => $this->nullableString($row['description'] ?? null),
            'code' => Str::upper((string) $this->requireValue($row['code'] ?? null, $rowNumber, __('codice obbligatorio.'))),
        ];
    }

    /**
     * @param  array{name: string, description: ?string, code: string}  $payload
     */
    private function upsertJobTask(array $payload): void
    {
        $jobTask = JobTask::withTrashed()
            ->where('code', $payload['code'])
            ->first();

        if ($jobTask === null) {
            JobTask::query()->create($payload);

            return;
        }

        $jobTask->fill($payload);
        $jobTask->save();

        if ($jobTask->trashed()) {
            $jobTask->restore();
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
