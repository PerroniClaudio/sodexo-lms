<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\TrainingPath;
use App\Models\TrainingPathCourseApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPathCourseApproval>
 */
class TrainingPathCourseApprovalFactory extends Factory
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
            'training_path_id' => TrainingPath::factory(),
            'course_id' => Course::factory(),
            'status' => TrainingPathCourseApproval::STATUS_APPROVED,
            'reasons' => [fake()->sentence()],
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ];
    }
}
