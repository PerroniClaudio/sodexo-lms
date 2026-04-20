<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('live_stream_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_session_id')->constrained('live_stream_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('app_role');
            $table->string('twilio_identity');
            $table->string('twilio_participant_sid')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('audio_enabled')->default(false);
            $table->boolean('video_enabled')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['live_stream_session_id', 'user_id']);
            $table->unique(['live_stream_session_id', 'twilio_identity']);
            $table->index(['live_stream_session_id', 'app_role', 'is_hidden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_participants');
    }
};
