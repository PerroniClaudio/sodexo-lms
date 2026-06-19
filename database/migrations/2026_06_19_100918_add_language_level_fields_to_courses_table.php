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
            $table->foreignId('required_language_level_id')->default(1)->after('status');
            $table->boolean('is_language_verification_course')->default(false)->after('required_language_level_id');
            $table->foreignId('grants_language_level_id')->nullable()->after('is_language_verification_course');

            $table->index('required_language_level_id');
            $table->index(['is_language_verification_course', 'grants_language_level_id'], 'courses_language_verification_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_language_verification_idx');
            $table->dropIndex(['required_language_level_id']);
            $table->dropColumn([
                'required_language_level_id',
                'is_language_verification_course',
                'grants_language_level_id',
            ]);
        });
    }
};
