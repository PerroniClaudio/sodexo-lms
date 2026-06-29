<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_tracking_archive', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_tracking_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('course_user_id')->nullable()->index();
            $table->unsignedBigInteger('module_id')->nullable()->index();
            $table->unsignedBigInteger('scorm_package_id')->nullable()->index();
            $table->uuid('reset_batch_uuid')->index();
            $table->unsignedBigInteger('archived_by_user_id')->nullable()->index();
            $table->string('sco_identifier');
            $table->string('element');
            $table->longText('value')->nullable();
            $table->timestamp('tracked_at')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->timestamp('archived_at')->index();
            $table->timestamps();

            $table->index(
                ['course_user_id', 'module_id', 'reset_batch_uuid'],
                'scorm_tracking_archive_enrollment_module_batch_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_tracking_archive');
    }
};
