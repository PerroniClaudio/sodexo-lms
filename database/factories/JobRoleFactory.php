<?php

namespace Database\Factories;

use App\Models\JobRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRole>
 */
class JobRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Lavoratore', 'Preposto', 'Dirigente', 'RSPP', 'RLS']),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
