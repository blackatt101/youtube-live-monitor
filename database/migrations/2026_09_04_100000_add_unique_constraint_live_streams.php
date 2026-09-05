<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds unique constraint to prevent duplicate live_stream records
     * for the same video_id on the same channel.
     *
     * This is a safety measure - the application code already handles
     * this case, but the database constraint ensures data integrity.
     */
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            // Add unique constraint for active streams
            // Only one LIVE stream per channel per video_id
            $table->unique(
                ['monitored_channel_id', 'youtube_video_id', 'status'],
                'unique_live_stream_per_video'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropUnique('unique_live_stream_per_video');
        });
    }
};
