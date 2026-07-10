<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_based_requirement_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_based_requirement_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['user_id', 'job_based_requirement_id'], 'job_based_requirement_user_unique');
            $table->index(['user_id', 'is_active'], 'job_based_requirement_user_status_index');
            $table->index('valid_from');
            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_based_requirement_user');
    }
};
