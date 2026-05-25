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
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            // Drop the old index on is_active first
            $table->dropIndex(['is_active']);
        });

        Schema::table('risk_based_requirements', function (Blueprint $table) {
            // Remove is_active column
            $table->dropColumn('is_active');
            
            // Add is_limited_validity boolean
            $table->boolean('is_limited_validity')->default(false)->after('description');
            
            // Add soft deletes
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            // Add back is_active
            $table->boolean('is_active')->default(true)->after('validity_months');
            $table->index('is_active');
            
            // Remove is_limited_validity
            $table->dropColumn('is_limited_validity');
            
            // Remove soft deletes
            $table->dropSoftDeletes();
        });
    }
};
