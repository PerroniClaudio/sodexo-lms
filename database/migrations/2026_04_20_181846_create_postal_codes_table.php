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
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->comment('World city ID');
            $table->string('postal_code', 10)->comment('Postal code / CAP / ZIP code');
            $table->timestamps();

            // Indexes
            $table->index('city_id');
            $table->index('postal_code');
            $table->unique(['city_id', 'postal_code'], 'unique_city_postal_code');

            // Foreign key to world_cities
            $table->foreign('city_id')->references('id')->on('world_cities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
