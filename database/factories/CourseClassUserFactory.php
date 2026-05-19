<?php

namespace Database\Factories;

use App\Models\CourseClass;
use App\Models\CourseClassUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClassUser>
 */
class CourseClassUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_class_id' => CourseClass::factory(),
            'user_id' => User::factory()->asUser(),
            'assigned_at' => now(),
        ];
    }
}
