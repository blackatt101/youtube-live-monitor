<?php

namespace App\Console\Commands;

use App\Jobs\DetectChannelJob;
use App\Models\MonitoredChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Test command to verify scheduler overlap behavior
 */
class SchedulerOverlapTestCommand extends Command
{
    protected $signature = 'test:scheduler-overlap
                            {--channels=50 : Number of channels}
                            {--dispatch-interval=100 : Milliseconds between dispatches}
                            {--test-duration=5 : Test duration in seconds}';

    protected $description = 'Test scheduler overlap behavior with concurrent dispatch';

    public function handle(): int
    {
        $channelCount = (int) $this->option('channels');
        $dispatchInterval = (int) $this->option('dispatch-interval');
        $testDuration = (int) $this->option('test-duration');

        $this->info("=== Scheduler Overlap Test ===");
        $this->info("Channels: {$channelCount}");
        $this->info("Dispatch interval: {$dispatchInterval}ms");
        $this->info("Test duration: {$testDuration}s");
        $this->newLine();

        // Setup test channels
        $this->setupTestChannels($channelCount);

        // Clear existing jobs
        DB::table('jobs')->truncate();

        // Track dispatches and overlapping
        $dispatches = [];
        $overlapCount = 0;

        $this->info("Starting overlap test...");
        $startTime = microtime(true);
        $cycle = 0;

        while ((microtime(true) - $startTime) < $testDuration) {
            $cycle++;
            $cycleStart = microtime(true);

            $this->info("Cycle {$cycle} started at " . round(($cycleStart - $startTime) * 1000) . "ms");

            // Check if previous cycle's jobs are still pending
            $pendingBefore = DB::table('jobs')->count();
            $this->line("  Pending jobs before dispatch: {$pendingBefore}");

            // Dispatch jobs
            $dispatched = $this->dispatchJobs();

            // Check pending after dispatch
            $pendingAfter = DB::table('jobs')->count();
            $this->line("  Dispatched: {$dispatched}");
            $this->line("  Pending jobs after dispatch: {$pendingAfter}");

            // Calculate overlap
            if ($pendingBefore > 0) {
                $overlapCount++;
                $this->warn("  ⚠️  OVERLAP DETECTED: Previous cycle's jobs still pending!");
            }

            $cycleDuration = (microtime(true) - $cycleStart) * 1000;
            $this->line("  Cycle duration: " . round($cycleDuration, 2) . "ms");
            $this->newLine();

            // Simulate dispatch interval
            usleep($dispatchInterval * 1000);
        }

        // Summary
        $this->newLine();
        $this->info("=== OVERLAP TEST SUMMARY ===");
        $this->info("Total cycles: {$cycle}");
        $this->info("Overlap count: {$overlapCount}");
        $this->info("Overlap percentage: " . round(($overlapCount / max(1, $cycle - 1)) * 100, 1) . "%");

        $finalPending = DB::table('jobs')->count();
        $this->info("Final queue backlog: {$finalPending}");

        if ($overlapCount > 0) {
            $this->error("⚠️  OVERLAP DETECTED - Scheduler protection recommended!");
        } else {
            $this->info("✓ No overlap detected");
        }

        // Check for duplicate jobs
        $this->checkDuplicateJobs();

        return self::SUCCESS;
    }

    private function setupTestChannels(int $count): void
    {
        // Delete existing test channels
        MonitoredChannel::where('channel_name', 'like', 'overlap_test_%')->delete();

        $channels = [];
        for ($i = 0; $i < $count; $i++) {
            $channels[] = [
                'youtube_channel_id' => 'UC' . Str::random(21),
                'channel_name' => "overlap_test_channel_{$i}",
                'channel_url' => "https://www.youtube.com/@overlap_test_channel_{$i}",
                'is_active' => true,
                'is_live' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($channels, 50) as $chunk) {
            MonitoredChannel::insert($chunk);
        }

        $this->info("Created {$count} test channels");
    }

    private function dispatchJobs(): int
    {
        $channels = MonitoredChannel::where('is_active', true)
            ->where('channel_name', 'like', 'overlap_test_channel_%')
            ->get();

        $dispatched = 0;
        $batchId = Str::uuid()->toString();

        foreach ($channels as $channel) {
            DetectChannelJob::dispatch($channel, $batchId)
                ->onQueue(config('youtube.monitoring_queue', 'youtube'));
            $dispatched++;
        }

        return $dispatched;
    }

    private function checkDuplicateJobs(): void
    {
        // Count jobs per channel by looking at payload data
        $jobs = DB::table('jobs')
            ->select('payload')
            ->get();

        $channelJobCounts = [];
        foreach ($jobs as $job) {
            // Use JSON decode first to check if it's JSON (for database jobs)
            $data = @unserialize($job->payload);
            if ($data !== false && isset($data->channel)) {
                $channelId = $data->channel->id ?? null;
                if ($channelId) {
                    if (!isset($channelJobCounts[$channelId])) {
                        $channelJobCounts[$channelId] = 0;
                    }
                    $channelJobCounts[$channelId]++;
                }
            }
        }

        if (empty($channelJobCounts)) {
            $this->info("No jobs to analyze for duplicates");
            return;
        }

        $maxJobsForChannel = max($channelJobCounts);
        $duplicatesFound = array_filter($channelJobCounts, fn($count) => $count > 1);

        if (count($duplicatesFound) > 0) {
            $this->warn("⚠️  DUPLICATE JOBS DETECTED:");
            $this->warn("  Max jobs for single channel: {$maxJobsForChannel}");
            $this->warn("  Channels with duplicates: " . count($duplicatesFound));
            $this->warn("  This is expected without deduplication protection!");
        } else {
            $this->info("✓ No duplicate jobs detected");
        }
    }
}
