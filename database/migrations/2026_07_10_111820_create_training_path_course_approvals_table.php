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
        Schema::create('training_path_course_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importazione_id')->nullable()->constrained('importazioni')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('training_path_id')->constrained();
            $table->foreignId('course_id')->constrained();
            $table->string('status')->default('pending');
            $table->json('reasons');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['importazione_id', 'user_id', 'training_path_id', 'course_id'],
                'training_path_course_approvals_import_unique'
            );
            $table->index(
                ['user_id', 'training_path_id', 'status', 'course_id'],
                'training_path_course_approvals_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_path_course_approvals');
    }
};
