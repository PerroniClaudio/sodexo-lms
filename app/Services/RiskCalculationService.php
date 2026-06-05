<?php

namespace App\Services;

use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\NaceAteco;
use App\Models\RiskBasedRequirement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskCalculationService
{
    public function getSectorRiskLevel(int $jobSectorId): RiskLevel
    {
        $jobSector = JobSector::findOrFail($jobSectorId);

        $inclusions = DB::table('job_sector_nace_ateco')
            ->where('job_sector_id', $jobSectorId)
            ->get();

        $manualRiskLevel = $jobSector->manual_risk_level instanceof RiskLevel
            ? $jobSector->manual_risk_level
            : RiskLevel::tryFrom((string) $jobSector->manual_risk_level);

        if ($inclusions->isEmpty()) {
            return $manualRiskLevel ?? RiskLevel::LOW;
        }

        $allRisks = collect();

        foreach ($inclusions as $inclusion) {
            $inclusionType = InclusionType::from($inclusion->inclusion_type);
            $naceAtecoCode = $inclusion->nace_ateco_code;
            $codes = $this->expandInclusionCodes($naceAtecoCode, $inclusionType);

            $risks = NaceAteco::whereIn('code', $codes)
                ->whereNotNull('risk')
                ->get()
                ->pluck('risk')
                ->filter();

            $allRisks = $allRisks->merge($risks);
        }

        $calculatedRisk = $this->getHighestRisk($allRisks);

        if ($manualRiskLevel === null) {
            return $calculatedRisk;
        }

        return $calculatedRisk->max($manualRiskLevel);
    }

    public function getEffectiveWorkerRisk(int $jobSectorId, int $jobTaskId): RiskLevel
    {
        return $this->getEffectiveWorkerRiskForTasks($jobSectorId, [$jobTaskId]);
    }

    /**
     * @param  iterable<int, int>  $jobTaskIds
     */
    public function getEffectiveWorkerRiskForTasks(int $jobSectorId, iterable $jobTaskIds): RiskLevel
    {
        $sectorRisk = $this->getSectorRiskLevel($jobSectorId);
        $normalizedJobTaskIds = collect($jobTaskIds)
            ->map(fn (mixed $jobTaskId): int => (int) $jobTaskId)
            ->filter(fn (int $jobTaskId): bool => $jobTaskId > 0)
            ->unique()
            ->values();
        $taskRisks = $this->getTaskRisksInSector($jobSectorId, $normalizedJobTaskIds);

        if ($taskRisks->isEmpty()) {
            return $sectorRisk;
        }

        $highestTaskRisk = $this->getHighestRisk($taskRisks->pluck('risk_level'));

        // The sector risk can be overridden only when every active task assigned to the user
        // has an explicit mapping for this sector and every mapping is flagged for override.
        // If even one task is missing a sector-specific mapping, or has the override flag off,
        // the native sector risk remains in force for all tasks.
        $allTasksAllowSectorOverride = $normalizedJobTaskIds->count() === $taskRisks->count()
            && $taskRisks->every(fn (array $taskRisk): bool => $taskRisk['sector_risk_override'] === true);

        return $allTasksAllowSectorOverride
            ? $highestTaskRisk
            : $sectorRisk->max($highestTaskRisk);
    }

    /**
     * @return array<int, RiskBasedRequirement>
     */
    public function getRiskBasedRequirementsForRiskLevel(RiskLevel $riskLevel): array
    {
        return RiskBasedRequirement::query()
            ->forRiskLevel($riskLevel)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function findSectorByAtecoCode(string $fullAtecoCode): ?JobSector
    {
        $naceAteco = NaceAteco::find($fullAtecoCode);

        if (! $naceAteco) {
            return null;
        }

        $hierarchyChain = $this->buildHierarchyChain($fullAtecoCode, $naceAteco->section);

        foreach ($hierarchyChain as $code => $inclusionType) {
            $jobSector = JobSector::whereHas('naceAtecoCodes', function ($query) use ($code, $inclusionType) {
                $query->where('nace_ateco_code', $code)
                    ->where('inclusion_type', $inclusionType->value);
            })->first();

            if ($jobSector) {
                return $jobSector;
            }
        }

        return null;
    }

    public function getSectionForCode(string $atecoCode): ?NaceAteco
    {
        $code = NaceAteco::find($atecoCode);

        if (! $code || ! $code->section) {
            return null;
        }

        return NaceAteco::where('code', $code->section)->first();
    }

    protected function expandInclusionCodes(string $code, InclusionType $inclusionType): array
    {
        if ($inclusionType === InclusionType::FULL_CODE) {
            return [$code];
        }

        $baseCode = NaceAteco::find($code);

        if (! $baseCode) {
            return [$code];
        }

        $hierarchyLevel = $inclusionType->toHierarchyLevel();

        if ($inclusionType === InclusionType::SECTION) {
            return NaceAteco::where('section', $code)->pluck('code')->toArray();
        }

        return NaceAteco::where('code', 'LIKE', $code.'%')
            ->where('hierarchy', '>=', $hierarchyLevel->value)
            ->pluck('code')
            ->toArray();
    }

    /**
     * @return array<string, InclusionType>
     */
    protected function buildHierarchyChain(string $fullCode, ?string $section): array
    {
        $chain = [];
        $chain[$fullCode] = InclusionType::FULL_CODE;

        if (preg_match('/^(\d{2}\.\d{2})\./', $fullCode, $matches)) {
            $chain[$matches[1]] = InclusionType::CATEGORY;

            if (preg_match('/^(\d{2})\./', $matches[1], $classMatches)) {
                $chain[$classMatches[1]] = InclusionType::NACE_CLASS;
            }
        } elseif (preg_match('/^(\d{2})\./', $fullCode, $matches)) {
            $chain[$matches[1]] = InclusionType::NACE_CLASS;
        }

        if ($section) {
            $chain[$section] = InclusionType::SECTION;
        }

        return $chain;
    }

    protected function getTaskRiskInSector(int $jobSectorId, int $jobTaskId): ?RiskLevel
    {
        $pivot = DB::table('job_task_job_sector')
            ->where('job_sector_id', $jobSectorId)
            ->where('job_task_id', $jobTaskId)
            ->first();

        if (! $pivot || ! $pivot->task_risk_level) {
            return null;
        }

        return RiskLevel::tryFrom($pivot->task_risk_level);
    }

    /**
     * @param  iterable<int, int>  $jobTaskIds
     * @return Collection<int, array{job_task_id: int, risk_level: RiskLevel, sector_risk_override: bool}>
     */
    protected function getTaskRisksInSector(int $jobSectorId, iterable $jobTaskIds): Collection
    {
        $normalizedJobTaskIds = collect($jobTaskIds)
            ->map(fn (mixed $jobTaskId): int => (int) $jobTaskId)
            ->filter(fn (int $jobTaskId): bool => $jobTaskId > 0)
            ->unique()
            ->values();

        if ($normalizedJobTaskIds->isEmpty()) {
            return collect();
        }

        return DB::table('job_tasks')
            ->leftJoin('job_task_job_sector', function ($join) use ($jobSectorId) {
                $join->on('job_tasks.id', '=', 'job_task_job_sector.job_task_id')
                    ->where('job_task_job_sector.job_sector_id', '=', $jobSectorId);
            })
            ->whereIn('job_tasks.id', $normalizedJobTaskIds)
            ->whereNull('job_tasks.deleted_at')
            ->select([
                'job_tasks.id as job_task_id',
                'job_task_job_sector.task_risk_level as sector_task_risk_level',
                'job_task_job_sector.sector_risk_override as sector_risk_override',
                'job_tasks.global_risk_level',
                'job_tasks.global_sector_risk_override',
            ])
            ->get()
            ->map(function (object $row): ?array {
                // Priorità 1: se c'è l'associazione job_task_job_sector, usa quella
                if ($row->sector_task_risk_level !== null) {
                    $riskLevel = RiskLevel::tryFrom($row->sector_task_risk_level);

                    if ($riskLevel === null) {
                        return null;
                    }

                    return [
                        'job_task_id' => (int) $row->job_task_id,
                        'risk_level' => $riskLevel,
                        'sector_risk_override' => (bool) $row->sector_risk_override,
                    ];
                }

                // Priorità 2: se sono impostati global_risk_level e global_sector_risk_override, usa quelli
                if ($row->global_risk_level !== null) {
                    $riskLevel = RiskLevel::tryFrom($row->global_risk_level);

                    if ($riskLevel === null) {
                        return null;
                    }

                    return [
                        'job_task_id' => (int) $row->job_task_id,
                        'risk_level' => $riskLevel,
                        'sector_risk_override' => (bool) $row->global_sector_risk_override,
                    ];
                }

                // Priorità 3: nessun rischio definito per questa mansione
                return null;
            })
            ->filter()
            ->values();
    }

    protected function getHighestRisk(Collection $risks): RiskLevel
    {
        if ($risks->isEmpty()) {
            return RiskLevel::LOW;
        }

        return $risks->reduce(
            fn (?RiskLevel $carry, RiskLevel $risk) => $carry === null ? $risk : $carry->max($risk),
            null
        ) ?? RiskLevel::LOW;
    }
}
