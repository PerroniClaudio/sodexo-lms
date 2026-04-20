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
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->comment('Country ID from world_countries');
            $table->unsignedBigInteger('region_id')->nullable()->comment('Region ID from world_divisions');
            $table->string('code', 2)->comment('Official province code (e.g. MI, RM, NA)');
            $table->string('name')->comment('Province name');
            $table->timestamps();

            $table->unique(['country_id', 'code'], 'unique_province_code');
            $table->index(['country_id', 'region_id']);

            // Foreign key to world_countries
            $table->foreign('country_id')->references('id')->on('world_countries')->onDelete('cascade');
            // Foreign key to world_divisions (regions)
            $table->foreign('region_id')->references('id')->on('world_divisions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
