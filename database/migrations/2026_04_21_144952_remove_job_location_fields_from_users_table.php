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
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['job_country', 'job_region', 'job_province']);
            $table->dropColumn('job_country');
            $table->dropColumn('job_region');
            $table->dropColumn('job_province');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_country')->nullable();
            $table->string('job_region')->nullable();
            $table->string('job_province')->nullable();
        });
    }
};
