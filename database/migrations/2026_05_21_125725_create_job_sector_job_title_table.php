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
        Schema::create('job_sector_job_title', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_sector_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_title_id')->constrained()->onDelete('cascade');
            $table->string('title_risk_level');
            $table->timestamps();

            // Unique constraint to prevent duplicate mappings
            $table->unique(['job_sector_id', 'job_title_id']);

            // Indexes
            $table->index('title_risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_sector_job_title');
    }
};
