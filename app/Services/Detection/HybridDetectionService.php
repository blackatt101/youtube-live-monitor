<?php

namespace App\Services\Detection;

use App\Contracts\Services\ChannelInfo;
use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Contracts\Services\LiveDetectionResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid Detection Service
 *
 * Uses scraping for primary detection (is channel live?)
 * Uses YouTube API only for getting accurate start time when a channel first goes live.
 *
 * This is the recommended approach because:
 * - Scraping is "free" and reliable for detecting live/offline status
 * - API is called ONLY once per new stream to get actualStartTime
 * - Minimal API quota usage (~100 units per NEW stream)
 */
class HybridDetectionService implements LiveDetectionProviderInterface
{
    private YouTubeLiveDetector $scraper;
    private ?YouTubeApiDetector $api = null;
    private string $apiKey = '';

    public function __construct(?string $apiKey = null)
    {
        $this->scraper = new YouTubeLiveDetector();

        // Get API key from parameter or config
        $keyFromConfig = function_exists('config') ? config('youtube.api.key', '') : '';
        $this->apiKey = $apiKey ?? $keyFromConfig ?? '';

        // Initialize API detector only if we have a key
        if (!empty($this->apiKey)) {
            $this->api = new YouTubeApiDetector($this->apiKey);
        }
    }

    /**
     * Get the provider name
     */
    public function getProviderName(): string
    {
        return 'hybrid';
    }

    /**
     * Check if API is available for start time retrieval
     */
    public function hasApi(): bool
    {
        return $this->api !== null && $this->api->hasApiKey();
    }

    /**
     * Check if the provider supports the given channel identifier
     */
    public function supportsChannel(string $channelIdentifier): bool
    {
        return $this->scraper->supportsChannel($channelIdentifier);
    }

    /**
     * Detect live status for a single channel
     *
     * Primary: Uses scraping for live/offline detection (free, unlimited)
     * Secondary: Uses API ONLY when channel first goes live to get accurate start time
     */
    public function detect(string $channelIdentifier): LiveDetectionResult
    {
        $startTime = microtime(true);

        // Step 1: Use scraping for primary detection (free, unlimited)
        $scraperResult = $this->scraper->detect($channelIdentifier);

        // If channel is offline, return immediately (no need for API)
        if ($scraperResult->isOffline()) {
            return $scraperResult->withResponseTime((microtime(true) - $startTime) * 1000);
        }

        // If channel is live, check if we need to get start time from API
        if ($scraperResult->isLive() && $scraperResult->videoId) {
            // Try to get accurate start time from API
            $startTimeFromApi = $this->getStreamStartTimeFromApi($scraperResult->videoId);

            if ($startTimeFromApi) {
                Log::channel('youtube')->info('Using API for accurate start time', [
                    'video_id' => $scraperResult->videoId,
                    'start_time' => $startTimeFromApi->toIso8601String(),
                ]);

                // Return result with accurate start time from API
                return LiveDetectionResult::live(
                    channelId: $scraperResult->channelId,
                    channelHandle: $scraperResult->channelHandle,
                    videoId: $scraperResult->videoId,
                    title: $scraperResult->title,
                    thumbnail: $scraperResult->thumbnail,
                    viewerCount: $scraperResult->viewerCount,
                    startedAt: $startTimeFromApi,
                    detectionMethod: 'hybrid_api_start_time',
                    responseTimeMs: (microtime(true) - $startTime) * 1000,
                );
            }
        }

        // Fallback: Use scraper's result (may have approximate start time)
        return $scraperResult->withResponseTime((microtime(true) - $startTime) * 1000);
    }

    /**
     * Get accurate stream start time from YouTube API
     *
     * This is called ONLY when a channel first goes live.
     * Uses minimal API quota (~100 units per call).
     */
    private function getStreamStartTimeFromApi(string $videoId): ?Carbon
    {
        if (!$this->hasApi()) {
            Log::channel('youtube')->debug('API not available for start time, using scraper fallback');
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $this->apiKey,
                    'id' => $videoId,
                    'part' => 'liveStreamingDetails',
                ]);

            if (!$response->successful()) {
                $statusCode = $response->status();

                // Check for quota exceeded
                if ($statusCode === 429) {
                    Log::channel('youtube')->warning('YouTube API quota exceeded, using scraper fallback', [
                        'video_id' => $videoId,
                    ]);
                } else {
                    Log::channel('youtube')->warning('API call failed for start time', [
                        'video_id' => $videoId,
                        'status' => $statusCode,
                    ]);
                }
                return null;
            }

            $data = $response->json();

            if (empty($data['items'][0]['liveStreamingDetails']['actualStartTime'])) {
                Log::channel('youtube')->debug('No actualStartTime in API response', [
                    'video_id' => $videoId,
                ]);
                return null;
            }

            $actualStartTime = $data['items'][0]['liveStreamingDetails']['actualStartTime'];

            // Parse the timestamp and ensure it's stored in UTC
            // API returns ISO8601 with timezone, Carbon handles it correctly
            $startTime = Carbon::parse($actualStartTime);

            Log::channel('youtube')->debug('API actualStartTime', [
                'raw' => $actualStartTime,
                'parsed_utc' => $startTime->setTimezone('UTC')->toIso8601String(),
                'parsed_wib' => $startTime->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ]);

            return $startTime;

        } catch (\Exception $e) {
            Log::channel('youtube')->warning('Exception getting start time from API', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Detect live status for multiple channels
     */
    public function detectBatch(array $channelIdentifiers): array
    {
        return array_map(fn($id) => $this->detect($id), $channelIdentifiers);
    }

    /**
     * Validate a channel and return info
     *
     * Uses scraping for validation (free)
     */
    public function validateChannel(string $channelIdentifier): ?ChannelInfo
    {
        return $this->scraper->validateChannel($channelIdentifier);
    }
}
