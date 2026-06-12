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
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'assigned_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
