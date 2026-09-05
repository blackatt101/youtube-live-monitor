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
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_channel_id')->constrained()->onDelete('cascade');
            $table->string('youtube_video_id');
            $table->string('title');
            $table->string('thumbnail')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('viewer_count')->nullable();
            $table->enum('status', ['live', 'ended'])->default('live');
            $table->timestamps();

            // Indexes for faster queries
            $table->index('status');
            $table->index('started_at');
            $table->index(['monitored_channel_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
