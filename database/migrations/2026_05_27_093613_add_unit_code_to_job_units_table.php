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
        Schema::table('job_units', function (Blueprint $table) {
            $table->string('unit_code', 50)->nullable()->after('name');
            $table->index('unit_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_units', function (Blueprint $table) {
            $table->dropIndex(['unit_code']);
            $table->dropColumn('unit_code');
        });
    }
};
