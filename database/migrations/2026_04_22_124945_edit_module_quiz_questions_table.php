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
        Schema::table('module_quiz_questions', function (Blueprint $table) {
            $table->foreignId('correct_answer_id')
                ->nullable()
                ->constrained('module_quiz_answers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['correct_answer_id']);
            $table->dropColumn('correct_answer_id');
        });
    }
};
