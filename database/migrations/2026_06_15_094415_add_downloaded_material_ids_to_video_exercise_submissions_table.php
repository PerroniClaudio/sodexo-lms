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
        Schema::table('video_exercise_submissions', function (Blueprint $table) {
            $table->json('downloaded_material_ids')->nullable()->after('elapsed_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_exercise_submissions', function (Blueprint $table) {
            $table->dropColumn('downloaded_material_ids');
        });
    }
};
