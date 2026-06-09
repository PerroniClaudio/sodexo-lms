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
        if (! Schema::hasColumn('module_quiz_document_uploads', 'document_type_id')) {
            Schema::table('module_quiz_document_uploads', function (Blueprint $table): void {
                $table->foreignId('document_type_id')
                    ->nullable()
                    ->after('uploaded_by')
                    ->constrained('document_types')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('module_quiz_document_uploads', 'document_type_id')) {
            Schema::table('module_quiz_document_uploads', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('document_type_id');
            });
        }
    }
};
