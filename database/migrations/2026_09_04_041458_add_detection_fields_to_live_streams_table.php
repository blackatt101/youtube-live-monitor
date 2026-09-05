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
        Schema::table('live_streams', function (Blueprint $table) {
            // Add detection tracking fields
            $table->timestamp('detected_at')->nullable()->after('viewer_count');
            $table->string('detection_method', 50)->nullable()->after('detected_at');
        });

        Schema::table('monitored_channels', function (Blueprint $table) {
            // Add is_live tracking
            $table->boolean('is_live')->default(false)->after('is_active');

            // Add tracking fields
            $table->timestamp('last_checked_at')->nullable()->after('is_live');
            $table->timestamp('last_live_at')->nullable()->after('last_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['detected_at', 'detection_method']);
        });

        Schema::table('monitored_channels', function (Blueprint $table) {
            $table->dropColumn(['is_live', 'last_checked_at', 'last_live_at']);
        });
    }
};
