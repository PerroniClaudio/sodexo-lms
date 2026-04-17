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
        Schema::create('job_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('country', 2);
            $table->string('region')->nullable();
            $table->string('province', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country', 'region', 'province']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_units');
    }
};
