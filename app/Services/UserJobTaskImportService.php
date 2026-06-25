<?php

namespace App\Services;

use App\Models\Importazione;
use App\Models\JobTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserJobTaskImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'fiscal_code' => ['codice_fiscale', 'cf'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $jobTasksByCode = null;

    public function __construct(
        private readonly UserJobAssignmentService $userJobAssignmentService,
    ) {}

    public function import(Importazione $importazione, string $localFilePath): void
    {
        $sheetData = $this->rowsFromSpreadsheet($localFilePath);
        $seenFiscalCodes = [];

        DB::transaction(function () use ($sheetData, &$seenFiscalCodes): void {
            foreach ($sheetData['rows'] as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildPayload($row, $sheetData['taskColumns'], $rowNumber);

                if (isset($seenFiscalCodes[$payload['fiscal_code']])) {
                    $this->fail($rowNumber, __('codice fiscale duplicato nel file.'));
                }

                $seenFiscalCodes[$payload['fiscal_code']] = true;

                $this->assignTasks($payload, $rowNumber);
            }
        });
    }

    /**
     * @return array{
     *     rows: array<int, array<string, string|null>>,
     *     taskColumns: array<string, int>
     * }
     */
    private function rowsFromSpreadsheet(string $localFilePath): array
    {
        $spreadsheet = IOFactory::load($localFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headerRow = $rows[1] ?? [];
        $fiscalCodeColumn = $this->resolveFiscalCodeColumn($headerRow);
        $taskColumns = $this->resolveTaskColumns($headerRow);

        if ($fiscalCodeColumn === null) {
            throw ValidationException::withMessages([
                'file' => __('Colonna obbligatoria mancante: fiscal_code'),
            ]);
        }

        if ($taskColumns === []) {
            throw ValidationException::withMessages([
                'file' => __('Nessuna colonna mansione valida trovata nell\'intestazione del file.'),
            ]);
        }

        $mappedRows = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $mappedRow = [
                'fiscal_code' => $this->nullableString($row[$fiscalCodeColumn] ?? null),
            ];

            foreach ($taskColumns as $column => $jobTaskId) {
                $mappedRow['task_'.$jobTaskId] = $this->nullableString($row[$column] ?? null);
            }

            $mappedRows[$rowNumber] = $mappedRow;
        }

        return [
            'rows' => $mappedRows,
            'taskColumns' => $taskColumns,
        ];
    }

    private function resolveFiscalCodeColumn(array $headerRow): ?string
    {
        $normalizedHeaders = collect($headerRow)
            ->filter(fn (mixed $value): bool => $this->nullableString($value) !== null)
            ->mapWithKeys(fn (mixed $value, string $column): array => [$this->normalizeKey((string) $value) => $column])
            ->all();

        foreach (self::HEADER_ALIASES['fiscal_code'] as $alias) {
            if (isset($normalizedHeaders[$alias])) {
                return $normalizedHeaders[$alias];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $headerRow
     * @return array<string, int>
     */
    private function resolveTaskColumns(array $headerRow): array
    {
        $jobTasksByCode = $this->jobTasksByCode();
        $taskColumns = [];

        foreach ($headerRow as $column => $value) {
            $header = $this->nullableString($value);

            if ($header === null) {
                continue;
            }

            $normalizedHeader = $this->normalizeKey($header);

            if (in_array($normalizedHeader, self::HEADER_ALIASES['fiscal_code'], true)) {
                continue;
            }

            $jobTaskId = $jobTasksByCode[$normalizedHeader] ?? null;

            if ($jobTaskId !== null) {
                $taskColumns[$column] = $jobTaskId;
            }
        }

        return $taskColumns;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $taskColumns
     * @return array{fiscal_code: string, job_task_ids: array<int, int>}
     */
    private function buildPayload(array $row, array $taskColumns, int $rowNumber): array
    {
        $fiscalCode = Str::upper((string) $this->requireValue($row['fiscal_code'] ?? null, $rowNumber, __('codice fiscale obbligatorio.')));
        $jobTaskIds = [];

        foreach ($taskColumns as $column => $jobTaskId) {
            if ($this->isTruthy($row['task_'.$jobTaskId] ?? null)) {
                $jobTaskIds[] = $jobTaskId;
            }
        }

        if ($jobTaskIds === []) {
            $this->fail($rowNumber, __('devi indicare almeno una mansione da associare.'));
        }

        return [
            'fiscal_code' => $fiscalCode,
            'job_task_ids' => array_values(array_unique($jobTaskIds)),
        ];
    }

    /**
     * @param  array{fiscal_code: string, job_task_ids: array<int, int>}  $payload
     */
    private function assignTasks(array $payload, int $rowNumber): void
    {
        $user = User::query()
            ->with('jobTasks')
            ->where('fiscal_code', $payload['fiscal_code'])
            ->first();

        if ($user === null) {
            $this->fail($rowNumber, __('utente con codice fiscale :fiscal_code non trovato.', ['fiscal_code' => $payload['fiscal_code']]));
        }

        if (! $user->hasRole('user')) {
            $this->fail($rowNumber, __('utente :fiscal_code non è un lavoratore.', ['fiscal_code' => $payload['fiscal_code']]));
        }

        $employmentStartDate = $user->employment_start_date?->toDateString();
        $employmentEndDate = $user->employment_end_date?->toDateString();

        if ($employmentStartDate === null) {
            $this->fail($rowNumber, __('utente :fiscal_code non ha data di assunzione.', ['fiscal_code' => $payload['fiscal_code']]));
        }

        $originalLegacyJobTaskId = $user->job_task_id;

        $existingAssignments = $user->jobTasks
            ->map(fn (JobTask $jobTask): array => [
                'job_task_id' => $jobTask->getKey(),
                'starts_at' => $jobTask->pivot->starts_at,
                'ends_at' => $jobTask->pivot->ends_at,
            ])
            ->values();

        $existingTaskIds = $existingAssignments
            ->pluck('job_task_id')
            ->all();

        $newAssignments = collect($payload['job_task_ids'])
            ->reject(fn (int $jobTaskId): bool => in_array($jobTaskId, $existingTaskIds, true))
            ->map(fn (int $jobTaskId): array => [
                'job_task_id' => $jobTaskId,
                'starts_at' => $employmentStartDate,
                'ends_at' => $employmentEndDate,
            ]);

        if ($newAssignments->isEmpty()) {
            return;
        }

        $this->userJobAssignmentService->syncAssignments(
            $user,
            $existingAssignments->merge($newAssignments)->all(),
            true,
        );

        if ($originalLegacyJobTaskId !== null && in_array($originalLegacyJobTaskId, $existingTaskIds, true)) {
            $user->forceFill(['job_task_id' => $originalLegacyJobTaskId])->saveQuietly();
        }
    }

    /**
     * @return array<string, int>
     */
    private function jobTasksByCode(): array
    {
        if ($this->jobTasksByCode !== null) {
            return $this->jobTasksByCode;
        }

        return $this->jobTasksByCode = JobTask::query()
            ->select(['id', 'code'])
            ->get()
            ->filter(fn (JobTask $jobTask): bool => filled($jobTask->code))
            ->mapWithKeys(fn (JobTask $jobTask): array => [$this->normalizeKey((string) $jobTask->code) => $jobTask->getKey()])
            ->all();
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

    private function isTruthy(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return Str::upper($value) === 'SI';
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
