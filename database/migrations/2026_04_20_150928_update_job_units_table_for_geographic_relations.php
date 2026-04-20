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
        // 1. Rimuovi l'indice solo se esiste (o commentalo se sai che non c'è)
        Schema::table('job_units', function (Blueprint $table) {
            // Se non sei sicuro del nome, commenta questa riga:
            $table->dropIndex(['country', 'region', 'province']);
        });

        // 2. Aggiungi le nuove colonne e rimuovi le vecchie
        Schema::table('job_units', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('description');
            $table->unsignedBigInteger('region_id')->nullable()->after('country_id');
            $table->unsignedBigInteger('province_id')->nullable()->after('region_id');
            $table->unsignedBigInteger('city_id')->nullable()->after('province_id');

            $table->dropColumn(['country', 'region', 'province', 'city']);
        });

        // 3. Crea i nuovi indici
        Schema::table('job_units', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('world_countries')->onDelete('set null');
            $table->foreign('region_id')->references('id')->on('world_divisions')->onDelete('set null');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('city_id')->references('id')->on('world_cities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_units', function (Blueprint $table) {
            // Remove foreign keys and indexes
            $table->dropForeign(['country_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['province_id']);
            $table->dropForeign(['city_id']);

            $table->dropIndex(['country_id']);
            $table->dropIndex(['region_id']);
            $table->dropIndex(['province_id']);
            $table->dropIndex(['city_id']);

            // Remove new columns
            $table->dropColumn(['country_id', 'region_id', 'province_id', 'city_id']);

            // Add back old string columns
            $table->string('country', 2)->nullable()->after('description');
            $table->string('region')->nullable()->after('country');
            $table->string('province', 2)->nullable()->after('region');
            $table->string('city')->nullable()->after('province');

            // Recreate the original index
            $table->index(['country', 'region', 'province']);
        });
    }
};
