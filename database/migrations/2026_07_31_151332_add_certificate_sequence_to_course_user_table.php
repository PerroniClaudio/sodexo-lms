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
        Schema::table('course_user', function (Blueprint $table) {
            $table->unsignedInteger('certificate_sequence')->nullable();
            $table->unsignedSmallInteger('certificate_sequence_year')->nullable();
            $table->unique(['certificate_sequence_year', 'certificate_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropUnique(['certificate_sequence_year', 'certificate_sequence']);
            $table->dropColumn(['certificate_sequence', 'certificate_sequence_year']);
        });
    }
};
