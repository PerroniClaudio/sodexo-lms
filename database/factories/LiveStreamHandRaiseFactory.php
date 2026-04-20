<?php

namespace Database\Factories;

use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamHandRaise>
 */
class LiveStreamHandRaiseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_stream_session_id' => LiveStreamSession::factory(),
            'user_id' => User::factory(),
            'status' => LiveStreamHandRaise::STATUS_PENDING,
            'requested_at' => now(),
            'approved_at' => null,
            'resolved_at' => null,
            'approved_by' => null,
        ];
    }
}
