<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_conversion_jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->string('input_disk');
            $table->string('input_path');
            $table->string('output_disk')->nullable();
            $table->string('output_path')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('worker_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('locked_at');
            $table->index('created_at');
            $table->index('worker_id');
            $table->index(['status', 'locked_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_conversion_jobs');
    }
};
