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
        Schema::create('scorm_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_user_id')->nullable()->constrained('course_user')->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('scorm_package_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->unique();
            $table->string('sco_identifier');
            $table->string('status')->index();
            $table->json('runtime_snapshot')->nullable();
            $table->unsignedInteger('recorded_session_seconds')->default(0);
            $table->string('last_error_code')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scorm_package_id', 'status']);
            $table->index(['course_user_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_sessions');
    }
};
