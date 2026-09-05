<?php

namespace Tests\Mocks;

use App\Contracts\Services\ChannelInfo;
use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Contracts\Services\LiveDetectionResult;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Mock detector for load testing
 *
 * Simulates various YouTube responses without making real HTTP requests
 */
class MockYouTubeDetector implements LiveDetectionProviderInterface
{
    private array $responses = [];
    private int $requestCount = 0;
    private int $minLatencyMs;
    private int $maxLatencyMs;
    private float $errorRate;
    private string $defaultScenario;

    public function __construct(
        int $minLatencyMs = 10,
        int $maxLatencyMs = 50,
        float $errorRate = 0.0,
        string $defaultScenario = 'offline'
    ) {
        $this->minLatencyMs = $minLatencyMs;
        $this->maxLatencyMs = $maxLatencyMs;
        $this->errorRate = $errorRate;
        $this->defaultScenario = $defaultScenario;
    }

    /**
     * Set predefined responses for specific channels
     */
    public function setResponse(string $channelHandle, LiveDetectionResult $result): void
    {
        $this->responses[$channelHandle] = $result;
    }

    /**
     * Configure bulk responses by scenario
     */
    public function setBulkResponses(array $responses): void
    {
        $this->responses = array_merge($this->responses, $responses);
    }

    /**
     * Get the total number of requests made
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * Reset request counter
     */
    public function resetRequestCount(): void
    {
        $this->requestCount = 0;
    }

    public function detect(string $channelIdentifier): LiveDetectionResult
    {
        $this->requestCount++;
        $startTime = microtime(true);

        // Simulate network latency
        $latency = random_int($this->minLatencyMs, $this->maxLatencyMs);
        usleep($latency * 1000);

        // Check for predefined response
        if (isset($this->responses[$channelIdentifier])) {
            return $this->responses[$channelIdentifier]
                ->withResponseTime((microtime(true) - $startTime) * 1000);
        }

        // Simulate error rate
        if (mt_rand(1, 10000) / 10000 < $this->errorRate) {
            $errors = [
                LiveDetectionResult::error(
                    channelId: $channelIdentifier,
                    channelHandle: $channelIdentifier,
                    error: 'Simulated timeout',
                    errorCode: 'TIMEOUT'
                ),
                LiveDetectionResult::error(
                    channelId: $channelIdentifier,
                    channelHandle: $channelIdentifier,
                    error: 'Simulated HTTP 429',
                    errorCode: 'RATE_LIMITED'
                ),
                LiveDetectionResult::error(
                    channelId: $channelIdentifier,
                    channelHandle: $channelIdentifier,
                    error: 'Simulated connection failure',
                    errorCode: 'CONNECTION_FAILED'
                ),
            ];
            return $errors[array_rand($errors)]
                ->withResponseTime((microtime(true) - $startTime) * 1000);
        }

        // Return default scenario
        return match ($this->defaultScenario) {
            'live' => LiveDetectionResult::live(
                channelId: 'UC' . Str::random(21),
                channelHandle: $channelIdentifier,
                videoId: Str::random(11),
                title: "Live Stream from {$channelIdentifier}",
                viewerCount: random_int(100, 50000),
                startedAt: Carbon::now()->subMinutes(random_int(1, 60)),
                detectionMethod: 'mock'
            ),
            'blocked' => LiveDetectionResult::blocked(
                channelId: $channelIdentifier,
                channelHandle: $channelIdentifier,
                reason: 'Mocked blocked response'
            ),
            default => LiveDetectionResult::offline(
                channelId: $channelIdentifier,
                channelHandle: $channelIdentifier,
                detectionMethod: 'mock'
            ),
        };
    }

    public function detectBatch(array $channelIdentifiers): array
    {
        return array_map(fn($id) => $this->detect($id), $channelIdentifiers);
    }

    public function validateChannel(string $channelIdentifier): ?ChannelInfo
    {
        return new ChannelInfo(
            channelId: 'UC' . Str::random(21),
            handle: ltrim($channelIdentifier, '@'),
            name: ucfirst(ltrim($channelIdentifier, '@')),
            url: "https://youtube.com/@" . ltrim($channelIdentifier, '@'),
        );
    }

    public function getProviderName(): string
    {
        return 'mock_detector';
    }

    public function supportsChannel(string $channelIdentifier): bool
    {
        return true;
    }
}

/**
 * Factory for creating mock detection scenarios
 */
class MockDetectorFactory
{
    /**
     * Create detector for normal offline channels
     */
    public static function offline(float $errorRate = 0.0): MockYouTubeDetector
    {
        return new MockYouTubeDetector(
            minLatencyMs: 10,
            maxLatencyMs: 30,
            errorRate: $errorRate,
            defaultScenario: 'offline'
        );
    }

    /**
     * Create detector for mixed live/offline channels
     */
    public static function mixed(int $liveCount, int $totalCount): MockYouTubeDetector
    {
        $detector = new MockYouTubeDetector();

        for ($i = 0; $i < $totalCount; $i++) {
            $handle = "channel{$i}";
            if ($i < $liveCount) {
                $detector->setResponse($handle, LiveDetectionResult::live(
                    channelId: "UC" . Str::random(21),
                    channelHandle: $handle,
                    videoId: Str::random(11),
                    title: "Live Stream #{$i}",
                    viewerCount: random_int(100, 10000),
                    startedAt: Carbon::now()->subMinutes(random_int(1, 30)),
                    detectionMethod: 'mock'
                ));
            } else {
                $detector->setResponse($handle, LiveDetectionResult::offline(
                    channelId: "UC" . Str::random(21),
                    channelHandle: $handle,
                    detectionMethod: 'mock'
                ));
            }
        }

        return $detector;
    }

    /**
     * Create detector that simulates various error scenarios
     */
    public static function withErrors(float $timeoutRate = 0.02, float $rateLimitRate = 0.01): MockYouTubeDetector
    {
        return new MockYouTubeDetector(
            minLatencyMs: 5,
            maxLatencyMs: 20,
            errorRate: $timeoutRate + $rateLimitRate,
            defaultScenario: 'offline'
        );
    }

    /**
     * Create detector with specific latency characteristics
     */
    public static function withLatency(int $minMs, int $maxMs): MockYouTubeDetector
    {
        return new MockYouTubeDetector(
            minLatencyMs: $minMs,
            maxLatencyMs: $maxMs,
            errorRate: 0.0,
            defaultScenario: 'offline'
        );
    }
}
