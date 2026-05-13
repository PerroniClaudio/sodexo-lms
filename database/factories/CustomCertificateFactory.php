<?php

namespace Database\Factories;

use App\Models\CustomCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomCertificate>
 */
class CustomCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(CustomCertificate::availableTypes()),
            'name' => fake()->sentence(3),
            'storage_disk' => 'local',
            'template_path' => 'custom-certificates/'.Str::uuid().'.docx',
            'original_filename' => fake()->word().'.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'is_active' => true,
            'course_ids' => null,
            'replaced_by_id' => null,
            'activated_at' => now(),
            'archived_at' => null,
        ];
    }
}
