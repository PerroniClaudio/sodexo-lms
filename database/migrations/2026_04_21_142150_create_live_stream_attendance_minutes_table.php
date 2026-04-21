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
        Schema::create('live_stream_attendance_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_session_id')->constrained('live_stream_sessions')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('minute_at');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('heartbeat_count')->default(1);
            $table->timestamps();

            $table->unique(['live_stream_session_id', 'user_id', 'minute_at']);
            $table->index(['module_id', 'user_id', 'minute_at']);
            $table->index(['live_stream_session_id', 'minute_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_attendance_minutes');
    }
};
