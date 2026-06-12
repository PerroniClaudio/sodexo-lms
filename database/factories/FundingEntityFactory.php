<?php

namespace Database\Factories;

use App\Models\FundingEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundingEntity>
 */
class FundingEntityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'vat_number' => fake()->optional()->numerify('###########'),
            'fiscal_code' => fake()->optional()->bothify('??##??##??###??#'),
            'pec' => fake()->optional()->safeEmail(),
        ];
    }
}
