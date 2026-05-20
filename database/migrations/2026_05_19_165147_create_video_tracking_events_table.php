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
        Schema::create('video_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_progress_id')->constrained('module_user')->cascadeOnDelete();
            $table->foreignId('course_user_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('session_uuid');
            $table->uuid('event_uuid')->unique();
            $table->string('event_type');
            $table->unsignedInteger('position_second')->nullable();
            $table->unsignedInteger('max_second_client')->nullable();
            $table->unsignedInteger('delta_watched_seconds')->nullable();
            $table->unsignedInteger('from_second')->nullable();
            $table->unsignedInteger('to_second')->nullable();
            $table->boolean('player_ended')->default(false);
            $table->boolean('was_blocked')->default(false);
            $table->timestamp('occurred_at');
            $table->json('client_payload')->nullable();
            $table->timestamps();

            $table->index(['module_progress_id', 'occurred_at']);
            $table->index(['session_uuid', 'occurred_at']);
            $table->index(['module_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_tracking_events');
    }
};
