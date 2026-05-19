<?php

namespace Database\Factories;

use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClassTeacher>
 */
class CourseClassTeacherFactory extends Factory
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
            'user_id' => User::factory()->asTeacher(),
            'assigned_at' => now(),
        ];
    }
}
