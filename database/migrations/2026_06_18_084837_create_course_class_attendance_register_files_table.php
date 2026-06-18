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
        Schema::create('course_class_attendance_register_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id')->unique('cc_att_reg_files_course_class_unique');
            $table->foreign('course_class_id', 'cc_att_reg_files_course_class_fk')
                ->references('id')
                ->on('course_classes')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id');
            $table->foreign('uploaded_by_user_id', 'cc_att_reg_files_uploaded_by_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_class_attendance_register_files');
    }
};
