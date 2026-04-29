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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Titolo descrittivo del video');
            $table->text('description')->nullable()->comment('Descrizione opzionale del video');
            $table->string('mux_asset_id')->unique()->comment('ID asset Mux per il video');
            $table->string('mux_playback_id')->unique()->comment('ID playback Mux per la generazione di signed playback URL');
            $table->string('mux_upload_id')->nullable()->unique()->comment('ID upload Mux per tracking stato upload');
            $table->string('mux_video_status')->default('pending')->comment('Stato video su Mux: pending, uploading, ready, failed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
