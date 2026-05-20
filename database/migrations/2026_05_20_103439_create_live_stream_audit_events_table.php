<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_session_id')->constrained('live_stream_sessions')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('live_stream_participant_id')->nullable()->constrained('live_stream_participants')->nullOnDelete();
            $table->foreignId('live_stream_hand_raise_id')->nullable()->constrained('live_stream_hand_raises')->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('app_role')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['live_stream_session_id', 'occurred_at'], 'ls_audit_session_occurred_idx');
            $table->index(['module_id', 'event_type', 'occurred_at'], 'ls_audit_module_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_audit_events');
    }
};
