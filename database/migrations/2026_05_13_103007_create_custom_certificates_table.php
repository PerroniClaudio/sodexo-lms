<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('name');
            $table->string('storage_disk')->default('local');
            $table->string('template_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->boolean('is_active')->default(true)->index();
            $table->json('course_ids')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('custom_certificates')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_certificates');
    }
};
