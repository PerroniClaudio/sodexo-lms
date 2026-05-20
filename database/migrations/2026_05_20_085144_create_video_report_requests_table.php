<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_report_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('scope_type');
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('job_dimension')->nullable();
            $table->unsignedBigInteger('job_dimension_id')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('output_disk')->default('s3');
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'course_id']);
            $table->index(['job_dimension', 'job_dimension_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_report_requests');
    }
};
