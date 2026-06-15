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
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('visible_to_all')->default(true)->after('hasMany');
        });

        Schema::create('course_job_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'job_role_id']);
        });

        Schema::create('course_job_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'job_task_id']);
        });

        Schema::create('course_job_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'job_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_job_unit');
        Schema::dropIfExists('course_job_task');
        Schema::dropIfExists('course_job_role');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('visible_to_all');
        });
    }
};
