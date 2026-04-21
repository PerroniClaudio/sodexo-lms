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
        Schema::create('scorm_tracking', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scorm_package_id')->constrained()->cascadeOnDelete();
            $table->string('sco_identifier');
            $table->string('element');
            $table->longText('value')->nullable();
            $table->timestamp('tracked_at')->index();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scorm_package_id', 'sco_identifier'], 'scorm_tracking_user_package_sco_idx');
            $table->index(['user_id', 'scorm_package_id', 'element'], 'scorm_tracking_user_package_element_idx');
            $table->index(['scorm_package_id', 'sco_identifier', 'element'], 'scorm_tracking_package_sco_element_idx');
            $table->index(['session_id', 'scorm_package_id'], 'scorm_tracking_session_package_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_tracking');
    }
};
