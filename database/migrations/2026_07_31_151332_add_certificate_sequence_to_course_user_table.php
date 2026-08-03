<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'course_user_certificate_sequence_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('course_user', 'certificate_sequence')) {
            Schema::table('course_user', function (Blueprint $table): void {
                $table->unsignedInteger('certificate_sequence')->nullable();
            });
        }

        if (! Schema::hasColumn('course_user', 'certificate_sequence_year')) {
            Schema::table('course_user', function (Blueprint $table): void {
                $table->unsignedSmallInteger('certificate_sequence_year')->nullable();
            });
        }

        if (! Schema::hasIndex('course_user', ['certificate_sequence_year', 'certificate_sequence'], 'unique')) {
            Schema::table('course_user', function (Blueprint $table): void {
                $table->unique(['certificate_sequence_year', 'certificate_sequence'], self::UNIQUE_INDEX);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('course_user', ['certificate_sequence_year', 'certificate_sequence'], 'unique')) {
            Schema::table('course_user', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('course_user', 'certificate_sequence') ? 'certificate_sequence' : null,
            Schema::hasColumn('course_user', 'certificate_sequence_year') ? 'certificate_sequence_year' : null,
        ]));

        if ($columns !== []) {
            Schema::table('course_user', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
