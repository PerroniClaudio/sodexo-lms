<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserCertificate;
use App\Models\UserCertificateFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCertificateFile>
 */
class UserCertificateFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_certificate_id' => UserCertificate::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 's3',
            'path' => 'users/'.fake()->numberBetween(1, 999).'/certificates/file/'.fake()->uuid().'.pdf',
            'original_name' => fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
