<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_task_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'ends_at']);
            $table->index(['job_task_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_task_user');
    }
};
