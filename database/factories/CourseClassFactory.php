<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClass>
 */
class CourseClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory()->state([
                'type' => Module::TYPE_RESIDENTIAL,
                'belongsTo' => Course::factory()->res(),
            ]),
            'name' => fake()->words(3, true),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CourseClass $courseClass): void {
            if ($courseClass->schedules()->exists()) {
                return;
            }

            CourseClassSchedule::factory()
                ->forCourseClass($courseClass)
                ->create();
        });
    }

    public function forModule(Module $module): static
    {
        return $this->state(fn (): array => [
            'module_id' => $module->getKey(),
        ]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'module_id' => Module::factory()->state([
                'type' => Module::TYPE_RESIDENTIAL,
                'belongsTo' => (string) $course->getKey(),
            ]),
        ]);
    }
}
