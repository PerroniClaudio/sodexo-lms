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
        Schema::table('modules', function (Blueprint $table) {
            $table->string('permitted_submission')
                ->nullable()
                ->default('online')
                ->change();
        });

        DB::table('modules')
            ->where('type', '!=', 'learning_quiz')
            ->update(['permitted_submission' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('modules')
            ->whereNull('permitted_submission')
            ->update(['permitted_submission' => 'online']);

        Schema::table('modules', function (Blueprint $table) {
            $table->string('permitted_submission')
                ->nullable(false)
                ->default('online')
                ->change();
        });
    }
};
