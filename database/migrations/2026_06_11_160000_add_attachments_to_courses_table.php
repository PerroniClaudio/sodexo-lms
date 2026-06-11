<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('cover_image_path')->nullable()->after('description');
            $table->string('poster_pdf_path')->nullable()->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['cover_image_path', 'poster_pdf_path']);
        });
    }
};
