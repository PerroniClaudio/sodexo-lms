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
        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            // Aggiungi source_type per distinguere tra 'upload' e 'online'
            $table->string('source_type')->default('upload')->after('module_id');
            
            // Aggiungi course_enrollment_id per tracciare l'enrollment (per quiz online)
            $table->foreignId('course_enrollment_id')->nullable()->after('user_id')->constrained('course_user')->cascadeOnDelete();
            
            // Rendi nullable uploaded_by e path (non necessari per quiz online)
            $table->foreignId('uploaded_by')->nullable()->change();
            $table->string('path')->nullable()->change();
            
            // Aggiungi campi per gestire il quiz in corso
            $table->timestamp('started_at')->nullable()->after('processed_at');
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            
            // Aggiungi indice per query veloci
            $table->index(['course_enrollment_id', 'module_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->dropIndex(['course_enrollment_id', 'module_id', 'created_at']);
            $table->dropColumn(['source_type', 'course_enrollment_id', 'started_at', 'submitted_at']);
            $table->foreignId('uploaded_by')->nullable(false)->change();
            $table->string('path')->nullable(false)->change();
        });
    }
};
