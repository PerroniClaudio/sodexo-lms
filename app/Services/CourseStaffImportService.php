<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CourseStaffImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'email' => ['email'],
        'course_code' => ['codice_corso', 'course_code'],
        'role' => ['ruolo', 'role'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $coursesByCode = null;

    /**
     * @return array{processed_records: int}
     */
    public function import(string $localFilePath): array
    {
        $rows = $this->rowsFromSpreadsheet($localFilePath);
        $seenAssignments = [];

        DB::transaction(function () use ($rows, &$seenAssignments): void {
            foreach ($rows as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildPayload($row, $rowNumber);
                $assignmentKey = implode('-', [$payload['email'], $payload['course_id'], $payload['role']]);

                if (isset($seenAssignments[$assignmentKey])) {
                    $this->fail($rowNumber, __('assegnazione duplicata nel file.'));
                }

                $seenAssignments[$assignmentKey] = true;
                $this->assign($payload, $rowNumber);
            }
        });

        return ['processed_records' => collect($rows)->reject(fn (array $row): bool => $this->rowIsEmpty($row))->count()];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromSpreadsheet(string $localFilePath): array
    {
        $spreadsheet = IOFactory::load($localFilePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headerMap = $this->resolveHeaderMap($rows[1] ?? []);

        foreach (array_keys(self::HEADER_ALIASES) as $requiredHeader) {
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
                'email' => $this->nullableString($row[$headerMap['email']] ?? null),
                'course_code' => $this->nullableString($row[$headerMap['course_code']] ?? null),
                'role' => $this->nullableString($row[$headerMap['role']] ?? null),
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
     * @return array{email: string, course_id: int, role: 'teacher'|'tutor'}
     */
    private function buildPayload(array $row, int $rowNumber): array
    {
        $email = Str::lower($this->requireValue($row['email'] ?? null, $rowNumber, __('email obbligatoria.')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail($rowNumber, __('email non valida: :value', ['value' => $row['email']]));
        }

        $courseCode = $this->normalizeKey($this->requireValue($row['course_code'] ?? null, $rowNumber, __('codice corso obbligatorio.')));
        $courseId = $this->coursesByCode()[$courseCode] ?? null;

        if ($courseId === null) {
            $this->fail($rowNumber, __('corso non valido: :value', ['value' => $row['course_code']]));
        }

        $role = match ($this->normalizeKey($this->requireValue($row['role'] ?? null, $rowNumber, __('ruolo obbligatorio.')))) {
            'docente', 'teacher' => 'teacher',
            'tutor' => 'tutor',
            default => null,
        };

        if ($role === null) {
            $this->fail($rowNumber, __('ruolo non valido: usa docente oppure tutor.'));
        }

        return ['email' => $email, 'course_id' => $courseId, 'role' => $role];
    }

    /**
     * @param  array{email: string, course_id: int, role: 'teacher'|'tutor'}  $payload
     */
    private function assign(array $payload, int $rowNumber): void
    {
        $user = User::query()->where('email', $payload['email'])->first();

        if ($user === null) {
            $this->fail($rowNumber, __('utente con email :email non trovato.', ['email' => $payload['email']]));
        }

        $course = Course::query()->find($payload['course_id']);

        if ($course === null) {
            $this->fail($rowNumber, __('corso non trovato.'));
        }

        if ($payload['role'] === 'teacher' && ! $user->hasAnyRole(['teacher', 'docente'])) {
            $this->fail($rowNumber, __('l\'utente :email non ha il ruolo docente.', ['email' => $payload['email']]));
        }

        if ($payload['role'] === 'tutor' && ! $user->hasRole('tutor')) {
            $this->fail($rowNumber, __('l\'utente :email non ha il ruolo tutor.', ['email' => $payload['email']]));
        }

        $payload['role'] === 'teacher'
            ? $this->persistAssignment(CourseTeacherEnrollment::class, $user, $course)
            : $this->persistAssignment(CourseTutorEnrollment::class, $user, $course);
    }

    /**
     * @param  class-string<CourseTeacherEnrollment|CourseTutorEnrollment>  $modelClass
     */
    private function persistAssignment(string $modelClass, User $user, Course $course): void
    {
        $assignment = $modelClass::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $user->getKey())
            ->first();

        if ($assignment !== null && ! $assignment->trashed()) {
            return;
        }

        if ($assignment !== null) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();

            return;
        }

        $modelClass::enroll($user, $course);
    }

    /**
     * @return array<string, int>
     */
    private function coursesByCode(): array
    {
        return $this->coursesByCode ??= Course::query()
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
        return $this->nullableString($value) ?? $this->fail($rowNumber, $message);
    }

    private function nullableString(mixed $value): ?string
    {
        $cleanValue = $value === null ? '' : trim((string) $value);

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
            'file' => __('Riga :row: :message', ['row' => $rowNumber, 'message' => $message]),
        ]);
    }
}
