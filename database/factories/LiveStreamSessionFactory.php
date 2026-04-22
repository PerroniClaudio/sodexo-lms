<?php

namespace Database\Factories;

use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamSession>
 */
class LiveStreamSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'teacher_user_id' => User::factory(),
            'started_by_user_id' => null,
            'regia_user_id' => null,
            'twilio_room_sid' => 'RM'.fake()->regexify('[A-Za-z0-9]{32}'),
            'twilio_room_name' => sprintf('live-module-%s-%s', fake()->numberBetween(1, 999), fake()->uuid()),
            'mux_playback_id' => null,
            'mux_broadcast_status' => null,
            'mux_broadcast_started_at' => null,
            'mux_broadcast_ended_at' => null,
            'status' => LiveStreamSession::STATUS_LIVE,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
