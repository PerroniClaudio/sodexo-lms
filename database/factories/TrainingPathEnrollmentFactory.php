<?php

namespace Database\Factories;

use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPathEnrollment>
 */
class TrainingPathEnrollmentFactory extends Factory
{
    protected $model = TrainingPathEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'training_path_id' => TrainingPath::factory(),
            'assigned_at' => now(),
        ];
    }
}
