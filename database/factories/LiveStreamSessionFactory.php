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
            'twilio_room_sid' => 'RM'.fake()->regexify('[A-Za-z0-9]{32}'),
            'twilio_room_name' => sprintf('live-module-%s-%s', fake()->numberBetween(1, 999), fake()->uuid()),
            'status' => LiveStreamSession::STATUS_LIVE,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
