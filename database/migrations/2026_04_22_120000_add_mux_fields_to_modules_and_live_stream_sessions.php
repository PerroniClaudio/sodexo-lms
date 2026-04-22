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
        Schema::table('modules', function (Blueprint $table): void {
            $table->string('mux_live_stream_id')->nullable()->after('is_live_teacher');
            $table->string('mux_playback_id')->nullable()->after('mux_live_stream_id');
            $table->string('mux_stream_key')->nullable()->after('mux_playback_id');
            $table->string('mux_ingest_url')->nullable()->after('mux_stream_key');

            $table->index('mux_live_stream_id');
        });

        Schema::table('live_stream_sessions', function (Blueprint $table): void {
            $table->foreignId('started_by_user_id')->nullable()->after('teacher_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('regia_user_id')->nullable()->after('started_by_user_id')->constrained('users')->nullOnDelete();
            $table->string('mux_playback_id')->nullable()->after('twilio_room_name');
            $table->string('mux_broadcast_status')->nullable()->after('mux_playback_id');
            $table->timestamp('mux_broadcast_started_at')->nullable()->after('mux_broadcast_status');
            $table->timestamp('mux_broadcast_ended_at')->nullable()->after('mux_broadcast_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_stream_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('started_by_user_id');
            $table->dropConstrainedForeignId('regia_user_id');
            $table->dropColumn([
                'mux_playback_id',
                'mux_broadcast_status',
                'mux_broadcast_started_at',
                'mux_broadcast_ended_at',
            ]);
        });

        Schema::table('modules', function (Blueprint $table): void {
            $table->dropIndex(['mux_live_stream_id']);
            $table->dropColumn([
                'mux_live_stream_id',
                'mux_playback_id',
                'mux_stream_key',
                'mux_ingest_url',
            ]);
        });
    }
};
