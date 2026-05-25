<?php

namespace Database\Seeders;

use App\Enums\RiskLevel;
use App\Models\RiskBasedRequirement;
use Illuminate\Database\Seeder;

class RiskBasedRequirementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requirements = [
            [
                'name' => 'Formazione Generale',
                'description' => 'Durata di 4 ore',
                'is_limited_validity' => false,
                'risk_levels' => [RiskLevel::LOW, RiskLevel::MEDIUM, RiskLevel::HIGH],
                'validity_months' => null,
            ],
            [
                'name' => 'Formazione Specifica Rischio Basso',
                'description' => 'Durata di 4 ore (iniziale) o 6 ore (aggiornamento)',
                'is_limited_validity' => true,
                'risk_levels' => [RiskLevel::LOW],
                'validity_months' => 60,
            ],
            [
                'name' => 'Formazione Specifica Rischio Medio',
                'description' => 'Durata di 8 ore (iniziale) o 6 ore (aggiornamento)',
                'is_limited_validity' => true,
                'risk_levels' => [RiskLevel::MEDIUM],
                'validity_months' => 60,
            ],
            [
                'name' => 'Formazione Specifica Rischio Alto',
                'description' => 'Durata di 12 ore (iniziale) o 6 ore (aggiornamento)',
                'is_limited_validity' => true,
                'risk_levels' => [RiskLevel::HIGH],
                'validity_months' => 60,
            ],
        ];

        foreach ($requirements as $requirement) {
            RiskBasedRequirement::create($requirement);
        }
    }
}
