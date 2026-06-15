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
        if (! Schema::hasColumn('video_exercise_materials', 'type')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->string('type')->default('file')->after('uploaded_by');
            });
        }

        if (! Schema::hasColumn('video_exercise_materials', 'title')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->string('title')->default('Asset')->after('type');
            });
        }

        if (Schema::hasColumn('video_exercise_materials', 'disk')) {
            // SQLite cannot alter nullability in-place without doctrine/dbal; keep legacy columns nullable by usage.
        }

        if (! Schema::hasColumn('video_exercise_materials', 'youtube_url')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->string('youtube_url')->nullable()->after('size_bytes');
            });
        }

        if (! Schema::hasColumn('video_exercise_materials', 'content_html')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->text('content_html')->nullable()->after('youtube_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('video_exercise_materials', 'content_html')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->dropColumn('content_html');
            });
        }

        if (Schema::hasColumn('video_exercise_materials', 'youtube_url')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->dropColumn('youtube_url');
            });
        }

        if (Schema::hasColumn('video_exercise_materials', 'title')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }

        if (Schema::hasColumn('video_exercise_materials', 'type')) {
            Schema::table('video_exercise_materials', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }
    }
};
