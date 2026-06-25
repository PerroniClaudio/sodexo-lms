<?php

namespace App\Services;

use App\Enums\RiskLevel;
use App\Models\Importazione;
use App\Models\JobSector;
use App\Models\JobTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JobTaskRiskAssociationImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'job_task_code' => ['codice_mansione', 'mansione_codice', 'job_task_code'],
        'job_sector_name' => ['nome_settore', 'settore', 'job_sector_name'],
        'risk_level' => ['livello_di_rischio', 'rischio', 'risk_level'],
        'sector_risk_override' => ['sovrascrivi_rischio_settore', 'override_rischio_settore', 'sector_risk_override'],
    ];

    public function import(Importazione $importazione, string $localFilePath): void
    {
        $rows = $this->rowsFromSpreadsheet($localFilePath);
        $seenAssociations = [];

        DB::transaction(function () use ($rows, &$seenAssociations): void {
            foreach ($rows as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildPayload($row, $rowNumber);
                $associationKey = $payload['job_task_id'].'-'.$payload['job_sector_id'];

                if (isset($seenAssociations[$associationKey])) {
                    $this->fail($rowNumber, __('associazione mansione-settore duplicata nel file.'));
                }

                $seenAssociations[$associationKey] = true;

                $this->upsertAssociation($payload);
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

        foreach (['job_task_code', 'job_sector_name', 'risk_level', 'sector_risk_override'] as $requiredHeader) {
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
     * @return array{job_task_id: int, job_sector_id: int, task_risk_level: string, sector_risk_override: bool}
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        $jobTaskCode = Str::upper((string) $this->requireValue($row['job_task_code'] ?? null, $rowNumber, __('codice mansione obbligatorio.')));
        $jobSectorName = $this->requireValue($row['job_sector_name'] ?? null, $rowNumber, __('nome settore obbligatorio.'));
        $jobTask = JobTask::query()->where('code', $jobTaskCode)->first();
        $jobSector = JobSector::query()->where('name', $jobSectorName)->first();

        if ($jobTask === null) {
            $this->fail($rowNumber, __('mansione non valida: :value', ['value' => $jobTaskCode]));
        }

        if ($jobSector === null) {
            $this->fail($rowNumber, __('settore non valido: :value', ['value' => $jobSectorName]));
        }

        return [
            'job_task_id' => $jobTask->getKey(),
            'job_sector_id' => $jobSector->getKey(),
            'task_risk_level' => $this->parseRiskLevel($row['risk_level'] ?? null, $rowNumber)->value,
            'sector_risk_override' => $this->parseOverride($row['sector_risk_override'] ?? null, $rowNumber),
        ];
    }

    /**
     * @param  array{job_task_id: int, job_sector_id: int, task_risk_level: string, sector_risk_override: bool}  $payload
     */
    private function upsertAssociation(array $payload): void
    {
        $associationQuery = DB::table('job_task_job_sector')
            ->where('job_task_id', $payload['job_task_id'])
            ->where('job_sector_id', $payload['job_sector_id']);

        if ($associationQuery->exists()) {
            $associationQuery->update([
                'task_risk_level' => $payload['task_risk_level'],
                'sector_risk_override' => $payload['sector_risk_override'],
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('job_task_job_sector')->insert([
            'job_task_id' => $payload['job_task_id'],
            'job_sector_id' => $payload['job_sector_id'],
            'task_risk_level' => $payload['task_risk_level'],
            'sector_risk_override' => $payload['sector_risk_override'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function parseRiskLevel(?string $value, int $rowNumber): RiskLevel
    {
        $normalizedValue = $this->normalizeKey($this->requireValue($value, $rowNumber, __('livello di rischio obbligatorio.')));

        return match ($normalizedValue) {
            'basso', 'low' => RiskLevel::LOW,
            'medio', 'medium' => RiskLevel::MEDIUM,
            'alto', 'high' => RiskLevel::HIGH,
            default => $this->fail($rowNumber, __('livello di rischio non valido: :value', ['value' => $value])),
        };
    }

    private function parseOverride(?string $value, int $rowNumber): bool
    {
        $normalizedValue = $this->normalizeKey($this->requireValue($value, $rowNumber, __('sovrascrivi rischio settore obbligatorio.')));

        return match ($normalizedValue) {
            'si', 's', 'yes', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => $this->fail($rowNumber, __('sovrascrivi rischio settore non valido: :value. Usa SI o NO.', ['value' => $value])),
        };
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
