<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleTutorEnrollment>
 */
class ModuleTutorEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_id' => Module::factory(),
            'assigned_at' => now(),
        ];
    }
}
