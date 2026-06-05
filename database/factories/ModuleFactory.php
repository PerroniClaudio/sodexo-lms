<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    public function definition(): array
    {
        $appointment = now()->copy()->addDay()->startOfDay()->addHours(9);

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'type' => Module::TYPE_VIDEO,
            'order' => 1,
            'is_live_teacher' => false,
            'mux_live_stream_id' => null,
            'mux_playback_id' => null,
            'mux_stream_key' => null,
            'mux_ingest_url' => null,
            'appointment_date' => $appointment,
            'appointment_start_time' => $appointment,
            'appointment_end_time' => $appointment->copy()->addHour(),
            'status' => 'draft',
            'passing_score' => null,
            'max_score' => null,
            'max_attempts' => null,
            'permitted_submission' => null,
            'belongsTo' => '1',
            'video_id' => null,
        ];
    }
}
