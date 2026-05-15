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
            $table->foreignId('document_upload_id')
                ->nullable()
                ->after('module_id')
                ->constrained('module_quiz_document_uploads')
                ->cascadeOnDelete();
        });

        // Rimuove i campi che ora sono nella tabella document_uploads (solo per upload)
        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'disk',
                'path',
                'provider',
                'processed_at',
            ]);
        });

        // Rende uploaded_by nullable poiché le submission online non hanno uploaded_by
        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable(false)->change();
        });

        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->string('disk')->default('local')->after('uploaded_by');
            $table->string('path')->after('disk');
            $table->string('provider')->nullable()->after('total_score');
            $table->timestamp('processed_at')->nullable()->after('provider_payload');
        });

        Schema::table('module_quiz_submissions', function (Blueprint $table) {
            $table->dropForeign(['document_upload_id']);
            $table->dropColumn('document_upload_id');
        });
    }
};
