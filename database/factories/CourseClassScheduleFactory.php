<?php

namespace Database\Factories;

use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClassSchedule>
 */
class CourseClassScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeek()->setTime(9, 0);

        return [
            'course_class_id' => CourseClass::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ];
    }

    public function forCourseClass(CourseClass $courseClass): static
    {
        return $this->state(fn (): array => [
            'course_class_id' => $courseClass->getKey(),
        ]);
    }
}
