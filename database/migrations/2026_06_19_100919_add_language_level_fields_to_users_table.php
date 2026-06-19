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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('declared_language_level_id')->nullable()->after('is_foreigner_or_immigrant');
            $table->foreignId('verified_language_level_id')->nullable()->after('declared_language_level_id');
            $table->boolean('needs_language_level_verification')->default(false)->after('verified_language_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'declared_language_level_id',
                'verified_language_level_id',
                'needs_language_level_verification',
            ]);
        });
    }
};
