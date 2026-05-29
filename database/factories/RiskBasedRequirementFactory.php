<?php

namespace Database\Factories;

use App\Enums\RiskLevel;
use App\Models\RiskBasedRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskBasedRequirement>
 */
class RiskBasedRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requirements = [
            'Corso di formazione sulla sicurezza generale',
            'Abilitazione per lavori in quota',
            'Certificazione uso DPI di III categoria',
            'Corso antincendio rischio medio',
            'Corso primo soccorso',
            'Abilitazione uso carrello elevatore',
            'Corso HACCP livello base',
            'Formazione specifica per rischio elettrico',
        ];

        $hasLimitedValidity = fake()->boolean(70);

        return [
            'name' => fake()->randomElement($requirements),
            'description' => fake()->optional()->sentence(),
            'is_limited_validity' => $hasLimitedValidity,
            'risk_levels' => fake()->randomElements(
                [RiskLevel::LOW, RiskLevel::MEDIUM, RiskLevel::HIGH],
                fake()->numberBetween(1, 3)
            ),
            'validity_months' => $hasLimitedValidity ? fake()->randomElement([12, 24, 36, 48, 60]) : null,
            'reset_formation_years' => fake()->optional(0.4)->numberBetween(1, 10),
        ];
    }

    /**
     * Create a requirement with unlimited validity
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_limited_validity' => false,
            'validity_months' => null,
        ]);
    }

    /**
     * Create a requirement with limited validity
     */
    public function limited(int $months): static
    {
        return $this->state(fn (array $attributes) => [
            'is_limited_validity' => true,
            'validity_months' => $months,
        ]);
    }

    public function withFormationReset(int $years): static
    {
        return $this->state(fn (array $attributes) => [
            'reset_formation_years' => $years,
        ]);
    }

    /**
     * Create a requirement for a specific risk level
     */
    public function forRiskLevel(RiskLevel $riskLevel): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_levels' => [$riskLevel],
        ]);
    }

    /**
     * Create a requirement for high risk only
     */
    public function highRiskOnly(): static
    {
        return $this->forRiskLevel(RiskLevel::HIGH);
    }
}
