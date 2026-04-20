<?php

namespace Database\Factories;

use App\Models\JobUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobUnit>
 */
class JobUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Sede '.fake()->city(),
            'description' => fake()->optional()->sentence(),
            'country' => 'IT',
            'region' => fake()->state(),
            'province' => fake()->stateAbbr(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
        ];
    }
}
