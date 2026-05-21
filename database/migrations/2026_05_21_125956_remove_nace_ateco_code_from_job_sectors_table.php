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
            $table->dropForeign(['nace_ateco_code']);
            $table->dropIndex(['nace_ateco_code']);
            $table->dropColumn('nace_ateco_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_sectors', function (Blueprint $table) {
            $table->string('nace_ateco_code')->nullable()->after('description');
            $table->foreign('nace_ateco_code')
                ->references('code')
                ->on('nace_ateco')
                ->onDelete('set null');
            $table->index('nace_ateco_code');
        });
    }
};
