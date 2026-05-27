<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCertificate>
 */
class UserCertificateFactory extends Factory
{
    protected $model = UserCertificate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-2 years', '-1 day');
        $expiresAt = fake()->boolean(70)
            ? fake()->dateTimeBetween($issuedAt, '+2 years')
            : null;

        return [
            'user_id' => User::factory(),
            'internal_course_id' => null,
            'name' => fake()->randomElement([
                'Formazione generale lavoratori',
                'Aggiornamento antincendio',
                'Primo soccorso aziendale',
                'Lavori in quota',
            ]),
            'description' => fake()->optional()->sentence(),
            'file_path' => fake()->optional()->filePath(),
            'is_internal' => false,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
    }

    public function internal(?Course $course = null): static
    {
        return $this->state(fn (): array => [
            'internal_course_id' => $course?->getKey() ?? Course::factory(),
            'is_internal' => true,
        ]);
    }

    public function withoutExpiration(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => null,
        ]);
    }
}
