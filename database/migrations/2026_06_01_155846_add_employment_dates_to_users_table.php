<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('employment_start_date')->nullable()->after('birth_date');
            $table->date('employment_end_date')->nullable()->after('employment_start_date');
            $table->index(['employment_start_date', 'employment_end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['employment_start_date', 'employment_end_date']);
            $table->dropColumn(['employment_start_date', 'employment_end_date']);
        });
    }
};
