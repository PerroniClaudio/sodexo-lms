<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\LanguageLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        $requiredLanguageLevelId = LanguageLevel::query()->ordered()->value('id')
            ?? LanguageLevel::factory()->create([
                'name' => 'a1',
                'sort_order' => 1,
                'is_default' => true,
            ])->getKey();

        return [
            'title' => fake()->sentence(4),
            'code' => 'CRS-'.fake()->unique()->numerify('########'),
            'description' => fake()->text(),
            'cover_image_path' => null,
            'poster_pdf_path' => null,
            'teaching_material' => fake()->optional()->paragraph(),
            'max_participants' => fake()->optional()->numberBetween(5, 100),
            'participant_presence_verification' => null,
            'internal_notes' => fake()->optional()->paragraph(),
            'training_objective' => fake()->optional()->paragraph(),
            'knowledge' => fake()->optional()->paragraph(),
            'skills' => fake()->optional()->paragraph(),
            'competences' => fake()->optional()->paragraph(),
            'regulatory_reference' => fake()->optional()->paragraph(),
            'course_start_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'course_end_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 months'),
            'access_closure_date' => fake()->optional()->dateTimeBetween('+2 months', '+3 months'),
            'course_duration_hours' => fake()->optional()->numberBetween(1, 80),
            'interaction_duration_minutes' => fake()->optional()->numberBetween(1, 480),
            'program_schedule' => null,
            'type' => fake()->randomElement(Course::availableTypes()),
            'event_type' => null,
            'year' => now()->year,
            'expiry_date' => now()->copy()->endOfYear(),
            'status' => 'draft',
            'required_language_level_id' => $requiredLanguageLevelId,
            'is_language_verification_course' => false,
            'grants_language_level_id' => null,
            'is_financed' => false,
            'funding_entity_id' => null,
            'edition' => 1,
            'original_course_id' => null,
            'has_satisfaction_survey' => false,
            'satisfaction_survey_required_for_certificate' => false,
            'hasMany' => '1',
            'visible_to_all' => true,
        ];
    }

    public function res(): static
    {
        return $this->state(fn (): array => [
            'type' => 'res',
        ]);
    }

    public function async(): static
    {
        return $this->state(fn (): array => [
            'type' => 'async',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
        ]);
    }

    public function currentYear(): static
    {
        return $this->state(fn (): array => [
            'year' => now()->year,
            'expiry_date' => now()->copy()->endOfYear(),
        ]);
    }
}
