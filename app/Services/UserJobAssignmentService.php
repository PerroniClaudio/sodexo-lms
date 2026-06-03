<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserJobAssignmentService
{
    /**
     * @param  array<int, array<string, mixed>>  $assignments
     */
    public function syncAssignments(User $user, array $assignments, bool $requiresAssignments): void
    {
        $normalizedAssignments = $this->normalizeAssignments($assignments);

        $this->validateAssignments($user, $normalizedAssignments, $requiresAssignments);

        $user->jobTasks()->detach();

        foreach ($normalizedAssignments as $assignment) {
            $user->jobTasks()->attach($assignment['job_task_id'], [
                'starts_at' => $assignment['starts_at'],
                'ends_at' => $assignment['ends_at'],
            ]);
        }

        $user->forceFill([
            'job_task_id' => $this->resolveLegacyJobTaskId($user, $normalizedAssignments),
        ])->saveQuietly();
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignments
     * @return array<int, array{job_task_id: int, starts_at: string, ends_at: ?string}>
     */
    public function normalizeAssignments(array $assignments): array
    {
        return collect($assignments)
            ->map(function (mixed $assignment): ?array {
                if (! is_array($assignment)) {
                    return null;
                }

                $jobTaskId = (int) ($assignment['job_task_id'] ?? 0);
                $startsAt = $this->normalizeDateValue($assignment['starts_at'] ?? null);
                $endsAt = $this->normalizeDateValue($assignment['ends_at'] ?? null);

                if ($jobTaskId === 0 || $startsAt === null) {
                    return null;
                }

                return [
                    'job_task_id' => $jobTaskId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{job_task_id: int, starts_at: string, ends_at: ?string}>  $assignments
     */
    public function validateAssignments(User $user, array $assignments, bool $requiresAssignments): void
    {
        if (! $requiresAssignments) {
            return;
        }

        $employmentStartDate = $this->toImmutableDate($user->employment_start_date);
        $employmentEndDate = $this->toImmutableDate($user->employment_end_date);
        $errors = [];

        if ($employmentStartDate === null) {
            $errors['employment_start_date'] = __('La data di assunzione è obbligatoria per i lavoratori.');
        }

        if ($employmentStartDate !== null && $employmentEndDate !== null && $employmentEndDate->lt($employmentStartDate)) {
            $errors['employment_end_date'] = __('La data di cessazione non può essere precedente alla data di assunzione.');
        }

        if ($assignments === []) {
            $errors['job_tasks'] = __('Devi assegnare almeno una mansione al lavoratore.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($assignments as $index => $assignment) {
            $assignmentStartsAt = CarbonImmutable::createFromFormat('Y-m-d', $assignment['starts_at']);
            $assignmentEndsAt = $assignment['ends_at'] !== null
                ? CarbonImmutable::createFromFormat('Y-m-d', $assignment['ends_at'])
                : null;

            if ($assignmentEndsAt !== null && $assignmentEndsAt->lt($assignmentStartsAt)) {
                $errors["job_tasks.$index.ends_at"] = __('La data di fine mansione non può essere precedente alla data di inizio.');
            }
        }

        $normalizedRanges = collect($assignments)
            ->map(fn (array $assignment): array => [
                ...$assignment,
                'starts_at_carbon' => CarbonImmutable::createFromFormat('Y-m-d', $assignment['starts_at']),
                'ends_at_carbon' => $assignment['ends_at'] !== null
                    ? CarbonImmutable::createFromFormat('Y-m-d', $assignment['ends_at'])
                    : null,
            ])
            ->sortBy('starts_at')
            ->values();

        $this->ensureCoverageWindow($normalizedRanges, $employmentStartDate, $employmentEndDate, $errors);
        $this->ensureClosableAssignmentsHaveSuccessors($normalizedRanges, $employmentEndDate, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, array{job_task_id: int, starts_at: string, ends_at: ?string, starts_at_carbon: CarbonImmutable, ends_at_carbon: ?CarbonImmutable}>  $assignments
     * @param  array<string, string>  $errors
     */
    private function ensureCoverageWindow(Collection $assignments, CarbonImmutable $employmentStartDate, ?CarbonImmutable $employmentEndDate, array &$errors): void
    {
        $windowStart = $employmentStartDate->max(today()->toImmutable());

        if ($employmentEndDate !== null && $employmentEndDate->lt($windowStart)) {
            return;
        }

        $coveringAssignments = $assignments
            ->filter(fn (array $assignment): bool => $assignment['starts_at_carbon']->lte($windowStart)
                && ($assignment['ends_at_carbon'] === null || $assignment['ends_at_carbon']->gte($windowStart)))
            ->values();

        if ($coveringAssignments->isEmpty()) {
            $errors['job_tasks'] = __('Dal giorno del controllo fino alla cessazione deve esserci sempre almeno una mansione attiva.');

            return;
        }

        $coverageEnd = $this->maxCoverageEnd($coveringAssignments);

        while ($coverageEnd !== null && ($employmentEndDate === null || $coverageEnd->lt($employmentEndDate))) {
            $overlappingAssignments = $assignments
                ->filter(fn (array $assignment): bool => $assignment['starts_at_carbon']->lte($coverageEnd)
                    && ($assignment['ends_at_carbon'] === null || $assignment['ends_at_carbon']->gte($coverageEnd)))
                ->values();

            $extendedCoverageEnd = $this->maxCoverageEnd($overlappingAssignments);

            if ($extendedCoverageEnd === null) {
                return;
            }

            if ($extendedCoverageEnd->equalTo($coverageEnd)) {
                $errors['job_tasks'] = __('Le mansioni assegnate devono coprire senza interruzioni tutto il periodo lavorativo valido.');

                return;
            }

            $coverageEnd = $extendedCoverageEnd;
        }

        if ($employmentEndDate === null && $coverageEnd !== null) {
            $errors['job_tasks'] = __('Senza data di cessazione deve esistere almeno una mansione attiva senza scadenza.');
        }
    }

    /**
     * @param  Collection<int, array{job_task_id: int, starts_at: string, ends_at: ?string, starts_at_carbon: CarbonImmutable, ends_at_carbon: ?CarbonImmutable}>  $assignments
     * @param  array<string, string>  $errors
     */
    private function ensureClosableAssignmentsHaveSuccessors(Collection $assignments, ?CarbonImmutable $employmentEndDate, array &$errors): void
    {
        foreach ($assignments as $index => $assignment) {
            if ($assignment['ends_at_carbon'] === null) {
                continue;
            }

            $successor = $assignments->contains(function (array $candidate, int $candidateIndex) use ($assignment, $index, $employmentEndDate): bool {
                if ($candidateIndex === $index) {
                    return false;
                }

                if ($candidate['starts_at_carbon']->gt($assignment['ends_at_carbon'])) {
                    return false;
                }

                if ($candidate['ends_at_carbon'] !== null && $candidate['ends_at_carbon']->lt($assignment['ends_at_carbon'])) {
                    return false;
                }

                if ($employmentEndDate === null) {
                    return $candidate['ends_at_carbon'] === null;
                }

                return $candidate['ends_at_carbon'] === null || $candidate['ends_at_carbon']->gte($employmentEndDate);
            });

            if (! $successor) {
                $errors["job_tasks.$index.ends_at"] = __('Puoi chiudere una mansione solo se esiste un\'altra mansione valida in quella data e coperta fino alla cessazione.');
            }
        }
    }

    /**
     * @param  Collection<int, array{ends_at_carbon: ?CarbonImmutable}>  $assignments
     */
    private function maxCoverageEnd(Collection $assignments): ?CarbonImmutable
    {
        if ($assignments->contains(fn (array $assignment): bool => $assignment['ends_at_carbon'] === null)) {
            return null;
        }

        return $assignments
            ->pluck('ends_at_carbon')
            ->filter()
            ->sortDesc()
            ->first();
    }

    /**
     * @param  array<int, array{job_task_id: int, starts_at: string, ends_at: ?string}>  $assignments
     */
    private function resolveLegacyJobTaskId(User $user, array $assignments): ?int
    {
        if ($assignments === []) {
            return null;
        }

        $anchorDate = ($user->employment_start_date?->isFuture() ?? false)
            ? $user->employment_start_date->toImmutable()
            : today()->toImmutable();

        $normalizedAssignments = collect($assignments)
            ->map(fn (array $assignment): array => [
                ...$assignment,
                'starts_at_carbon' => CarbonImmutable::createFromFormat('Y-m-d', $assignment['starts_at']),
                'ends_at_carbon' => $assignment['ends_at'] !== null
                    ? CarbonImmutable::createFromFormat('Y-m-d', $assignment['ends_at'])
                    : null,
            ]);

        $activeAssignment = $normalizedAssignments
            ->filter(fn (array $assignment): bool => $assignment['starts_at_carbon']->lte($anchorDate)
                && ($assignment['ends_at_carbon'] === null || $assignment['ends_at_carbon']->gte($anchorDate)))
            ->sortByDesc('starts_at')
            ->first();

        if ($activeAssignment !== null) {
            return $activeAssignment['job_task_id'];
        }

        $futureAssignment = $normalizedAssignments
            ->filter(fn (array $assignment): bool => $assignment['starts_at_carbon']->gt($anchorDate))
            ->sortBy('starts_at')
            ->first();

        if ($futureAssignment !== null) {
            return $futureAssignment['job_task_id'];
        }

        return $normalizedAssignments
            ->sortByDesc(fn (array $assignment): int => $assignment['ends_at_carbon']?->timestamp ?? PHP_INT_MAX)
            ->first()['job_task_id'] ?? null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return null;
        }

        return CarbonImmutable::parse($trimmedValue)->toDateString();
    }

    private function toImmutableDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
