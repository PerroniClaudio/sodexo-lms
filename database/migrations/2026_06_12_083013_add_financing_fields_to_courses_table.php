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
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_financed')->default(false)->after('has_satisfaction_survey');
            $table->foreignId('funding_entity_id')
                ->nullable()
                ->after('is_financed')
                ->constrained('funding_entities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['funding_entity_id']);
            $table->dropColumn(['is_financed', 'funding_entity_id']);
        });
    }
};
