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
        Schema::create('course_risk_based_requirement', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_based_requirement_id')->constrained()->cascadeOnDelete();
            $table->string('course_validity_type')
                ->default('first_achievement');
            $table->timestamps();

            $table->primary(
                ['course_id', 'risk_based_requirement_id'],
                'course_risk_based_requirement_primary'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_risk_based_requirement');
    }
};
