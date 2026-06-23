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
        Schema::table('course_user', function (Blueprint $table): void {
            $table->boolean('direct_origin')->default(true)->after('origin_course_id');
            $table->boolean('pathway_origin')->default(false)->after('direct_origin');
            $table->index(['course_id', 'direct_origin']);
            $table->index(['course_id', 'pathway_origin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table): void {
            $table->dropIndex(['course_id', 'direct_origin']);
            $table->dropIndex(['course_id', 'pathway_origin']);
            $table->dropColumn(['direct_origin', 'pathway_origin']);
        });
    }
};
