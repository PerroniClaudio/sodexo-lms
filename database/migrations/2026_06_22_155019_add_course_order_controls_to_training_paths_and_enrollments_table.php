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
        Schema::table('training_paths', function (Blueprint $table) {
            $table->boolean('enforce_course_order')->default(true)->after('visible_to_all');
        });

        Schema::table('training_path_user', function (Blueprint $table) {
            $table->foreignId('current_course_id')
                ->nullable()
                ->after('training_path_id')
                ->constrained('courses')
                ->nullOnDelete();

            $table->index(['training_path_id', 'current_course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_path_user', function (Blueprint $table) {
            $table->dropIndex(['training_path_id', 'current_course_id']);
            $table->dropConstrainedForeignId('current_course_id');
        });

        Schema::table('training_paths', function (Blueprint $table) {
            $table->dropColumn('enforce_course_order');
        });
    }
};
