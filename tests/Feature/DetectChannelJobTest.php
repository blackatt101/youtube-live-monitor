<?php

namespace Tests\Feature;

use App\Contracts\Services\LiveDetectionResult;
use App\Jobs\DetectChannelJob;
use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectChannelJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_detects_live_channel(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::live(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                videoId: 'abc123xyz',
                title: 'Test Stream',
                thumbnail: 'https://example.com/thumb.jpg',
                viewerCount: 100,
                detectionMethod: 'test'
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        // Verify stream was created
        $this->assertDatabaseHas('live_streams', [
            'monitored_channel_id' => $channel->id,
            'youtube_video_id' => 'abc123xyz',
            'title' => 'Test Stream',
            'status' => 'live',
        ]);

        // Verify channel status
        $channel->refresh();
        $this->assertTrue($channel->is_live);
    }

    public function test_job_detects_offline_channel(): void
    {
        // Create channel that was previously live
        $channel = MonitoredChannel::factory()->create(['is_live' => true]);
        $oldStream = LiveStream::factory()->create([
            'monitored_channel_id' => $channel->id,
            'status' => 'live',
        ]);

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::offline(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                detectionMethod: 'none'
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        // Verify old stream was ended
        $oldStream->refresh();
        $this->assertEquals('ended', $oldStream->status);
        $this->assertNotNull($oldStream->ended_at);

        // Verify channel status
        $channel->refresh();
        $this->assertFalse($channel->is_live);
    }

    public function test_job_ignores_error_for_live_channel(): void
    {
        // Create channel that is currently live
        $channel = MonitoredChannel::factory()->create(['is_live' => true]);
        $stream = LiveStream::factory()->create([
            'monitored_channel_id' => $channel->id,
            'status' => 'live',
            'youtube_video_id' => 'existing123',
        ]);

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::error(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                error: 'Network timeout',
                errorCode: 'TIMEOUT'
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        // The original stream should still exist
        $this->assertDatabaseHas('live_streams', [
            'id' => $stream->id,
            'status' => 'live',
        ]);
    }

    public function test_job_creates_new_stream_for_different_video(): void
    {
        $channel = MonitoredChannel::factory()->create();
        $oldStream = LiveStream::factory()->create([
            'monitored_channel_id' => $channel->id,
            'status' => 'live',
            'youtube_video_id' => 'oldVideo123',
        ]);

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::live(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                videoId: 'newVideo456',
                title: 'New Stream',
                detectionMethod: 'test'
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        // Old stream should be ended
        $oldStream->refresh();
        $this->assertEquals('ended', $oldStream->status);

        // New stream should be created
        $this->assertDatabaseHas('live_streams', [
            'monitored_channel_id' => $channel->id,
            'youtube_video_id' => 'newVideo456',
            'title' => 'New Stream',
            'status' => 'live',
        ]);

        // Should have exactly one active stream
        $this->assertEquals(1, LiveStream::where('monitored_channel_id', $channel->id)->where('status', 'live')->count());
    }

    public function test_job_updates_viewer_count_for_same_stream(): void
    {
        $channel = MonitoredChannel::factory()->create();
        $stream = LiveStream::factory()->create([
            'monitored_channel_id' => $channel->id,
            'status' => 'live',
            'youtube_video_id' => 'sameVideo',
            'viewer_count' => 100,
        ]);

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::live(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                videoId: 'sameVideo',
                title: 'Same Stream',
                viewerCount: 500,
                detectionMethod: 'test'
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        // Should have updated viewer count
        $stream->refresh();
        $this->assertEquals(500, $stream->viewer_count);

        // Should still have only one stream
        $this->assertEquals(1, LiveStream::where('monitored_channel_id', $channel->id)->where('status', 'live')->count());
    }

    public function test_job_prevents_duplicate_streams(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->twice() // Called twice
            ->andReturn(LiveDetectionResult::live(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                videoId: 'sameVideo',
                title: 'Stream',
                detectionMethod: 'test'
            ));

        // First detection
        $job1 = new DetectChannelJob($channel);
        $job1->handle($detector);

        // Second detection
        $job2 = new DetectChannelJob($channel);
        $job2->handle($detector);

        // Should still have only one stream
        $this->assertEquals(1, LiveStream::where('monitored_channel_id', $channel->id)->where('status', 'live')->count());
    }

    public function test_job_updates_last_checked_at(): void
    {
        $channel = MonitoredChannel::factory()->create(['last_checked_at' => null]);

        $detector = $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $detector->shouldReceive('detect')
            ->once()
            ->andReturn(LiveDetectionResult::offline(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
            ));

        $job = new DetectChannelJob($channel);
        $job->handle($detector);

        $channel->refresh();
        $this->assertNotNull($channel->last_checked_at);
    }
}
