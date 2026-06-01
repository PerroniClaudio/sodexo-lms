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
    /**
     * Funzione 1: Calcola il rischio nativo del settore personalizzato
     * Analizza tutti i codici ATECO inclusi nella pivot per quel settore
     * (esplodendo le gerarchie se necessario) e restituisce il livello di rischio più alto
     */
    public function getSectorRiskLevel(int $jobSectorId): RiskLevel
    {
        JobSector::findOrFail($jobSectorId);

        $inclusions = DB::table('job_sector_nace_ateco')
            ->where('job_sector_id', $jobSectorId)
            ->get();

        if ($inclusions->isEmpty()) {
            return RiskLevel::LOW;
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

        return $this->getHighestRisk($allRisks);
    }

    /**
     * Funzione 2: Calcola il rischio finale effettivo del lavoratore
     * Prende il rischio del settore e lo paragona al rischio specifico della mansione
     * Restituisce il rischio più alto tra i due
     */
    public function getEffectiveWorkerRisk(int $jobSectorId, int $jobTaskId): RiskLevel
    {
        $sectorRisk = $this->getSectorRiskLevel($jobSectorId);
        $taskRisk = $this->getTaskRiskInSector($jobSectorId, $jobTaskId);

        if (! $taskRisk) {
            return $sectorRisk;
        }

        return $sectorRisk->max($taskRisk);
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

    /**
     * Funzione 3: Risalita Gerarchica ed Ereditarietà Sezione
     * Trova il settore partendo dal codice ATECO a 6 cifre di un'azienda
     * L'algoritmo verifica la pivot partendo dal full_code e risalendo (category, class... fino a section)
     *
     * @param  string  $fullAtecoCode  Codice ATECO completo (es. "86.90.11")
     */
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

    /**
     * Ottimizzazione: Per trovare la sezione di appartenenza di un qualsiasi codice numerico,
     * basta leggere direttamente la colonna section della riga di quel codice
     */
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
