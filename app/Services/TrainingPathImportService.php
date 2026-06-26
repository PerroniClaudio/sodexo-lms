<?php

namespace App\Services;

use App\Models\Importazione;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TrainingPathImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'fiscal_code' => ['codice_fiscale', 'cf'],
        'training_path_code' => ['codice_percorso_formativo', 'codice_percorso', 'training_path_code'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $trainingPathsByCode = null;

    public function __construct(
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
    ) {}

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
                $associationKey = $payload['fiscal_code'].'-'.$payload['training_path_id'];

                if (isset($seenAssociations[$associationKey])) {
                    $this->fail($rowNumber, __('associazione utente-percorso duplicata nel file.'));
                }

                $seenAssociations[$associationKey] = true;

                $this->enroll($payload, $rowNumber);
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

        foreach (['fiscal_code', 'training_path_code'] as $requiredHeader) {
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

            $mappedRows[$rowNumber] = [
                'fiscal_code' => $this->nullableString($row[$headerMap['fiscal_code']] ?? null),
                'training_path_code' => $this->nullableString($row[$headerMap['training_path_code']] ?? null),
            ];
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
     * @return array{fiscal_code: string, training_path_id: int}
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        $fiscalCode = Str::upper((string) $this->requireValue($row['fiscal_code'] ?? null, $rowNumber, __('codice fiscale obbligatorio.')));
        $trainingPathCode = $this->normalizeKey($this->requireValue($row['training_path_code'] ?? null, $rowNumber, __('codice percorso formativo obbligatorio.')));
        $trainingPathId = $this->trainingPathsByCode()[$trainingPathCode] ?? null;

        if ($trainingPathId === null) {
            $this->fail($rowNumber, __('percorso formativo non valido: :value', ['value' => $row['training_path_code']]));
        }

        return [
            'fiscal_code' => $fiscalCode,
            'training_path_id' => $trainingPathId,
        ];
    }

    /**
     * @param  array{fiscal_code: string, training_path_id: int}  $payload
     */
    private function enroll(array $payload, int $rowNumber): void
    {
        $user = User::query()
            ->where('fiscal_code', $payload['fiscal_code'])
            ->first();

        if ($user === null) {
            $this->fail($rowNumber, __('utente con codice fiscale :fiscal_code non trovato.', ['fiscal_code' => $payload['fiscal_code']]));
        }

        $trainingPath = TrainingPath::query()->find($payload['training_path_id']);

        if ($trainingPath === null) {
            $this->fail($rowNumber, __('percorso formativo non trovato.'));
        }

        if ($trainingPath->status !== 'published') {
            $this->fail($rowNumber, __('percorso formativo :code non pubblicato.', ['code' => $trainingPath->code]));
        }

        $visibilityErrors = $trainingPath->enrollmentVisibilityErrorsFor($user);

        if ($visibilityErrors !== []) {
            $this->fail($rowNumber, collect($visibilityErrors)->implode(' '));
        }

        $existingEnrollment = TrainingPathEnrollment::withTrashed()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            $existingEnrollment->restore();
            $existingEnrollment->refresh();
            $this->trainingPathEnrollmentSyncService->syncEnrollment($existingEnrollment);

            return;
        }

        if ($existingEnrollment !== null) {
            $this->trainingPathEnrollmentSyncService->syncEnrollment($existingEnrollment);

            return;
        }

        $enrollment = TrainingPathEnrollment::enroll($user, $trainingPath);
        $this->trainingPathEnrollmentSyncService->syncEnrollment($enrollment);
    }

    /**
     * @return array<string, int>
     */
    private function trainingPathsByCode(): array
    {
        if ($this->trainingPathsByCode !== null) {
            return $this->trainingPathsByCode;
        }

        return $this->trainingPathsByCode = TrainingPath::query()
            ->select(['id', 'code'])
            ->get()
            ->filter(fn (TrainingPath $trainingPath): bool => filled($trainingPath->code))
            ->mapWithKeys(fn (TrainingPath $trainingPath): array => [$this->normalizeKey((string) $trainingPath->code) => $trainingPath->getKey()])
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

    private function normalizeKey(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->squish()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->lower()
            ->value();
    }

    private function fail(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('Riga :row: :message', [
                'row' => $rowNumber,
                'message' => $message,
            ]),
        ]);
    }
}
