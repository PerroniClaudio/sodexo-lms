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
        if (Schema::hasTable('course_class_users')) {
            return;
        }

        Schema::create('course_class_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_class_id', 'deleted_at']);
            $table->index(['user_id', 'deleted_at']);
            $table->index(['course_class_id', 'user_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_class_users');
    }
};
