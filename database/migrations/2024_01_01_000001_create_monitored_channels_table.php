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
        Schema::create('monitored_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('youtube_channel_id');
            $table->string('channel_name');
            $table->string('channel_url')->nullable();
            $table->string('channel_thumbnail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint: one user cannot add the same YouTube channel twice
            $table->unique(['user_id', 'youtube_channel_id']);

            // Index for faster lookups
            $table->index('youtube_channel_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitored_channels');
    }
};
