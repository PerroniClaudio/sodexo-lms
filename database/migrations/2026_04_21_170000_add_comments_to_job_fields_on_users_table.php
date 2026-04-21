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
            $table->foreignId('job_title_id')->nullable()->comment('Mansione')->change();
            $table->foreignId('job_role_id')->nullable()->comment('Ruolo')->change();
            $table->foreignId('job_sector_id')->nullable()->comment('Settore')->change();
            $table->foreignId('job_unit_id')->nullable()->comment('Unità lavorativa')->change();
            $table->foreignId('job_category_id')->nullable()->comment('Categoria')->change();
            $table->foreignId('job_level_id')->nullable()->comment('Livello di inquadramento')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->comment(null)->change();
            $table->foreignId('job_role_id')->nullable()->comment(null)->change();
            $table->foreignId('job_sector_id')->nullable()->comment(null)->change();
            $table->foreignId('job_unit_id')->nullable()->comment(null)->change();
            $table->foreignId('job_category_id')->nullable()->comment(null)->change();
            $table->foreignId('job_level_id')->nullable()->comment(null)->change();
        });
    }
};
