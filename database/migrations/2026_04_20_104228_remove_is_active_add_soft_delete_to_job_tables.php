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
        $tables = ['job_categories', 'job_levels', 'job_roles', 'job_sectors', 'job_titles'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Aggiungi soft delete
                $table->softDeletes();

                // Rimuovi l'indice di is_active prima di eliminare la colonna
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['job_categories', 'job_levels', 'job_roles', 'job_sectors', 'job_titles'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Rimuovi soft delete
                $table->dropSoftDeletes();

                // Ripristina is_active
                $table->boolean('is_active')->default(true)->after('description');
            });

            // Ricrea l'indice per is_active in una chiamata separata
            Schema::table($table, function (Blueprint $table) {
                $table->index('is_active');
            });
        }
    }
};
