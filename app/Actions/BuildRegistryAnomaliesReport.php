<?php

namespace App\Actions;

use App\Models\JobTask;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildRegistryAnomaliesReport
{
    /**
     * @return array{
     *     generated_at: string,
     *     scope_label: string,
     *     total_users: int,
     *     overall_completeness_percentage: int,
     *     fully_complete_percentage: int,
     *     sections: array<string, array{total: int, complete: int, complete_percentage: int, completeness_percentage: int}>,
     *     severity_counts: array<string, int>,
     *     anomalies: Collection<int, array<string, string>>,
     *     job_tasks: Collection<int, array<string, mixed>>
     * }
     */
    public function __invoke(?int $companyDivisionId): array
    {
        $today = now()->toDateString();
        $users = User::query()
            ->select([
                'id', 'name', 'surname', 'email', 'fiscal_code', 'birth_date',
                'employment_start_date', 'employment_end_date', 'is_foreigner_or_immigrant',
                'job_role_id', 'job_sector_id', 'job_unit_id', 'company_division_id',
            ])
            ->when($companyDivisionId !== null, fn ($query) => $query->where('company_division_id', $companyDivisionId))
            ->with([
                'roles:id,name',
                'jobRole:id,name',
                'jobSector:id,name',
                'jobUnit:id,name',
                'jobTasks' => fn ($query) => $query
                    ->select(['job_tasks.id', 'job_tasks.name', 'job_tasks.global_risk_level'])
                    ->with('jobSectors:id,name'),
            ])
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $anomalies = collect();
        $workerStats = $this->emptyStats();
        $otherStats = $this->emptyStats();
        $activeTaskUsage = [];

        foreach ($users as $user) {
            $isWorker = $user->roles->contains('name', 'user');
            $stats = $isWorker ? $workerStats : $otherStats;
            $requiredFields = $this->requiredFields($user, $isWorker);
            $missingFields = collect($requiredFields)
                ->filter(fn (mixed $value): bool => $value === null || $value === '')
                ->keys()
                ->all();

            $stats['total']++;
            $stats['fields_total'] += count($requiredFields);
            $stats['fields_filled'] += count($requiredFields) - count($missingFields);

            if ($missingFields === []) {
                $stats['complete']++;
            } else {
                $this->addAnomaly($anomalies, $user, $isWorker, 'Dati obbligatori mancanti', 'Alta', implode(', ', $missingFields), 'Completare i campi obbligatori.');
            }

            if (! $isWorker) {
                $otherStats = $stats;

                continue;
            }

            $activeTasks = $user->jobTasks
                ->filter(fn (JobTask $jobTask): bool => $this->isActiveToday($jobTask, $today))
                ->values();

            foreach ($activeTasks as $jobTask) {
                $activeTaskUsage[$jobTask->getKey()] = ($activeTaskUsage[$jobTask->getKey()] ?? 0) + 1;
            }

            if ($activeTasks->isEmpty()) {
                $this->addAnomaly($anomalies, $user, true, 'Mansione attiva assente', 'Alta', 'Nessuna mansione valida alla data odierna.', 'Assegnare o aggiornare la mansione del lavoratore.');
            }

            $this->addDateAnomalies($anomalies, $user, $isWorker);

            if ($user->jobSector !== null) {
                foreach ($activeTasks as $jobTask) {
                    $hasSectorRisk = $jobTask->jobSectors->contains('id', $user->job_sector_id);

                    if ($jobTask->global_risk_level === null && ! $hasSectorRisk) {
                        $this->addAnomaly($anomalies, $user, true, 'Mansione non classificata', 'Alta', $jobTask->name, 'Configurare rischio globale o associazione mansione-settore.');
                    }
                }
            }

            $workerStats = $stats;
        }

        $this->addDuplicateAnomalies($anomalies, $users, 'fiscal_code', 'Codice fiscale duplicato', 'Critica', 'Correggere o unificare le anagrafiche.');
        $this->addDuplicateAnomalies($anomalies, $users, 'email', 'Email duplicata', 'Alta', 'Verificare indirizzo email associato.');
        $this->addPersonalDuplicateAnomalies($anomalies, $users);

        $sections = [
            'workers' => $this->finalizeStats($workerStats),
            'other_users' => $this->finalizeStats($otherStats),
        ];
        $totalFields = $workerStats['fields_total'] + $otherStats['fields_total'];
        $filledFields = $workerStats['fields_filled'] + $otherStats['fields_filled'];
        $totalUsers = $users->count();

        return [
            'generated_at' => now()->format('d/m/Y H:i'),
            'scope_label' => $companyDivisionId === null ? 'tutte le divisioni' : 'divisione attiva',
            'total_users' => $totalUsers,
            'overall_completeness_percentage' => $totalFields === 0 ? 100 : (int) round($filledFields / $totalFields * 100),
            'fully_complete_percentage' => $totalUsers === 0 ? 100 : (int) round(($workerStats['complete'] + $otherStats['complete']) / $totalUsers * 100),
            'sections' => $sections,
            'severity_counts' => collect(['Critica', 'Alta', 'Media'])->mapWithKeys(fn (string $severity): array => [$severity => $anomalies->where('severity', $severity)->count()])->all(),
            'anomalies' => $anomalies
                ->sortBy([
                    [fn (array $anomaly): int => match ($anomaly['severity']) {
                        'Critica' => 1,
                        'Alta' => 2,
                        default => 3,
                    }],
                    ['user_name', 'asc'],
                ])
                ->values(),
            'job_tasks' => $this->jobTaskRows($activeTaskUsage),
        ];
    }

    /** @return array<string, int> */
    private function emptyStats(): array
    {
        return ['total' => 0, 'complete' => 0, 'fields_total' => 0, 'fields_filled' => 0];
    }

    /** @param array<string, int> $stats @return array{total: int, complete: int, complete_percentage: int, completeness_percentage: int} */
    private function finalizeStats(array $stats): array
    {
        return [
            'total' => $stats['total'],
            'complete' => $stats['complete'],
            'complete_percentage' => $stats['total'] === 0 ? 100 : (int) round($stats['complete'] / $stats['total'] * 100),
            'completeness_percentage' => $stats['fields_total'] === 0 ? 100 : (int) round($stats['fields_filled'] / $stats['fields_total'] * 100),
        ];
    }

    /** @return array<string, mixed> */
    private function requiredFields(User $user, bool $isWorker): array
    {
        $fields = [
            'Nome' => trim((string) $user->name),
            'Cognome' => trim((string) $user->surname),
            'Codice fiscale' => trim((string) $user->fiscal_code),
        ];

        if (! $isWorker) {
            return $fields;
        }

        return [...$fields, ...[
            'Stato straniero/immigrato' => $user->getRawOriginal('is_foreigner_or_immigrant'),
            'Data assunzione' => $user->employment_start_date?->toDateString(),
            'Ruolo' => $user->jobRole?->name,
            'Settore' => $user->jobSector?->name,
            'Unità lavorativa' => $user->jobUnit?->name,
            'Mansione' => $user->jobTasks->isNotEmpty() ? 'configured' : null,
        ]];
    }

    private function isActiveToday(JobTask $jobTask, string $today): bool
    {
        $startsAt = (string) ($jobTask->pivot->starts_at ?? '');
        $endsAt = $jobTask->pivot->ends_at;

        return $startsAt !== '' && $startsAt <= $today && ($endsAt === null || $endsAt >= $today);
    }

    private function addDateAnomalies(Collection $anomalies, User $user, bool $isWorker): void
    {
        if ($user->employment_start_date !== null && $user->employment_end_date !== null && $user->employment_end_date->lt($user->employment_start_date)) {
            $this->addAnomaly($anomalies, $user, $isWorker, 'Date di impiego incoerenti', 'Alta', 'Cessazione precedente all’assunzione.', 'Correggere le date di impiego.');
        }

        foreach ($user->jobTasks as $jobTask) {
            $startsAt = (string) ($jobTask->pivot->starts_at ?? '');
            $endsAt = $jobTask->pivot->ends_at;

            if ($startsAt === '' || ($endsAt !== null && $endsAt < $startsAt)) {
                $this->addAnomaly($anomalies, $user, $isWorker, 'Date mansione incoerenti', 'Alta', $jobTask->name, 'Correggere intervallo di assegnazione mansione.');
            }
        }
    }

    private function addDuplicateAnomalies(Collection $anomalies, Collection $users, string $field, string $category, string $severity, string $action): void
    {
        $users->groupBy(fn (User $user): string => mb_strtolower(trim((string) $user->{$field})))
            ->filter(fn (Collection $group, string $value): bool => $value !== '' && $group->count() > 1)
            ->each(function (Collection $group) use ($anomalies, $category, $severity, $action): void {
                $group->each(fn (User $user) => $this->addAnomaly($anomalies, $user, $user->roles->contains('name', 'user'), $category, $severity, 'Valore condiviso con '.$group->count().' utenti.', $action));
            });
    }

    private function addPersonalDuplicateAnomalies(Collection $anomalies, Collection $users): void
    {
        $users->groupBy(function (User $user): string {
            if ($user->birth_date === null) {
                return '';
            }

            return implode('|', [mb_strtolower(trim($user->name)), mb_strtolower(trim($user->surname)), $user->birth_date->toDateString()]);
        })->filter(fn (Collection $group, string $key): bool => $key !== '' && $group->count() > 1)
            ->each(function (Collection $group) use ($anomalies): void {
                $group->each(fn (User $user) => $this->addAnomaly($anomalies, $user, $user->roles->contains('name', 'user'), 'Possibile duplicato anagrafico', 'Media', 'Stesso nome, cognome e data di nascita.', 'Verificare che le anagrafiche non rappresentino la stessa persona.'));
            });
    }

    private function addAnomaly(Collection $anomalies, User $user, bool $isWorker, string $category, string $severity, string $detail, string $action): void
    {
        $anomalies->push([
            'section' => $isWorker ? 'Lavoratori' : 'Altri utenti',
            'user_name' => trim($user->surname.' '.$user->name),
            'category' => $category,
            'severity' => $severity,
            'detail' => $detail,
            'action' => $action,
        ]);
    }

    /** @param array<int, int> $activeTaskUsage @return Collection<int, array<string, mixed>> */
    private function jobTaskRows(array $activeTaskUsage): Collection
    {
        return JobTask::query()
            ->select(['id', 'name', 'global_risk_level'])
            ->with('jobSectors:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (JobTask $jobTask) use ($activeTaskUsage): array {
                $sectorRisks = $jobTask->jobSectors
                    ->map(fn ($sector): string => $sector->name.' - '.($sector->pivot->task_risk_level ?? 'n/d'))
                    ->join('; ');

                return [
                    'name' => $jobTask->name,
                    'users_count' => $activeTaskUsage[$jobTask->getKey()] ?? 0,
                    'risk' => $jobTask->global_risk_level?->label() ?? ($sectorRisks !== '' ? $sectorRisks : 'Non classificata'),
                    'classified' => $jobTask->global_risk_level !== null || $sectorRisks !== '',
                ];
            });
    }
}
