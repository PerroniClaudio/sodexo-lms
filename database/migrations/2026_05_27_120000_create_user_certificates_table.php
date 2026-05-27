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
        Schema::create('user_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internal_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
            $table->index(['user_id', 'expires_at']);
            $table->index('name');
        });

        Schema::create('requirement_user_certificate', function (Blueprint $table) {
            $table->foreignId('user_certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_based_requirement_id')->constrained('risk_based_requirements')->cascadeOnDelete();

            $table->primary(['user_certificate_id', 'risk_based_requirement_id'], 'requirement_user_certificate_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirement_user_certificate');
        Schema::dropIfExists('user_certificates');
    }
};
