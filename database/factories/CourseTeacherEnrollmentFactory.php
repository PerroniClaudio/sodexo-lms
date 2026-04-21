<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTeacherEnrollment>
 */
class CourseTeacherEnrollmentFactory extends Factory
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
            'assigned_at' => now(),
        ];
    }
}
