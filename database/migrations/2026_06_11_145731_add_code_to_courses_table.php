<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('code')->nullable()->after('title');
        });

        DB::table('courses')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $course): void {
                DB::table('courses')
                    ->where('id', $course->id)
                    ->update([
                        'code' => 'CRS-'.$course->id,
                    ]);
            });

        Schema::table('courses', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
