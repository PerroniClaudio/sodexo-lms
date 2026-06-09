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
        Schema::table('course_user', function (Blueprint $table): void {
            $table->string('course_validity_type')->nullable()->after('completion_percentage');
            $table->boolean('is_integrative_enrollment')->default(false)->after('course_validity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table): void {
            $table->dropColumn(['course_validity_type', 'is_integrative_enrollment']);
        });
    }
};
