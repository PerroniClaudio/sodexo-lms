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
        Schema::create('nace_ateco', function (Blueprint $table) {
            $table->char('section', 1)->nullable();
            $table->string('code')->primary();
            $table->unsignedInteger('order');
            $table->unsignedTinyInteger('hierarchy');
            $table->string('title_it');
            $table->string('title_en');
            $table->string('risk')->nullable();
            $table->timestamps();

            $table->index('section');
            $table->index('hierarchy');
            $table->index('risk');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nace_ateco');
    }
};
