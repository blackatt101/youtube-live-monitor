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
 * YouTube Data API v3 Detection Service
 *
 * Uses the official YouTube Data API to detect live streams.
 * More reliable than scraping, but requires an API key.
 *
 * API provides:
 * - actualStartTime: When the stream actually started
 * - concurrentViewers: Current viewer count
 * - liveStreamingDetails: Complete live stream info
 */
class YouTubeApiDetector implements LiveDetectionProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('youtube.api.key', '');
    }

    /**
     * Get the provider name
     */
    public function getProviderName(): string
    {
        return 'youtube_api';
    }

    /**
     * Check if API key is configured
     */
    public function hasApiKey(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Check if the provider supports the given channel identifier
     */
    public function supportsChannel(string $channelIdentifier): bool
    {
        $pattern = '/^(@[a-zA-Z0-9_]{1,30}|UC[a-zA-Z0-9_-]{21,}|https?:\/\/(www\.)?youtube\.com\/)/';
        return preg_match($pattern, $channelIdentifier) === 1;
    }

    /**
     * Detect live status for a single channel
     *
     * Uses the search API to find live broadcasts from the channel,
     * then gets detailed info including actualStartTime
     */
    public function detect(string $channelIdentifier): LiveDetectionResult
    {
        $startTime = microtime(true);
        $handle = $this->extractHandle($channelIdentifier);
        $channelId = $this->normalizeIdentifier($channelIdentifier);

        // Check if we have an API key
        if (!$this->hasApiKey()) {
            Log::warning('YouTube API key not configured, falling back to scraping');
            return LiveDetectionResult::error(
                channelId: $channelId,
                channelHandle: $handle,
                error: 'YouTube API key not configured',
                errorCode: 'NO_API_KEY',
                responseTimeMs: (microtime(true) - $startTime) * 1000,
            );
        }

        try {
            // First, resolve handle to channel ID if needed
            $resolvedChannelId = $channelId;

            if (str_starts_with($handle, '@') || !str_starts_with($channelId, 'UC')) {
                $resolvedChannelId = $this->resolveHandleToChannelId($handle);
                if (!$resolvedChannelId) {
                    return LiveDetectionResult::error(
                        channelId: $channelId,
                        channelHandle: $handle,
                        error: 'Could not resolve channel ID',
                        errorCode: 'CHANNEL_NOT_FOUND',
                        responseTimeMs: (microtime(true) - $startTime) * 1000,
                    );
                }
            }

            // Search for live streams from this channel
            $videoId = $this->searchLiveStream($resolvedChannelId);

            if (!$videoId) {
                return LiveDetectionResult::offline(
                    channelId: $resolvedChannelId,
                    channelHandle: $handle,
                    detectionMethod: 'youtube_api_v3',
                    responseTimeMs: (microtime(true) - $startTime) * 1000,
                );
            }

            // Get detailed video info including liveStreamingDetails
            $videoDetails = $this->getVideoDetails($videoId);

            if (!$videoDetails) {
                return LiveDetectionResult::error(
                    channelId: $resolvedChannelId,
                    channelHandle: $handle,
                    error: 'Could not get video details',
                    errorCode: 'API_ERROR',
                    responseTimeMs: (microtime(true) - $startTime) * 1000,
                );
            }

            // Parse the response
            return $this->parseLiveStreamResult($resolvedChannelId, $handle, $videoDetails, $startTime);

        } catch (\Exception $e) {
            Log::error('YouTube API detection error', [
                'channel' => $handle,
                'error' => $e->getMessage(),
            ]);

            return LiveDetectionResult::error(
                channelId: $channelId,
                channelHandle: $handle,
                error: $e->getMessage(),
                errorCode: 'EXCEPTION',
                responseTimeMs: (microtime(true) - $startTime) * 1000,
            );
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
     */
    public function validateChannel(string $channelIdentifier): ?ChannelInfo
    {
        if (!$this->hasApiKey()) {
            return null;
        }

        $handle = $this->extractHandle($channelIdentifier);
        $channelId = $this->normalizeIdentifier($channelIdentifier);

        try {
            // Resolve to channel ID
            $resolvedChannelId = $channelId;

            if (str_starts_with($handle, '@') || !str_starts_with($channelId, 'UC')) {
                $resolvedChannelId = $this->resolveHandleToChannelId($handle);
                if (!$resolvedChannelId) {
                    return null;
                }
            }

            // Get channel details
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/channels", [
                    'key' => $this->apiKey,
                    'id' => $resolvedChannelId,
                    'part' => 'snippet',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data['items'][0])) {
                return null;
            }

            $snippet = $data['items'][0]['snippet'] ?? [];

            return new ChannelInfo(
                channelId: $resolvedChannelId,
                handle: $handle,
                name: $snippet['title'] ?? $handle,
                thumbnail: $snippet['thumbnails']['high']['url']
                    ?? $snippet['thumbnails']['medium']['url']
                    ?? $snippet['thumbnails']['default']['url']
                    ?? null,
                url: "https://www.youtube.com/channel/{$resolvedChannelId}",
            );

        } catch (\Exception $e) {
            Log::warning('Channel validation failed', [
                'channel' => $channelIdentifier,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve a @handle to a channel ID
     */
    private function resolveHandleToChannelId(string $handle): ?string
    {
        // Check cache first
        $cacheKey = "youtube_handle_{$handle}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/channels", [
                    'key' => $this->apiKey,
                    'forHandle' => ltrim($handle, '@'),
                    'part' => 'id',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data['items'][0]['id'])) {
                return null;
            }

            $channelId = $data['items'][0]['id'];

            // Cache for 24 hours
            Cache::put($cacheKey, $channelId, now()->addHours(24));

            return $channelId;

        } catch (\Exception $e) {
            Log::warning('Handle resolution failed', [
                'handle' => $handle,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Search for live streams from a channel
     */
    private function searchLiveStream(string $channelId): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/search", [
                    'key' => $this->apiKey,
                    'channelId' => $channelId,
                    'type' => 'video',
                    'eventType' => 'live',
                    'part' => 'id',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data['items'][0]['id']['videoId'])) {
                return null;
            }

            return $data['items'][0]['id']['videoId'];

        } catch (\Exception $e) {
            Log::warning('Live stream search failed', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get detailed video information including live streaming details
     */
    private function getVideoDetails(string $videoId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/videos", [
                    'key' => $this->apiKey,
                    'id' => $videoId,
                    'part' => 'snippet,liveStreamingDetails,statistics',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            return $data['items'][0] ?? null;

        } catch (\Exception $e) {
            Log::warning('Video details fetch failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse the API response into a LiveDetectionResult
     */
    private function parseLiveStreamResult(
        string $channelId,
        string $handle,
        array $videoDetails,
        float $startTime
    ): LiveDetectionResult {
        $snippet = $videoDetails['snippet'] ?? [];
        $liveDetails = $videoDetails['liveStreamingDetails'] ?? [];
        $statistics = $videoDetails['statistics'] ?? [];

        // Extract title
        $title = $snippet['title'] ?? 'Live Stream';

        // Extract thumbnail
        $thumbnails = $snippet['thumbnails'] ?? [];
        $thumbnail = $thumbnails['maxres']['url']
            ?? $thumbnails['high']['url']
            ?? $thumbnails['medium']['url']
            ?? $thumbnails['default']['url']
            ?? null;

        // Extract viewer count (concurrent viewers)
        $viewerCount = null;
        if (isset($liveDetails['concurrentViewers'])) {
            $viewerCount = (int) $liveDetails['concurrentViewers'];
        } elseif (isset($statistics['viewCount'])) {
            // Fallback to total view count if concurrent not available
            $viewerCount = (int) $statistics['viewCount'];
        }

        // Extract actual start time - THIS IS THE KEY DATA WE WANT
        $actualStartTime = null;
        if (isset($liveDetails['actualStartTime'])) {
            $actualStartTime = Carbon::parse($liveDetails['actualStartTime']);
        }

        // Extract scheduled start time (for scheduled but not started streams)
        $scheduledStartTime = null;
        if (isset($liveDetails['scheduledStartTime'])) {
            $scheduledStartTime = Carbon::parse($liveDetails['scheduledStartTime']);
        }

        $videoId = $snippet['resourceId']['videoId'] ?? $videoDetails['id'] ?? null;

        Log::channel('youtube')->info('YouTube API detection', [
            'channel_id' => $channelId,
            'video_id' => $videoId,
            'title' => $title,
            'actual_start_time' => $actualStartTime?->toIso8601String(),
            'viewer_count' => $viewerCount,
        ]);

        return LiveDetectionResult::live(
            channelId: $channelId,
            channelHandle: $handle,
            videoId: $videoId,
            title: $title,
            thumbnail: $thumbnail,
            viewerCount: $viewerCount,
            startedAt: $actualStartTime,
            detectionMethod: 'youtube_api_v3',
            responseTimeMs: (microtime(true) - $startTime) * 1000,
        );
    }

    /**
     * Extract handle from identifier
     */
    private function extractHandle(string $identifier): string
    {
        if (str_starts_with($identifier, 'http')) {
            if (preg_match('#youtube\.com/@([^/\s]+)#', $identifier, $m)) {
                return $m[1];
            }
            if (preg_match('#youtube\.com/channel/([^/\s]+)#', $identifier, $m)) {
                return $m[1];
            }
        }

        return ltrim($identifier, '@');
    }

    /**
     * Normalize identifier to channel ID format
     */
    private function normalizeIdentifier(string $identifier): string
    {
        return ltrim($identifier, '@');
    }
}
