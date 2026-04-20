<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEnrollment>
 */
class CourseEnrollmentFactory extends Factory
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
            'course_id' => Course::factory(),
            'current_module_id' => null,
            'status' => CourseEnrollment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'last_accessed_at' => null,
            'completion_percentage' => 0,
        ];
    }
}
