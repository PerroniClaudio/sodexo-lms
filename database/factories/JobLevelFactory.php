<?php

namespace Database\Factories;

use App\Models\JobLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLevel>
 */
class JobLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['1° Livello', '2° Livello', '3° Livello', 'B1', 'B2', 'C1']),
            'code' => strtoupper(fake()->unique()->lexify('LVL???')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
