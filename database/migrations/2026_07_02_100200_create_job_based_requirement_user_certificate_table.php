<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_based_requirement_user_certificate', function (Blueprint $table) {
            $table->foreignId('user_certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_based_requirement_id')->constrained()->cascadeOnDelete();

            $table->primary(
                ['user_certificate_id', 'job_based_requirement_id'],
                'job_based_requirement_user_certificate_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_based_requirement_user_certificate');
    }
};
