<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_report_requests', function (Blueprint $table) {
            $table->string('report_type')->default('video')->after('scope_type');

            $table->index(['report_type', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::table('video_report_requests', function (Blueprint $table) {
            $table->dropIndex(['report_type', 'scope_type']);
            $table->dropColumn('report_type');
        });
    }
};
