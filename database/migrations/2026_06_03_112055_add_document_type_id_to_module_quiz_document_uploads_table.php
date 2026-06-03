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
        Schema::table('module_quiz_document_uploads', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                ->nullable()
                ->after('uploaded_by')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_quiz_document_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_type_id');
        });
    }
};
