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
        Schema::create('audit_archives', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start')->unique();
            $table->date('period_end');
            $table->string('disk');
            $table->string('path');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('event_count');
            $table->timestamp('archived_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_archives');
    }
};
