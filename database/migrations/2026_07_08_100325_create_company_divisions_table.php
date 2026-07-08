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
        Schema::create('company_divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vat_number')->nullable()->index();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_division_id')
                ->nullable()
                ->after('job_sector_id')
                ->constrained('company_divisions')
                ->nullOnDelete();
        });

        Schema::create('company_division_admin', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_division_id', 'user_id']);
        });

        Schema::create('company_division_course', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_division_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_division_course');
        Schema::dropIfExists('company_division_admin');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_division_id');
        });

        Schema::dropIfExists('company_divisions');
    }
};
