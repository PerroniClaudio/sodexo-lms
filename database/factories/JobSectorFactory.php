<?php

namespace Database\Factories;

use App\Models\JobSector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSector>
 */
class JobSectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Ristorazione', 'Meccanica', 'Edilizia', 'Sanità', 'Logistica', 'Pulizie e Servizi']),
            'code' => strtoupper(fake()->unique()->lexify('SEC???')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
