<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importazioni', function (Blueprint $table) {
            $table->string('original_file_name')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('importazioni', function (Blueprint $table) {
            $table->dropColumn('original_file_name');
        });
    }
};
