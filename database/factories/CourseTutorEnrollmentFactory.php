<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTutorEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTutorEnrollment>
 */
class CourseTutorEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'assigned_at' => now(),
        ];
    }
}
