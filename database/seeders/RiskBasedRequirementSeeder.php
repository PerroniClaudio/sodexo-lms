<?php

namespace Database\Seeders;

use App\Enums\RiskLevel;
use App\Models\RiskBasedRequirement;
use Illuminate\Database\Seeder;

class RiskBasedRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            [
                'name' => 'Formazione Generale',
                'description' => 'Obbligatoria per tutti i lavoratori, validità illimitata.',
                'risk_levels' => [RiskLevel::LOW, RiskLevel::MEDIUM, RiskLevel::HIGH],
                'is_limited_validity' => false,
                'validity_months' => null,
                'reset_formation_years' => null,
            ],
            [
                'name' => 'Formazione Specifica Rischio Basso',
                'description' => 'Obbligatoria per lavoratori in ambienti a basso rischio (es. uffici, attività amministrative). Primo corso di 4 ore, aggiornamento di 6 ore ogni 5 anni.',
                'risk_levels' => [RiskLevel::LOW],
                'is_limited_validity' => true,
                'validity_months' => 60,
                'reset_formation_years' => 10,
            ],
            [
                'name' => 'Formazione Specifica Rischio Medio',
                'description' => 'Obbligatoria per lavoratori in ambienti a rischio medio (es. mense, cucine professionali, asili nido con presenza fino a 100 persone). Primo corso di 8 ore, aggiornamento di 6 ore ogni 5 anni.',
                'risk_levels' => [RiskLevel::MEDIUM],
                'is_limited_validity' => true,
                'validity_months' => 60,
                'reset_formation_years' => 10,
            ],
            [
                'name' => 'Formazione Specifica Rischio Alto',
                'description' => 'Obbligatoria per lavoratori in ambienti ad alto rischio (es. asili nido e scuole con oltre 100 persone presenti). Primo corso di 12 ore, aggiornamento di 6 ore ogni 5 anni.',
                'risk_levels' => [RiskLevel::HIGH],
                'is_limited_validity' => true,
                'validity_months' => 60,
                'reset_formation_years' => 10,
            ],
        ];

        foreach ($requirements as $requirement) {
            RiskBasedRequirement::query()->updateOrCreate(
                ['name' => $requirement['name']],
                $requirement,
            );
        }
    }
}
