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
        Schema::create('live_stream_session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_role', 50)->default('teacher');
            $table->string('disk', 50)->default('s3');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->default('application/json');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('twilio_room_name')->nullable();
            $table->string('participant_identity')->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('stats_snapshot_count')->default(0);
            $table->unsignedInteger('max_participant_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('exported_at');
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['module_id', 'exported_at']);
            $table->index(['teacher_user_id', 'exported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_session_logs');
    }
};
