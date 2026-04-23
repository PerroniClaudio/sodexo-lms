<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_quiz_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('status')->index();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('total_score')->nullable();
            $table->string('provider')->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['module_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_quiz_submissions');
    }
};
