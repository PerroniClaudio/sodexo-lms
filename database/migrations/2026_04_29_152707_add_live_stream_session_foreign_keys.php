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
        foreach ($this->missingForeignKeys() as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->foreign($column)->references('id')->on('live_stream_sessions')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tablesWithForeignKeys() as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });
        }
    }

    /**
     * @return array<string, string>
     */
    private function expectedReferences(): array
    {
        return [
            'live_stream_hand_raises' => 'live_stream_session_id',
            'live_stream_participants' => 'live_stream_session_id',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function missingForeignKeys(): array
    {
        return array_filter(
            $this->expectedReferences(),
            fn (string $column, string $tableName): bool => Schema::hasTable($tableName)
                && Schema::hasTable('live_stream_sessions')
                && ! $this->hasLiveStreamSessionForeignKey($tableName, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, string>
     */
    private function tablesWithForeignKeys(): array
    {
        return array_filter(
            $this->expectedReferences(),
            fn (string $column, string $tableName): bool => Schema::hasTable($tableName)
                && $this->hasLiveStreamSessionForeignKey($tableName, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function hasLiveStreamSessionForeignKey(string $tableName, string $column): bool
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['columns'][0] ?? null) !== $column) {
                continue;
            }

            if (($foreignKey['foreign_table'] ?? null) === 'live_stream_sessions') {
                return true;
            }
        }

        return false;
    }
};
