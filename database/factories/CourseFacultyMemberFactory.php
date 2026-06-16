<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseFacultyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseFacultyMember>
 */
class CourseFacultyMemberFactory extends Factory
{
    protected $model = CourseFacultyMember::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
            'role' => fake()->randomElement(CourseFacultyMember::roles()),
            'affiliation' => fake()->company(),
            'has_compensation' => false,
            'compensation_amount' => null,
        ];
    }
}
