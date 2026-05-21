<?php

namespace App\Services;

use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\NaceAteco;
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
        $jobSector = JobSector::findOrFail($jobSectorId);

        // Recupera tutte le inclusioni ATECO per questo settore
        $inclusions = DB::table('job_sector_nace_ateco')
            ->where('job_sector_id', $jobSectorId)
            ->get();

        if ($inclusions->isEmpty()) {
            // Se non ci sono inclusioni, restituisce rischio basso di default
            return RiskLevel::LOW;
        }

        $allRisks = collect();

        foreach ($inclusions as $inclusion) {
            $inclusionType = InclusionType::from($inclusion->inclusion_type);
            $naceAtecoCode = $inclusion->nace_ateco_code;

            // Recupera tutti i codici figli a seconda del tipo di inclusione
            $codes = $this->expandInclusionCodes($naceAtecoCode, $inclusionType);

            // Recupera i livelli di rischio per tutti questi codici
            $risks = NaceAteco::whereIn('code', $codes)
                ->whereNotNull('risk')
                ->get()
                ->pluck('risk')
                ->filter(); // Remove nulls

            $allRisks = $allRisks->merge($risks);
        }

        // Restituisce il rischio più alto trovato
        return $this->getHighestRisk($allRisks);
    }

    /**
     * Funzione 2: Calcola il rischio finale effettivo del lavoratore
     * Prende il rischio del settore e lo paragona al rischio specifico della mansione
     * Restituisce il rischio più alto tra i due
     */
    public function getEffectiveWorkerRisk(int $jobSectorId, int $jobTitleId): RiskLevel
    {
        // Recupera il rischio del settore
        $sectorRisk = $this->getSectorRiskLevel($jobSectorId);

        // Recupera il rischio specifico della mansione nel settore
        $titleRisk = $this->getTitleRiskInSector($jobSectorId, $jobTitleId);

        // Se non esiste una mappatura specifica, usa solo il rischio del settore
        if (! $titleRisk) {
            return $sectorRisk;
        }

        // Restituisce il rischio più alto tra settore e mansione
        return $sectorRisk->max($titleRisk);
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
        // Verifica che il codice esista
        $naceAteco = NaceAteco::find($fullAtecoCode);
        if (! $naceAteco) {
            return null;
        }

        // Costruisce la catena gerarchica: dal più specifico al più generico
        $hierarchyChain = $this->buildHierarchyChain($fullAtecoCode, $naceAteco->section);

        // Cerca nella pivot in ordine di specificità
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
     *
     * @return NaceAteco|null L'anagrafica completa della macro-sezione
     */
    public function getSectionForCode(string $atecoCode): ?NaceAteco
    {
        $code = NaceAteco::find($atecoCode);

        if (! $code || ! $code->section) {
            return null;
        }

        // Recupera l'anagrafica completa della sezione (la lettera)
        return NaceAteco::where('code', $code->section)->first();
    }

    /**
     * Espande i codici figli in base al tipo di inclusione
     */
    protected function expandInclusionCodes(string $code, InclusionType $inclusionType): array
    {
        if ($inclusionType === InclusionType::FULL_CODE) {
            // Se è full_code, restituisce solo quel codice specifico
            return [$code];
        }

        $baseCode = NaceAteco::find($code);
        if (! $baseCode) {
            return [$code];
        }

        $hierarchyLevel = $inclusionType->toHierarchyLevel();

        // Trova tutti i codici figli che appartengono a questa gerarchia
        // Per esempio, se inclusionType è SECTION e code è "Q", trova tutti i codici con section = "Q"
        // Se è DIVISION e code è "86", trova tutti i codici che iniziano con "86"

        if ($inclusionType === InclusionType::SECTION) {
            // Trova tutti i codici della sezione
            return NaceAteco::where('section', $code)->pluck('code')->toArray();
        }

        // Per gli altri livelli, trova tutti i figli tramite prefisso del codice
        // e verifica che abbiano hierarchy >= di quello specificato
        return NaceAteco::where('code', 'LIKE', $code.'%')
            ->where('hierarchy', '>=', $hierarchyLevel->value)
            ->pluck('code')
            ->toArray();
    }

    /**
     * Costruisce la catena gerarchica per la risalita
     *
     * @return array Array associativo [code => InclusionType]
     */
    protected function buildHierarchyChain(string $fullCode, ?string $section): array
    {
        $chain = [];

        // 1. Full code (es. "86.90.11") - SUBCATEGORY/FULL_CODE
        $chain[$fullCode] = InclusionType::FULL_CODE;

        // 2. Category (es. "86.90") - prime 5 cifre senza l'ultima parte
        if (preg_match('/^(\d{2}\.\d{2})\./', $fullCode, $matches)) {
            $chain[$matches[1]] = InclusionType::CATEGORY;

            // 3. Class/NACE (es. "86") - prime 2 cifre
            if (preg_match('/^(\d{2})\./', $matches[1], $classMatches)) {
                $chain[$classMatches[1]] = InclusionType::NACE_CLASS;
            }
        } elseif (preg_match('/^(\d{2})\./', $fullCode, $matches)) {
            // Se il codice è già a 4 cifre (es. "86.90")
            $chain[$matches[1]] = InclusionType::NACE_CLASS;
        }

        // Nota: GROUP e DIVISION richiederebbero una logica più complessa
        // basata sulla struttura effettiva dei dati NACE/ATECO
        // Per ora ci concentriamo sui livelli più comuni

        // 4. Section (es. "Q")
        if ($section) {
            $chain[$section] = InclusionType::SECTION;
        }

        return $chain;
    }

    /**
     * Recupera il rischio specifico della mansione nel settore
     */
    protected function getTitleRiskInSector(int $jobSectorId, int $jobTitleId): ?RiskLevel
    {
        $pivot = DB::table('job_sector_job_title')
            ->where('job_sector_id', $jobSectorId)
            ->where('job_title_id', $jobTitleId)
            ->first();

        if (! $pivot || ! $pivot->title_risk_level) {
            return null;
        }

        return RiskLevel::tryFrom($pivot->title_risk_level);
    }

    /**
     * Trova il rischio più alto in una collezione di RiskLevel
     */
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
