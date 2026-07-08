<?php

namespace Database\Factories;

use App\Models\CompanyDivision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDivision>
 */
class CompanyDivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'vat_number' => fake()->unique()->numerify('###########'),
            'logo_path' => null,
        ];
    }
}
