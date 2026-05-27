<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('job_sector_job_title');

        Schema::create('job_role_job_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_sector_id')->constrained()->cascadeOnDelete();
            $table->string('role_risk_level');
            $table->timestamps();

            $table->unique(['job_role_id', 'job_sector_id']);
            $table->index('role_risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_role_job_sector');

        Schema::create('job_sector_job_title', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_title_id')->constrained()->cascadeOnDelete();
            $table->string('title_risk_level');
            $table->timestamps();

            $table->unique(['job_sector_id', 'job_title_id']);
            $table->index('title_risk_level');
        });
    }
};
