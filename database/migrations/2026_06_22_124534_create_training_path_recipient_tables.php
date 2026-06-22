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
        Schema::create('training_path_job_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_path_id', 'job_role_id']);
        });

        Schema::create('training_path_job_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_path_id', 'job_task_id']);
        });

        Schema::create('training_path_job_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_path_id', 'job_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_path_job_unit');
        Schema::dropIfExists('training_path_job_task');
        Schema::dropIfExists('training_path_job_role');
    }
};
