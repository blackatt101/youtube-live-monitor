<?php

namespace App\Console\Commands;

use App\Services\POC\YouTubeLiveDetector;
use App\Services\POC\YouTubeLiveResult;
use Illuminate\Console\Command;

class POCYouTubeLiveDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poc:youtube-live
                            {--channels=* : Specific YouTube handles to test}
                            {--concurrency=5 : Number of concurrent requests}
                            {--timeout=10 : Request timeout in seconds}
                            {--retries=2 : Number of retries on failure}
                            {--verbose-output : Show detailed output for each channel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proof of Concept: Direct YouTube Live Detection via HTTP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║      YouTube Live Detection POC - Direct cURL Method          ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        // Get options
        $channels = $this->option('channels');
        $concurrency = (int) $this->option('concurrency');
        $timeout = (int) $this->option('timeout');
        $retries = (int) $this->option('retries');
        $verbose = $this->option('verbose-output');

        // Use provided channels or defaults
        if (empty($channels)) {
            $channels = $this->getDefaultTestChannels();
        }

        $this->table(
            ['Setting', 'Value'],
            [
                ['Concurrency', $concurrency],
                ['Timeout', "{$timeout}s"],
                ['Max Retries', $retries],
                ['Channels', count($channels)],
            ]
        );

        $this->info('');
        $this->info('Testing channels...');
        $this->info('');

        // Create detector
        $detector = new YouTubeLiveDetector(
            concurrency: $concurrency,
            timeout: $timeout,
            maxRetries: $retries,
            verbose: $verbose
        );

        $startTime = microtime(true);
        $results = $detector->detectBatch($channels);
        $totalTime = (microtime(true) - $startTime) * 1000;

        // Process results
        $liveResults = [];
        $offlineResults = [];
        $errorResults = [];
        $challengeResults = [];
        $responseTimes = [];
        $methods = [];

        foreach ($results as $result) {
            if ($result->error) {
                $errorResults[] = $result;
            } elseif ($result->challengeDetected) {
                $challengeResults[] = $result;
            } elseif ($result->isLive) {
                $liveResults[] = $result;
            } else {
                $offlineResults[] = $result;
            }

            if ($result->responseTimeMs > 0) {
                $responseTimes[] = $result->responseTimeMs;
            }

            if ($result->detectionMethod) {
                $methods[$result->detectionMethod] = ($methods[$result->detectionMethod] ?? 0) + 1;
            }
        }

        // Display results
        if ($verbose || true) {
            $this->displayResults($results);
        }

        // Display summary
        $this->displaySummary($results, $liveResults, $offlineResults, $errorResults, $challengeResults, $responseTimes, $methods, $totalTime);

        // Display field analysis
        $this->displayFieldAnalysis($results);

        return $this->exitCode($errorResults, $challengeResults);
    }

    /**
     * Display detailed results for each channel
     */
    private function displayResults(array $results): void
    {
        $this->info('─'.str_repeat('─', 75));
        $this->info('DETAILED RESULTS');
        $this->info('─'.str_repeat('─', 75));

        foreach ($results as $result) {
            $this->displaySingleResult($result);
            $this->info('');
        }
    }

    /**
     * Display a single result
     */
    private function displaySingleResult(YouTubeLiveResult $result): void
    {
        $status = $this->getStatusLabel($result);
        $responseTime = round($result->responseTimeMs, 0);
        $displayHandle = ltrim($result->channelHandle, '@');

        $this->info("  [@{$displayHandle}]");
        $this->info("    Status: {$status}");
        $this->info("    Response: {$responseTime}ms");

        if ($result->detectionMethod) {
            $this->info("    Method: {$result->detectionMethod}");
        }

        if ($result->error) {
            $this->error("    Error: {$result->error}");
        }

        if ($result->challengeDetected) {
            $this->warn("    Challenge: {$result->challengeDetected}");
        }

        if ($result->isLive) {
            $this->info("    Video ID: {$result->videoId}");
            $this->info("    Title: {$result->title}");
            if ($result->thumbnail) {
                $this->info("    Thumbnail: " . substr($result->thumbnail, 0, 80) . '...');
            }
            if ($result->viewerCount) {
                $this->info("    Viewers: " . number_format($result->viewerCount));
            }
        }

        if ($result->scheduledStartTime && !$result->isLive) {
            $this->info("    Scheduled: " . $result->scheduledStartTime->toIso8601String());
        }
    }

    /**
     * Get status label for a result
     */
    private function getStatusLabel(YouTubeLiveResult $result): string
    {
        if ($result->error) {
            return '<fg=red>ERROR</>';
        }
        if ($result->challengeDetected) {
            return '<fg=yellow>CHALLENGE</>';
        }
        if ($result->isLive) {
            return '<fg=green>LIVE</>';
        }
        return '<fg=gray>OFFLINE</>';
    }

    /**
     * Display summary statistics
     */
    private function displaySummary(
        array $allResults,
        array $liveResults,
        array $offlineResults,
        array $errorResults,
        array $challengeResults,
        array $responseTimes,
        array $methods,
        float $totalTime
    ): void {
        $this->info('');
        $this->info('─'.str_repeat('─', 75));
        $this->info('SUMMARY');
        $this->info('─'.str_repeat('─', 75));

        $avgResponse = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 2)
            : 0;
        $minResponse = count($responseTimes) > 0 ? round(min($responseTimes), 2) : 0;
        $maxResponse = count($responseTimes) > 0 ? round(max($responseTimes), 2) : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Channels', count($allResults)],
                ['<fg=green>LIVE</>', count($liveResults)],
                ['<fg=gray>OFFLINE</>', count($offlineResults)],
                ['<fg=yellow>CHALLENGES</>', count($challengeResults)],
                ['<fg=red>ERRORS</>', count($errorResults)],
                ['Avg Response Time', "{$avgResponse}ms"],
                ['Min Response Time', "{$minResponse}ms"],
                ['Max Response Time', "{$maxResponse}ms"],
                ['Total Execution', round($totalTime, 0) . 'ms'],
            ]
        );

        if (!empty($methods)) {
            $this->info('');
            $this->info('Detection Methods Used:');
            foreach ($methods as $method => $count) {
                $this->info("  - {$method}: {$count}");
            }
        }
    }

    /**
     * Display field analysis for the report
     */
    private function displayFieldAnalysis(array $results): void
    {
        $this->info('');
        $this->info('─'.str_repeat('─', 75));
        $this->info('FIELD ANALYSIS');
        $this->info('─'.str_repeat('─', 75));

        // Find examples
        $liveExample = null;
        $offlineExample = null;

        foreach ($results as $result) {
            if ($result->isLive && !$liveExample) {
                $liveExample = $result;
            }
            if (!$result->isLive && !$result->error && !$result->challengeDetected && !$offlineExample) {
                $offlineExample = $result;
            }
            if ($liveExample && $offlineExample) {
                break;
            }
        }

        $this->info('');
        $this->info('LIVE Channel Example:');
        if ($liveExample) {
            $this->printExample($liveExample);
        } else {
            $this->warn('  (No live channels found in this test)');
        }

        $this->info('');
        $this->info('OFFLINE Channel Example:');
        if ($offlineExample) {
            $this->printExample($offlineExample);
        } else {
            $this->warn('  (No offline channels found in this test)');
        }

        // Display JSON structures
        $this->info('');
        $this->info('Structured Output (JSON format for integration):');

        if ($liveExample) {
            $this->info('');
            $this->info('  LIVE Example:');
            $this->line('  ' . json_encode($liveExample->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($offlineExample) {
            $this->info('');
            $this->info('  OFFLINE Example:');
            $this->line('  ' . json_encode($offlineExample->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Print an example result
     */
    private function printExample(YouTubeLiveResult $result): void
    {
        $displayHandle = ltrim($result->channelHandle, '@');

        $this->info('  Channel ID: ' . $result->channelId);
        $this->info('  Channel Handle: @' . $displayHandle);
        $this->info('  Channel URL: ' . $result->channelUrl);
        $this->info('  Is Live: ' . ($result->isLive ? 'true' : 'false'));
        $this->info('  Detection Method: ' . ($result->detectionMethod ?? 'N/A'));

        if ($result->isLive) {
            $this->info('  Video ID: ' . ($result->videoId ?? 'N/A'));
            $this->info('  Title: ' . ($result->title ?? 'N/A'));
            $this->info('  Thumbnail: ' . ($result->thumbnail ? 'Available (URL)' : 'N/A'));
            $this->info('  Viewer Count: ' . ($result->viewerCount ?? 'N/A'));
        }

        if ($result->scheduledStartTime) {
            $this->info('  Scheduled Start: ' . $result->scheduledStartTime->toIso8601String());
        }

        if ($result->rawStatus) {
            $this->info('  Raw Status: ' . $result->rawStatus);
        }
    }

    /**
     * Determine exit code based on results
     */
    private function exitCode(array $errorResults, array $challengeResults): int
    {
        if (count($errorResults) > count($errorResults) * 0.5) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /**
     * Get default test channels
     */
    private function getDefaultTestChannels(): array
    {
        return [
            // Mix of channels - some likely live, some likely offline
            '@NASA',
            '@SpaceX',
            '@LinusTechTips',
            '@MKBHD',
            '@TheVerge',
        ];
    }
}
