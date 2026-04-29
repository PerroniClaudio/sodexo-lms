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
        Schema::create('live_stream_poll_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_poll_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('answer_index');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['live_stream_poll_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_poll_responses');
    }
};
