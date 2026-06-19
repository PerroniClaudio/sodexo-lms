<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreign('required_language_level_id')
                ->references('id')
                ->on('language_levels')
                ->nullOnDelete();
            $table->foreign('grants_language_level_id')
                ->references('id')
                ->on('language_levels')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('declared_language_level_id')
                ->references('id')
                ->on('language_levels')
                ->nullOnDelete();
            $table->foreign('verified_language_level_id')
                ->references('id')
                ->on('language_levels')
                ->nullOnDelete();
        });

        Schema::table('course_user', function (Blueprint $table): void {
            $table->foreign('origin_course_id')
                ->references('id')
                ->on('courses')
                ->nullOnDelete();
        });

        if (DB::table('language_levels')->doesntExist()) {
            DB::table('language_levels')->insert([
                ['name' => 'a1', 'sort_order' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'a2', 'sort_order' => 2, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'b1', 'sort_order' => 3, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'b2', 'sort_order' => 4, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'c1', 'sort_order' => 5, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'c2', 'sort_order' => 6, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $defaultLanguageLevelId = DB::table('language_levels')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->value('id');

        if ($defaultLanguageLevelId !== null) {
            DB::table('courses')
                ->whereNull('required_language_level_id')
                ->update(['required_language_level_id' => $defaultLanguageLevelId]);
        }
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table): void {
            $table->dropForeign(['origin_course_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['declared_language_level_id']);
            $table->dropForeign(['verified_language_level_id']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['required_language_level_id']);
            $table->dropForeign(['grants_language_level_id']);
        });
    }
};
