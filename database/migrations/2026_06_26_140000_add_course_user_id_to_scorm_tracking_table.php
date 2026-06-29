<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scorm_tracking', function (Blueprint $table): void {
            $table->foreignId('course_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('course_user')
                ->nullOnDelete();

            $table->index(
                ['course_user_id', 'scorm_package_id', 'sco_identifier'],
                'scorm_tracking_course_user_package_sco_idx'
            );
            $table->index(
                ['course_user_id', 'scorm_package_id', 'element'],
                'scorm_tracking_course_user_package_element_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('scorm_tracking', function (Blueprint $table): void {
            $table->dropIndex('scorm_tracking_course_user_package_sco_idx');
            $table->dropIndex('scorm_tracking_course_user_package_element_idx');
            $table->dropConstrainedForeignId('course_user_id');
        });
    }
};
