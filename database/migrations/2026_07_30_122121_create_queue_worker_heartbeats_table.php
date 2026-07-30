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
        // ponytail: development-only telemetry; production does not need worker heartbeats.
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        Schema::create('queue_worker_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->timestamp('last_seen_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_worker_heartbeats');
    }
};
