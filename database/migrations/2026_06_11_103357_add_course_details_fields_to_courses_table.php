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
        Schema::table('courses', function (Blueprint $table): void {
            $table->text('teaching_material')->nullable()->after('description');
            $table->integer('max_participants')->nullable()->after('teaching_material');
            $table->text('internal_notes')->nullable()->after('max_participants');
            $table->text('training_objective')->nullable()->after('internal_notes');
            $table->text('knowledge')->nullable()->after('training_objective');
            $table->text('skills')->nullable()->after('knowledge');
            $table->text('competences')->nullable()->after('skills');
            $table->text('regulatory_reference')->nullable()->after('competences');
            $table->date('course_start_date')->nullable()->after('regulatory_reference');
            $table->date('course_end_date')->nullable()->after('course_start_date');
            $table->date('access_closure_date')->nullable()->after('course_end_date');
            $table->integer('course_duration_hours')->nullable()->after('access_closure_date');
            $table->integer('interaction_duration_minutes')->nullable()->after('course_duration_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'teaching_material',
                'max_participants',
                'internal_notes',
                'training_objective',
                'knowledge',
                'skills',
                'competences',
                'regulatory_reference',
                'course_start_date',
                'course_end_date',
                'access_closure_date',
                'course_duration_hours',
                'interaction_duration_minutes',
            ]);
        });
    }
};
