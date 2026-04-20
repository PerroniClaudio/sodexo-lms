<?php

namespace Database\Factories;

use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleProgress>
 */
class ModuleProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_user_id' => CourseEnrollment::factory(),
            'module_id' => Module::factory(),
            'status' => ModuleProgress::STATUS_LOCKED,
            'started_at' => null,
            'completed_at' => null,
            'last_accessed_at' => null,
            'time_spent_seconds' => 0,
            'video_current_second' => null,
            'video_max_second' => null,
            'quiz_attempts' => 0,
            'quiz_score' => null,
            'quiz_total_score' => null,
            'passed_at' => null,
        ];
    }
}
