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
        Schema::create('funding_entities', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('vat_number', 32)->nullable();
            $table->string('fiscal_code', 32)->nullable();
            $table->string('pec')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_entities');
    }
};
