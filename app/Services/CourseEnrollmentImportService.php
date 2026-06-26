<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Importazione;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CourseEnrollmentImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'fiscal_code' => ['codice_fiscale', 'cf'],
        'course_code' => ['codice_corso', 'course_code'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $coursesByCode = null;

    public function __construct(
        private readonly CourseRiskRequirementService $courseRiskRequirementService,
        private readonly SyncCourseModuleProgresses $syncCourseModuleProgresses,
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
                $associationKey = $payload['fiscal_code'].'-'.$payload['course_id'];

                if (isset($seenAssociations[$associationKey])) {
                    $this->fail($rowNumber, __('associazione utente-corso duplicata nel file.'));
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

        foreach (['fiscal_code', 'course_code'] as $requiredHeader) {
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
                'course_code' => $this->nullableString($row[$headerMap['course_code']] ?? null),
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
     * @return array{fiscal_code: string, course_id: int}
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        $fiscalCode = Str::upper((string) $this->requireValue($row['fiscal_code'] ?? null, $rowNumber, __('codice fiscale obbligatorio.')));
        $courseCode = $this->normalizeKey($this->requireValue($row['course_code'] ?? null, $rowNumber, __('codice corso obbligatorio.')));
        $courseId = $this->coursesByCode()[$courseCode] ?? null;

        if ($courseId === null) {
            $this->fail($rowNumber, __('corso non valido: :value', ['value' => $row['course_code']]));
        }

        return [
            'fiscal_code' => $fiscalCode,
            'course_id' => $courseId,
        ];
    }

    /**
     * @param  array{fiscal_code: string, course_id: int}  $payload
     */
    private function enroll(array $payload, int $rowNumber): void
    {
        $user = User::query()
            ->where('fiscal_code', $payload['fiscal_code'])
            ->first();

        if ($user === null) {
            $this->fail($rowNumber, __('utente con codice fiscale :fiscal_code non trovato.', ['fiscal_code' => $payload['fiscal_code']]));
        }

        $course = Course::query()->find($payload['course_id']);

        if ($course === null) {
            $this->fail($rowNumber, __('corso non trovato.'));
        }

        if ($course->status !== 'published') {
            $this->fail($rowNumber, __('corso :code non pubblicato.', ['code' => $course->code]));
        }

        $existingEnrollment = CourseEnrollment::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
            $existingEnrollment->mergeOrigins(true, (bool) $existingEnrollment->pathway_origin);

            return;
        }

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            $visibilityError = $this->courseVisibilityError($course, $user);

            if ($visibilityError !== null) {
                $this->fail($rowNumber, $visibilityError);
            }

            $existingEnrollment->restore();
            $existingEnrollment->mergeOrigins(true, (bool) $existingEnrollment->pathway_origin);
            $this->syncCourseModuleProgresses->handle($course);

            return;
        }

        if (! $this->courseRiskRequirementService->userCanEnrollInCourse($user, $course)) {
            $this->fail($rowNumber, __('L\'utente non possiede i prerequisiti necessari per l\'iscrizione a questo corso.'));
        }

        $visibilityError = $this->courseVisibilityError($course, $user);

        if ($visibilityError !== null) {
            $this->fail($rowNumber, $visibilityError);
        }

        CourseEnrollment::enroll($user, $course, directOrigin: true, pathwayOrigin: false);
    }

    /**
     * @return array<string, int>
     */
    private function coursesByCode(): array
    {
        if ($this->coursesByCode !== null) {
            return $this->coursesByCode;
        }

        return $this->coursesByCode = Course::query()
            ->select(['id', 'code'])
            ->get()
            ->filter(fn (Course $course): bool => filled($course->code))
            ->mapWithKeys(fn (Course $course): array => [$this->normalizeKey((string) $course->code) => $course->getKey()])
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

    private function courseVisibilityError(Course $course, User $user): ?string
    {
        if ($course->isVisibleTo($user)) {
            return null;
        }

        return __('L\'utente non rientra tra i destinatari del corso ":title", quindi l\'iscrizione non è stata creata.', [
            'title' => $course->title,
        ]);
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
