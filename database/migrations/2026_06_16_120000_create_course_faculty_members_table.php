<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_faculty_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('surname');
            $table->string('fiscal_code');
            $table->string('role');
            $table->string('affiliation')->nullable();
            $table->boolean('has_compensation')->default(false);
            $table->decimal('compensation_amount', 10, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['course_id', 'role', 'deleted_at']);
            $table->index(['course_id', 'user_id', 'role', 'deleted_at']);
            $table->index(['course_id', 'surname', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_faculty_members');
    }
};
