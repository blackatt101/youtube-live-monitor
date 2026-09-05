<?php

namespace App\Console\Commands;

use App\Contracts\Services\LiveDetectionResult;
use App\Jobs\DetectChannelJob;
use App\Models\MonitoredChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Load test command for scalability validation
 *
 * Simulates 100 monitored channels without making real YouTube requests
 */
class LoadTestCommand extends Command
{
    protected $signature = 'test:load
                            {--channels=100 : Number of channels to simulate}
                            {--live-channels=0 : Number of channels that should be live}
                            {--error-rate=0 : Error rate (0-1)}
                            {--sync : Run jobs synchronously}
                            {--scenario=mixed : Scenario: mixed, offline, live, error}
                            {--clear-jobs : Clear existing jobs before test}';

    protected $description = 'Run scalability load test with mocked YouTube responses';

    private array $metrics = [];

    public function handle(): int
    {
        $channelCount = (int) $this->option('channels');
        $liveChannels = (int) $this->option('live-channels');
        $errorRate = (float) $this->option('error-rate');
        $sync = $this->option('sync');
        $scenario = $this->option('scenario');

        $this->info("=== YouTube Live Monitor - Scalability Load Test ===");
        $this->info("Channels: {$channelCount}");
        $this->info("Scenario: {$scenario}");
        $this->info("Error Rate: {$errorRate}");
        $this->info("Sync Mode: " . ($sync ? 'Yes' : 'No'));
        $this->newLine();

        // Initialize metrics
        $this->metrics = [
            'start_time' => microtime(true),
            'dispatch_start' => 0,
            'dispatch_end' => 0,
            'dispatched_count' => 0,
            'processing_start' => 0,
            'processing_end' => 0,
            'processed_count' => 0,
            'job_durations' => [],
            'errors' => [],
            'live_detected' => 0,
            'offline_detected' => 0,
            'blocked_detected' => 0,
        ];

        // Clear existing jobs if requested
        if ($this->option('clear-jobs')) {
            $this->clearQueue();
        }

        // Create test channels
        $this->createTestChannels($channelCount, $liveChannels, $scenario);

        // Bind mock detector
        $this->bindMockDetector($scenario, $errorRate);

        // Run dispatch test
        $this->runDispatchTest($channelCount, $sync);

        // If async, run queue processing
        if (!$sync) {
            $this->runQueueProcessing();
        }

        // Calculate and display metrics
        $this->displayMetrics();

        return self::SUCCESS;
    }

    private function clearQueue(): void
    {
        $this->info('Clearing queue...');
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();
    }

    private function createTestChannels(int $count, int $liveCount, string $scenario): void
    {
        $this->info("Creating {$count} test channels...");

        $startTime = microtime(true);

        // Delete existing test channels
        MonitoredChannel::where('channel_name', 'like', 'test_channel_%')
            ->orWhere('channel_name', 'like', 'load_test_%')
            ->delete();

        // Determine how many should be live
        $actualLiveCount = match ($scenario) {
            'live' => $count,
            'mixed' => $liveCount > 0 ? $liveCount : (int) ($count * 0.1),
            'error' => 0,
            default => 0,
        };

        $channels = [];
        for ($i = 0; $i < $count; $i++) {
            $isLive = $i < $actualLiveCount;
            $channels[] = [
                'youtube_channel_id' => 'UC' . Str::random(21),
                'channel_name' => "load_test_channel_{$i}",
                'channel_url' => "https://www.youtube.com/@load_test_channel_{$i}",
                'channel_thumbnail' => null,
                'is_active' => true,
                'is_live' => $isLive,
                'last_checked_at' => null,
                'last_live_at' => $isLive ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Batch insert for speed
        foreach (array_chunk($channels, 50) as $chunk) {
            MonitoredChannel::insert($chunk);
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $this->info("Created {$count} channels in " . round($duration, 2) . "ms");
        $this->info("Live channels: {$actualLiveCount}");
    }

    private function bindMockDetector(string $scenario, float $errorRate): void
    {
        $this->info("Binding mock detector...");

        $detector = new \Tests\Mocks\MockYouTubeDetector(
            minLatencyMs: 10,
            maxLatencyMs: 50,
            errorRate: $errorRate
        );

        // Get all channels that were marked as live
        $liveChannels = MonitoredChannel::where('is_active', true)
            ->where('channel_name', 'like', 'load_test_channel_%')
            ->where('is_live', true)
            ->get();

        // Pre-configure responses for live channels
        foreach ($liveChannels as $channel) {
            $detector->setResponse($channel->channel_name, LiveDetectionResult::live(
                channelId: $channel->youtube_channel_id,
                channelHandle: $channel->channel_name,
                videoId: Str::random(11),
                title: "Live Stream from {$channel->channel_name}",
                viewerCount: random_int(100, 10000),
                startedAt: now()->subMinutes(random_int(1, 30)),
                detectionMethod: 'mock'
            ));
        }

        // For mixed scenario with offline channels, set offline for others
        if ($scenario === 'mixed') {
            $offlineChannels = MonitoredChannel::where('is_active', true)
                ->where('channel_name', 'like', 'load_test_channel_%')
                ->where('is_live', false)
                ->get();

            foreach ($offlineChannels as $channel) {
                $detector->setResponse($channel->channel_name, LiveDetectionResult::offline(
                    channelId: $channel->youtube_channel_id,
                    channelHandle: $channel->channel_name,
                    detectionMethod: 'mock'
                ));
            }
        }

        // Bind as singleton for the test
        app()->instance(\App\Contracts\Services\LiveDetectionProviderInterface::class, $detector);

        $this->info("Mock detector bound successfully");
        $this->info("Live channels configured: {$liveChannels->count()}");
    }

    private function runDispatchTest(int $channelCount, bool $sync): void
    {
        $this->newLine();
        $this->info("=== DISPATCH TEST ===");

        $this->metrics['dispatch_start'] = microtime(true);

        $channels = MonitoredChannel::where('is_active', true)
            ->where('channel_name', 'like', 'load_test_channel_%')
            ->get();

        $this->info("Found {$channels->count()} active channels to dispatch");

        $dispatched = 0;
        $batchId = Str::uuid()->toString();

        $bar = $this->output->createProgressBar($channels->count());
        $bar->start();

        foreach ($channels as $channel) {
            if ($sync) {
                // Run synchronously
                $job = new DetectChannelJob($channel, $batchId);
                $job->handle(app(\App\Contracts\Services\LiveDetectionProviderInterface::class));
            } else {
                // Dispatch to queue
                DetectChannelJob::dispatch($channel, $batchId)
                    ->onQueue(config('youtube.monitoring_queue', 'youtube'));
            }
            $dispatched++;
            $bar->advance();
        }

        $bar->finish();
        $this->metrics['dispatch_end'] = microtime(true);
        $this->metrics['dispatched_count'] = $dispatched;

        $this->newLine();
        $this->info("Dispatched: {$dispatched} jobs");
    }

    private function runQueueProcessing(): void
    {
        $this->newLine();
        $this->info("=== QUEUE PROCESSING TEST ===");

        $this->metrics['processing_start'] = microtime(true);

        // Use sync mode for accurate timing
        $this->processJobsSync();

        $this->metrics['processing_end'] = microtime(true);
    }

    private function processJobsSync(): void
    {
        // Get all test channels and process them like queue workers would
        $channels = MonitoredChannel::where('is_active', true)
            ->where('channel_name', 'like', 'load_test_channel_%')
            ->get();

        $detector = app(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $batchId = Str::uuid()->toString();

        $this->info("Processing {$channels->count()} jobs synchronously...");

        $bar = $this->output->createProgressBar($channels->count());
        $bar->start();

        foreach ($channels as $channel) {
            $startTime = microtime(true);

            try {
                $job = new DetectChannelJob($channel, $batchId);
                $job->handle($detector);

                $duration = (microtime(true) - $startTime) * 1000;
                $this->metrics['job_durations'][] = $duration;

                // Count detection type
                $channel->refresh();
                if ($channel->is_live) {
                    $this->metrics['live_detected']++;
                } else {
                    $this->metrics['offline_detected']++;
                }

                $this->metrics['processed_count']++;

            } catch (\Exception $e) {
                $this->metrics['errors'][] = [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
    }

    private function displayMetrics(): void
    {
        $this->newLine();
        $this->info("=== METRICS SUMMARY ===");

        // Dispatch metrics
        $dispatchDuration = ($this->metrics['dispatch_end'] - $this->metrics['dispatch_start']) * 1000;
        $this->info("Dispatch Duration: " . round($dispatchDuration, 2) . "ms");
        $this->info("Jobs Dispatched: " . $this->metrics['dispatched_count']);

        // Queue metrics
        if ($this->metrics['processing_start'] > 0 && $this->metrics['processing_end'] > 0) {
            $processingDuration = ($this->metrics['processing_end'] - $this->metrics['processing_start']) * 1000;
            $this->info("Processing Duration: " . round($processingDuration, 2) . "ms");
            $this->info("Jobs Processed: " . $this->metrics['processed_count']);

            if ($this->metrics['processed_count'] > 0) {
                $this->info("Throughput: " . round($this->metrics['processed_count'] / ($processingDuration / 1000), 2) . " jobs/sec");
            }
        }

        // Job duration metrics
        if (!empty($this->metrics['job_durations'])) {
            $durations = $this->metrics['job_durations'];
            $this->info("Avg Job Duration: " . round(array_sum($durations) / count($durations), 2) . "ms");
            $this->info("Min Job Duration: " . round(min($durations), 2) . "ms");
            $this->info("Max Job Duration: " . round(max($durations), 2) . "ms");
        }

        // Detection summary
        $this->info("Live Detected: " . $this->metrics['live_detected']);
        $this->info("Offline Detected: " . $this->metrics['offline_detected']);
        $this->info("Blocked/Error: " . $this->metrics['blocked_detected']);

        // Errors
        if (!empty($this->metrics['errors'])) {
            $this->error("Errors: " . count($this->metrics['errors']));
            foreach ($this->metrics['errors'] as $error) {
                $this->error("  - Job {$error['job_id']}: {$error['error']}");
            }
        }

        // Queue backlog
        $backlog = DB::table('jobs')->count();
        $this->info("Queue Backlog: " . $backlog);

        // Total time
        $totalTime = (microtime(true) - $this->metrics['start_time']) * 1000;
        $this->info("Total Test Duration: " . round($totalTime, 2) . "ms");

        $this->newLine();
    }
}
