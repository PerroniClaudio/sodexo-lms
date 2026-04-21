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
            // Indirizzo di residenza (facoltativo)
            $table->dropColumn(['nation', 'region', 'province', 'city']); // Rimuovi vecchi campi geografici

            $table->unsignedBigInteger('home_city_id')->nullable()->before('address');
            $table->unsignedBigInteger('home_province_id')->nullable()->before('home_city_id');
            $table->unsignedBigInteger('home_region_id')->nullable()->before('home_province_id');
            $table->unsignedBigInteger('home_country_id')->nullable()->before('home_region_id');
        });

        // 3. Crea i nuovi indici
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('home_country_id')->references('id')->on('world_countries')->onDelete('set null');
            $table->foreign('home_region_id')->references('id')->on('world_divisions')->onDelete('set null');
            $table->foreign('home_province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('home_city_id')->references('id')->on('world_cities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rimuovi i nuovi campi geografici
            $table->dropForeign(['home_country_id']);
            $table->dropForeign(['home_region_id']);
            $table->dropForeign(['home_province_id']);
            $table->dropForeign(['home_city_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['home_country_id', 'home_region_id', 'home_province_id', 'home_city_id']);

            // Aggiungi di nuovo i vecchi campi geografici
            $table->string('nation', 2)->nullable()->after('postal_code');
            $table->string('region')->nullable()->after('nation');
            $table->string('province')->nullable()->after('region');
            $table->string('city')->nullable()->after('province');
        });
    }
};
