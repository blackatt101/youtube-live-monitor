<?php

namespace App\Jobs;

use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Contracts\Services\LiveDetectionResult;
use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DetectChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MonitoredChannel $channel,
        public ?string $batchId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LiveDetectionProviderInterface $detector): void
    {
        $startTime = microtime(true);

        Log::channel('youtube')->info('Detecting channel', [
            'channel_id' => $this->channel->youtube_channel_id,
            'handle' => $this->channel->channel_name,
            'batch_id' => $this->batchId,
        ]);

        try {
            $result = $detector->detect($this->channel->channel_name);

            // Update the channel's last_checked timestamp
            $this->channel->update(['last_checked_at' => now()]);

            // Process the detection result
            $this->processResult($result);

            $duration = (microtime(true) - $startTime) * 1000;
            Log::channel('youtube')->info('Channel detection completed', [
                'channel_id' => $this->channel->youtube_channel_id,
                'status' => $result->status,
                'duration_ms' => round($duration, 2),
            ]);

        } catch (\Exception $e) {
            Log::channel('youtube')->error('Channel detection failed', [
                'channel_id' => $this->channel->youtube_channel_id,
                'error' => $e->getMessage(),
            ]);

            // Mark as error state (but don't overwrite LIVE status)
            $this->markError($e->getMessage());

            throw $e;
        }
    }

    /**
     * Process the detection result
     */
    private function processResult(LiveDetectionResult $result): void
    {
        match ($result->status) {
            LiveDetectionResult::STATUS_LIVE => $this->handleLive($result),
            LiveDetectionResult::STATUS_OFFLINE => $this->handleOffline($result),
            LiveDetectionResult::STATUS_BLOCKED, LiveDetectionResult::STATUS_UNKNOWN => $this->handleBlocked($result),
            LiveDetectionResult::STATUS_ERROR => $this->markError($result->error ?? 'Unknown error'),
            default => Log::warning('Unknown detection status', ['status' => $result->status]),
        };
    }

    /**
     * Handle live stream detection
     */
    private function handleLive(LiveDetectionResult $result): void
    {
        // Find existing active stream for this channel
        $existingStream = LiveStream::where('monitored_channel_id', $this->channel->id)
            ->where('status', LiveStream::STATUS_LIVE)
            ->where('youtube_video_id', $result->videoId)
            ->first();

	if ($existingStream) {
    		// Update existing stream with latest detection data
    		$existingStream->update([
        		'title' => $result->title ?: $existingStream->title,
        		'thumbnail' => $result->thumbnail ?: $existingStream->thumbnail,
        		'viewer_count' => $result->viewerCount,
       			'detected_at' => now(),
   		 ]);
        } else {
            // End any previous live streams for this channel
            LiveStream::where('monitored_channel_id', $this->channel->id)
                ->where('status', LiveStream::STATUS_LIVE)
                ->update([
                    'status' => LiveStream::STATUS_ENDED,
                    'ended_at' => now(),
                ]);

            // Create new stream record
            LiveStream::create([
                'monitored_channel_id' => $this->channel->id,
                'youtube_video_id' => $result->videoId,
                'title' => $result->title,
                'thumbnail' => $result->thumbnail,
                'started_at' => $result->startedAt ?? now(),
                'viewer_count' => $result->viewerCount,
                'status' => LiveStream::STATUS_LIVE,
                'detected_at' => now(),
            ]);
        }

        // Update channel status
        $this->channel->update([
            'is_live' => true,
            'last_live_at' => now(),
        ]);
    }

    /**
     * Handle offline detection
     */
    private function handleOffline(LiveDetectionResult $result): void
    {
        // End any active live streams
        LiveStream::where('monitored_channel_id', $this->channel->id)
            ->where('status', LiveStream::STATUS_LIVE)
            ->update([
                'status' => LiveStream::STATUS_ENDED,
                'ended_at' => now(),
            ]);

        // Update channel status
        $this->channel->update(['is_live' => false]);
    }

    /**
     * Handle blocked/unknown status
     */
    private function handleBlocked(LiveDetectionResult $result): void
    {
        // Don't change the channel's is_live status
        // Just log the blocked status
        Log::channel('youtube')->warning('Channel detection blocked', [
            'channel_id' => $this->channel->youtube_channel_id,
            'reason' => $result->error,
        ]);
    }

    /**
     * Mark channel as having an error
     */
    private function markError(string $error): void
    {
        // Don't overwrite LIVE status due to errors
        // Just update the last_checked timestamp
        Log::channel('youtube')->warning('Channel detection error (status preserved)', [
            'channel_id' => $this->channel->youtube_channel_id,
            'error' => $error,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('youtube')->error('DetectChannelJob failed', [
            'channel_id' => $this->channel->youtube_channel_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
