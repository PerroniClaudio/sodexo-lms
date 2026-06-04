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
        if (! Schema::hasColumn('user_certificates', 'file_path')) {
            return;
        }

        Schema::table('user_certificates', function (Blueprint $table): void {
            $table->dropColumn('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('user_certificates', 'file_path')) {
            return;
        }

        Schema::table('user_certificates', function (Blueprint $table): void {
            $table->string('file_path')->nullable()->after('description');
        });
    }
};
