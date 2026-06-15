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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('country_id')->nullable()->constrained('world_countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('world_divisions')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('world_cities')->nullOnDelete();
            $table->string('postal_code', 20)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('job_unit_id')->nullable()->after('funding_entity_id')->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->after('job_unit_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropConstrainedForeignId('job_unit_id');
        });

        Schema::dropIfExists('venues');
    }
};
