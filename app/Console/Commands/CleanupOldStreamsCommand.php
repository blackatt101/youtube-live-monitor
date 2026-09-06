<?php

namespace App\Console\Commands;

use App\Models\LiveStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldStreamsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:cleanup
                            {--hours=12 : Hours to keep ended streams}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old stream data (keeps channel subscriptions, deletes old stream records)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $cutoff = now()->subHours($hours);

        $this->info("Cleaning up streams older than {$hours} hours...");
        $this->info("Cutoff time: {$cutoff->toDateTimeString()}");

        // Find ended streams older than cutoff
        $query = LiveStream::where('status', LiveStream::STATUS_ENDED)
            ->where('ended_at', '<', $cutoff);

        $oldStreams = $query->get();
        $count = $oldStreams->count();

        if ($count === 0) {
            $this->info('No old streams to clean up.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} stream(s) to clean up.");

        // Show details
        $this->table(
            ['ID', 'Channel', 'Video ID', 'Started', 'Ended', 'Duration'],
            $oldStreams->map(function ($stream) {
                $channel = $stream->monitoredChannel;
                $duration = $stream->started_at && $stream->ended_at
                    ? $stream->ended_at->diffForHumans($stream->started_at, true)
                    : 'N/A';

                return [
                    $stream->id,
                    $channel?->channel_name ?? 'Unknown',
                    $stream->youtube_video_id,
                    $stream->started_at?->toDateTimeString() ?? 'N/A',
                    $stream->ended_at?->toDateTimeString() ?? 'N/A',
                    $duration,
                ];
            })
        );

        if ($dryRun) {
            $this->warn('Dry run - no data was deleted.');
            return self::SUCCESS;
        }

        // Delete old streams
        $deleted = $query->delete();

        $this->info("Deleted {$deleted} stream record(s).");

        // Also clean up any orphaned live streams that are impossibly old
        // (shouldn't happen, but just in case detection failed to mark them as ended)
        $orphanedHours = $hours * 2; // Consider streams "orphaned" if live for more than 2x the cleanup period
        $orphanedCutoff = now()->subHours($orphanedHours);

        $orphanedQuery = LiveStream::where('status', LiveStream::STATUS_LIVE)
            ->where('started_at', '<', $orphanedCutoff);

        $orphanedCount = $orphanedQuery->count();

        if ($orphanedCount > 0) {
            $this->warn("Found {$orphanedCount} orphaned 'live' stream(s) - marking as ended.");
            $orphanedQuery->update([
                'status' => LiveStream::STATUS_ENDED,
                'ended_at' => now(),
            ]);
        }

        // Log the cleanup
        Log::channel('youtube')->info('Stream cleanup completed', [
            'deleted_count' => $deleted,
            'orphaned_marked_ended' => $orphanedCount,
            'cutoff_hours' => $hours,
            'cutoff_time' => $cutoff->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
