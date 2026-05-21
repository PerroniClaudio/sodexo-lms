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
        $tables = ['job_sectors', 'job_categories', 'job_levels', 'job_titles', 'job_roles'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['job_sectors', 'job_categories', 'job_levels', 'job_titles', 'job_roles'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('code')->unique()->after('name');
            });
        }
    }
};
