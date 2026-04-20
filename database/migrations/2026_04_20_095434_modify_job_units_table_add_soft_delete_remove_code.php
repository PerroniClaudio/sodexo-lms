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
        Schema::table('job_units', function (Blueprint $table) {
            // Aggiungi soft delete
            $table->softDeletes();

            // Rimuovi il campo code e il suo constraint unique
            $table->dropUnique(['code']);
            $table->dropColumn('code');

            // Rimuovi l'indice di is_active prima di eliminare la colonna
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_units', function (Blueprint $table) {
            // Rimuovi soft delete
            $table->dropSoftDeletes();

            // Aggiungi prima il campo code come nullable
            $table->string('code')->nullable()->after('name');

            // Aggiungi is_active
            $table->boolean('is_active')->default(true)->after('postal_code');
        });

        // Popola i record esistenti con valori per il campo code
        DB::table('job_units')->get()->each(function ($unit) {
            DB::table('job_units')
                ->where('id', $unit->id)
                ->update(['code' => $unit->id.'-rollback']);
        });

        // Ora rendi il campo code non nullable e unique, e aggiungi l'indice per is_active
        Schema::table('job_units', function (Blueprint $table) {
            $table->string('code')->nullable(false)->unique()->change();
            $table->index('is_active');
        });
    }
};
