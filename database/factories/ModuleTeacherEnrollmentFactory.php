<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleTeacherEnrollment>
 */
class ModuleTeacherEnrollmentFactory extends Factory
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
