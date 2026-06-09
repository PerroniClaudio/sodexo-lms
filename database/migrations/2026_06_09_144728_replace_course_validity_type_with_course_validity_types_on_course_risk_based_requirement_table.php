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
        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->json('course_validity_types')
                ->nullable()
                ->after('course_validity_type');
        });

        DB::table('course_risk_based_requirement')
            ->select(['course_id', 'risk_based_requirement_id', 'course_validity_type'])
            ->orderBy('course_id')
            ->orderBy('risk_based_requirement_id')
            ->get()
            ->each(function (object $association): void {
                $courseValidityTypes = match ($association->course_validity_type) {
                    'both' => ['first_achievement', 'refresh'],
                    'integrative' => ['integrative'],
                    'refresh' => ['refresh'],
                    default => ['first_achievement'],
                };

                DB::table('course_risk_based_requirement')
                    ->where('course_id', $association->course_id)
                    ->where('risk_based_requirement_id', $association->risk_based_requirement_id)
                    ->update([
                        'course_validity_types' => json_encode($courseValidityTypes),
                    ]);
            });

        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->dropColumn('course_validity_type');
        });

        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->json('course_validity_types')
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->string('course_validity_type')
                ->nullable()
                ->after('risk_based_requirement_id');
        });

        DB::table('course_risk_based_requirement')
            ->select(['course_id', 'risk_based_requirement_id', 'course_validity_types'])
            ->orderBy('course_id')
            ->orderBy('risk_based_requirement_id')
            ->get()
            ->each(function (object $association): void {
                $courseValidityTypes = json_decode((string) $association->course_validity_types, true);
                $normalizedTypes = is_array($courseValidityTypes) ? array_values(array_unique($courseValidityTypes)) : [];
                $legacyValidityType = match (true) {
                    in_array('integrative', $normalizedTypes, true) && count($normalizedTypes) === 1 => 'integrative',
                    in_array('first_achievement', $normalizedTypes, true) && in_array('refresh', $normalizedTypes, true) && count($normalizedTypes) === 2 => 'both',
                    in_array('refresh', $normalizedTypes, true) && count($normalizedTypes) === 1 => 'refresh',
                    default => 'first_achievement',
                };

                DB::table('course_risk_based_requirement')
                    ->where('course_id', $association->course_id)
                    ->where('risk_based_requirement_id', $association->risk_based_requirement_id)
                    ->update([
                        'course_validity_type' => $legacyValidityType,
                    ]);
            });

        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->dropColumn('course_validity_types');
        });

        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->string('course_validity_type')
                ->nullable(false)
                ->default('first_achievement')
                ->change();
        });
    }
};
