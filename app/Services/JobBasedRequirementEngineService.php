<?php

namespace App\Services;

use App\Models\JobBasedRequirement;
use App\Models\RequirementCalculationRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JobBasedRequirementEngineService
{
    /**
     * @return Collection<int, array{
     *     requirement_id: int,
     *     valid_from: string,
     *     is_active: bool,
     *     calculated_at: string
     * }>
     */
    public function recalculateUser(User $user, ?CarbonInterface $referenceDate = null): Collection
    {
        $anchorDate = CarbonImmutable::instance($referenceDate ?? today());
        $calculatedAt = now();

        $user->loadMissing('jobRole', 'jobTasks');

        $matchingRequirements = JobBasedRequirement::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (JobBasedRequirement $requirement) use ($anchorDate, $calculatedAt, $user): ?array {
                $validFrom = $this->firstMatchingDate($user, $requirement, $anchorDate);

                if ($validFrom === null) {
                    return null;
                }

                return [
                    'user_id' => (int) $user->getKey(),
                    'job_based_requirement_id' => (int) $requirement->getKey(),
                    'is_active' => $validFrom->lte($anchorDate),
                    'valid_from' => $validFrom->toDateString(),
                    'calculated_at' => $calculatedAt,
                    'created_at' => $calculatedAt,
                    'updated_at' => $calculatedAt,
                ];
            })
            ->filter()
            ->values();

        DB::transaction(function () use ($calculatedAt, $matchingRequirements, $user): void {
            $requirementIds = $matchingRequirements
                ->pluck('job_based_requirement_id')
                ->map(fn (mixed $requirementId): int => (int) $requirementId)
                ->all();

            DB::table('job_based_requirement_user')
                ->where('user_id', $user->getKey())
                ->when(
                    $requirementIds !== [],
                    fn (Builder|\Illuminate\Database\Query\Builder $query) => $query->whereNotIn('job_based_requirement_id', $requirementIds),
                )
                ->when(
                    $requirementIds === [],
                    fn (Builder|\Illuminate\Database\Query\Builder $query) => $query,
                )
                ->delete();

            if ($matchingRequirements->isNotEmpty()) {
                DB::table('job_based_requirement_user')->upsert(
                    $matchingRequirements->all(),
                    ['user_id', 'job_based_requirement_id'],
                    ['is_active', 'valid_from', 'calculated_at', 'updated_at'],
                );
            }

            $user->forceFill([
                'requirements_last_calculated_at' => $calculatedAt,
            ])->saveQuietly();
        });

        return $matchingRequirements->map(fn (array $item): array => [
            'requirement_id' => (int) $item['job_based_requirement_id'],
            'valid_from' => (string) $item['valid_from'],
            'is_active' => (bool) $item['is_active'],
            'calculated_at' => $calculatedAt->toIso8601String(),
        ]);
    }

    public function recalculateAll(?CarbonInterface $referenceDate = null, int $chunkSize = 200): int
    {
        $processedUsers = 0;

        User::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('job_role_id')
                    ->orWhereHas('jobTasks');
            })
            ->with(['jobRole', 'jobTasks'])
            ->chunkById($chunkSize, function ($users) use (&$processedUsers, $referenceDate): void {
                foreach ($users as $user) {
                    $this->recalculateUser($user, $referenceDate);
                    $processedUsers++;
                }
            });

        return $processedUsers;
    }

    public function promoteDueRequirements(?CarbonInterface $referenceDate = null): int
    {
        $anchorDate = CarbonImmutable::instance($referenceDate ?? today())->toDateString();
        $calculatedAt = now();

        $affectedUserIds = DB::table('job_based_requirement_user')
            ->where('is_active', false)
            ->whereDate('valid_from', '<=', $anchorDate)
            ->pluck('user_id')
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();

        $updatedRows = DB::table('job_based_requirement_user')
            ->where('is_active', false)
            ->whereDate('valid_from', '<=', $anchorDate)
            ->update([
                'is_active' => true,
                'calculated_at' => $calculatedAt,
                'updated_at' => $calculatedAt,
            ]);

        if ($affectedUserIds->isNotEmpty()) {
            User::query()
                ->whereIn('id', $affectedUserIds->all())
                ->update([
                    'requirements_last_calculated_at' => $calculatedAt,
                ]);
        }

        return $updatedRows;
    }

    /**
     * @return array{
     *     last_calculated_at: ?string,
     *     last_calculated_at_label: ?string,
     *     active_requirements: array<int, array{id: int, name: string, description: ?string, valid_from: string, valid_from_label: string}>,
     *     future_requirements: array<int, array{id: int, name: string, description: ?string, valid_from: string, valid_from_label: string}>
     * }
     */
    public function cachedSummaryForUser(User $user): array
    {
        $user->loadMissing('jobBasedRequirements');

        $items = $user->jobBasedRequirements
            ->map(function (JobBasedRequirement $requirement): array {
                $validFrom = CarbonImmutable::parse((string) $requirement->pivot->valid_from);

                return [
                    'id' => (int) $requirement->getKey(),
                    'name' => $requirement->name,
                    'description' => $requirement->description,
                    'is_active' => (bool) $requirement->pivot->is_active,
                    'valid_from' => $validFrom->toDateString(),
                    'valid_from_label' => $validFrom->format('d/m/Y'),
                ];
            })
            ->sortBy(['valid_from', 'name'])
            ->values();

        return [
            'last_calculated_at' => $user->requirements_last_calculated_at?->toIso8601String(),
            'last_calculated_at_label' => $user->requirements_last_calculated_at?->format('d/m/Y H:i'),
            'active_requirements' => $items
                ->where('is_active', true)
                ->values()
                ->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'valid_from' => $item['valid_from'],
                    'valid_from_label' => $item['valid_from_label'],
                ])
                ->all(),
            'future_requirements' => $items
                ->where('is_active', false)
                ->values()
                ->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'valid_from' => $item['valid_from'],
                    'valid_from_label' => $item['valid_from_label'],
                ])
                ->all(),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     last_completed_at: ?string,
     *     last_completed_at_label: ?string,
     *     last_completed_at_human: ?string,
     *     running_started_at: ?string
     * }
     */
    public function globalStatus(): array
    {
        $lastCompletedRun = RequirementCalculationRun::query()
            ->where('scope', 'global')
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        $runningRun = RequirementCalculationRun::query()
            ->where('scope', 'global')
            ->where('status', 'running')
            ->latest('started_at')
            ->first();

        return [
            'status' => $runningRun !== null ? 'running' : 'idle',
            'last_completed_at' => $lastCompletedRun?->finished_at?->toIso8601String(),
            'last_completed_at_label' => $lastCompletedRun?->finished_at?->format('d/m/Y H:i'),
            'last_completed_at_human' => $lastCompletedRun?->finished_at?->diffForHumans(),
            'running_started_at' => $runningRun?->started_at?->toIso8601String(),
        ];
    }

    private function firstMatchingDate(User $user, JobBasedRequirement $requirement, CarbonImmutable $anchorDate): ?CarbonImmutable
    {
        $candidateDates = $this->candidateDatesForUser($user, $anchorDate);

        foreach ($candidateDates as $candidateDate) {
            if ($this->requirementMatchesOnDate($user, $requirement, $candidateDate)) {
                return $candidateDate;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function candidateDatesForUser(User $user, CarbonImmutable $anchorDate): Collection
    {
        return collect([$anchorDate])
            ->merge(
                $user->jobTasks
                    ->map(fn ($jobTask): ?CarbonImmutable => $this->toImmutableDate($jobTask->pivot->starts_at ?? null))
                    ->filter(fn (?CarbonImmutable $date): bool => $date !== null && $date->gt($anchorDate))
            )
            ->unique(fn (CarbonImmutable $date): string => $date->toDateString())
            ->sortBy(fn (CarbonImmutable $date): string => $date->toDateString())
            ->values();
    }

    private function requirementMatchesOnDate(User $user, JobBasedRequirement $requirement, CarbonImmutable $date): bool
    {
        $ruleGroups = collect($requirement->rules ?? [])
            ->filter(fn (mixed $group): bool => is_array($group) && $group !== []);

        if ($ruleGroups->isEmpty()) {
            return false;
        }

        $activeTaskIds = $this->activeJobTaskIdsAt($user, $date);

        return $ruleGroups->contains(function (mixed $group) use ($activeTaskIds, $user): bool {
            if (! is_array($group) || $group === []) {
                return false;
            }

            return collect($group)->every(function (mixed $condition) use ($activeTaskIds, $user): bool {
                if (! is_array($condition)) {
                    return false;
                }

                $field = (string) ($condition['field'] ?? '');
                $operator = (string) ($condition['operator'] ?? '');
                $rawValue = $condition['value'] ?? null;

                return match ($field) {
                    'job_role_id' => $this->matchScalarCondition((int) ($user->job_role_id ?? 0), $operator, $rawValue),
                    'job_task_id' => $this->matchTaskCondition($activeTaskIds, $operator, $rawValue),
                    default => false,
                };
            });
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function activeJobTaskIdsAt(User $user, CarbonImmutable $referenceDate): Collection
    {
        return $user->jobTasks
            ->filter(function ($jobTask) use ($referenceDate): bool {
                $startsAt = $this->toImmutableDate($jobTask->pivot->starts_at ?? null);
                $endsAt = $this->toImmutableDate($jobTask->pivot->ends_at ?? null);

                if ($startsAt === null || $startsAt->gt($referenceDate)) {
                    return false;
                }

                return $endsAt === null || $endsAt->gte($referenceDate);
            })
            ->map(fn ($jobTask): int => (int) $jobTask->getKey())
            ->values();
    }

    private function matchScalarCondition(int $actualValue, string $operator, mixed $rawValue): bool
    {
        return match ($operator) {
            '===' => $actualValue > 0 && $actualValue === (int) $rawValue,
            'IN' => in_array($actualValue, $this->normalizeIntList($rawValue), true),
            default => false,
        };
    }

    /**
     * @param  Collection<int, int>  $activeTaskIds
     */
    private function matchTaskCondition(Collection $activeTaskIds, string $operator, mixed $rawValue): bool
    {
        if ($operator !== 'IN') {
            return false;
        }

        $expectedTaskIds = $this->normalizeIntList($rawValue);

        return $activeTaskIds->contains(fn (int $taskId): bool => in_array($taskId, $expectedTaskIds, true));
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIntList(mixed $rawValue): array
    {
        if (! is_array($rawValue)) {
            return [];
        }

        return collect($rawValue)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
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
