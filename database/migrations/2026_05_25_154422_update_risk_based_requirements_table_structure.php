<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'risk_based_requirements';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, ['is_active'])) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(['is_active']);
            });
        }

        if (Schema::hasColumn(self::TABLE, 'is_active')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'is_limited_validity')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->boolean('is_limited_validity')->default(false)->after('description');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'deleted_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'is_active')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('validity_months');
            });
        }

        if (! Schema::hasIndex(self::TABLE, ['is_active'])) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('is_active');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn(self::TABLE, 'is_limited_validity') ? 'is_limited_validity' : null,
            Schema::hasColumn(self::TABLE, 'deleted_at') ? 'deleted_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
