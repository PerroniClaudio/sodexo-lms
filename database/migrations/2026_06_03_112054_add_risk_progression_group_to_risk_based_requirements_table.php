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
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            $table->string('risk_progression_group')
                ->nullable()
                ->after('description');
            $table->index('risk_progression_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            $table->dropIndex(['risk_progression_group']);
            $table->dropColumn('risk_progression_group');
        });
    }
};
