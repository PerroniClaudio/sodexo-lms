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
        Schema::table('job_sectors', function (Blueprint $table) {
            $table->foreignId('employer_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('custom_certificates', function (Blueprint $table) {
            $table->foreignId('job_sector_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_sector_id');
        });

        Schema::table('job_sectors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employer_user_id');
        });
    }
};
