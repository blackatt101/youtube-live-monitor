<?php

namespace App\Services\Detection;

use App\Contracts\Services\ChannelInfo;
use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Contracts\Services\LiveDetectionResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Direct YouTube Live Detection Service
 *
 * Detects live streams by scraping YouTube channel pages directly
 * without using the YouTube Data API.
 *
 * Note: Do NOT use cookies - YouTube returns different content (without ytInitialData)
 * when cookies are present, causing detection to fail.
 */
class YouTubeLiveDetector implements LiveDetectionProviderInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
    private const BASE_URL = 'https://www.youtube.com';

    private int $timeout;
    private int $maxRetries;
    private int $retryDelayMs;
    private int $concurrency;
    private int $requestDelayMs;
    private int $consecutiveErrors = 0;
    private int $cooldownUntil = 0;

    public function __construct(
        int $timeout = 15,
        int $maxRetries = 2,
        int $retryDelayMs = 1000,
        int $concurrency = 5,
        int $requestDelayMs = 500
    ) {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
        $this->concurrency = $concurrency;
        $this->requestDelayMs = $requestDelayMs;
    }

    /**
     * Get the provider name
     */
    public function getProviderName(): string
    {
        return 'youtube_direct';
    }

    /**
     * Check if the provider supports the given channel identifier
     */
    public function supportsChannel(string $channelIdentifier): bool
    {
        // Supports @handles, UC... IDs, and full URLs
        $pattern = '/^(@[a-zA-Z0-9_]{1,30}|UC[a-zA-Z0-9_-]{21,}|https?:\/\/(www\.)?youtube\.com\/)/';
        return preg_match($pattern, $channelIdentifier) === 1;
    }

    /**
     * Detect live status for a single channel
     */
    public function detect(string $channelIdentifier): LiveDetectionResult
    {
        // Check cooldown
        if ($this->isInCooldown()) {
            Log::debug("YouTube detector in cooldown, returning blocked");
            return LiveDetectionResult::blocked(
                channelId: $this->normalizeIdentifier($channelIdentifier),
                channelHandle: $channelIdentifier,
                reason: 'Rate limit cooldown',
            );
        }

        $startTime = microtime(true);
        $url = $this->buildUrl($channelIdentifier);
        $handle = $this->extractHandle($channelIdentifier);

        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            $attempt++;

            try {
                // Exponential backoff
                if ($attempt > 1) {
                    $backoff = $this->retryDelayMs * pow(2, $attempt - 2);
                    usleep($backoff * 1000);
                }

                $result = $this->makeRequest($handle, $url);
                $result = $result->withResponseTime((microtime(true) - $startTime) * 1000);

                // Reset error counter on success
                $this->consecutiveErrors = 0;

                // Log the detection
                $this->logDetection($result);

                return $result;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->consecutiveErrors++;

                Log::warning("YouTube detection attempt {$attempt} failed for {$handle}: {$lastError}");

                // Trigger cooldown after multiple consecutive errors
                if ($this->consecutiveErrors >= 3) {
                    $this->startCooldown(60); // 60 second cooldown
                    Log::warning("YouTube detector entering cooldown due to consecutive errors");
                }
            }
        }

        return LiveDetectionResult::error(
            channelId: $this->normalizeIdentifier($channelIdentifier),
            channelHandle: $channelIdentifier,
            error: "Failed after {$this->maxRetries} retries: {$lastError}",
            errorCode: 'MAX_RETRIES_EXCEEDED',
            responseTimeMs: (microtime(true) - $startTime) * 1000
        );
    }

    /**
     * Detect live status for multiple channels
     *
     * @param array<string> $channelIdentifiers
     * @return array<LiveDetectionResult>
     */
    public function detectBatch(array $channelIdentifiers): array
    {
        $results = [];

        foreach ($channelIdentifiers as $index => $identifier) {
            // Rate limiting delay between requests
            if ($index > 0) {
                usleep($this->requestDelayMs * 1000);
            }

            $results[] = $this->detect($identifier);
        }

        return $results;
    }

    /**
     * Validate a channel and return info
     */
    public function validateChannel(string $channelIdentifier): ?ChannelInfo
    {
        try {
            // Use main channel page (not /live) to get correct avatar
            $url = $this->buildUrl($channelIdentifier, false);
            $handle = $this->extractHandle($channelIdentifier);

            // Note: Do NOT use cookies - YouTube returns different content without ytInitialData
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $this->getHeaders(),
                CURLOPT_ENCODING => '',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 404) {
                return null;
            }

            if ($httpCode !== 200) {
                return null;
            }

            // Extract channel info
            $channelId = $this->extractChannelId($response, $handle);
            $name = $this->extractChannelName($response);
            $thumbnail = $this->extractChannelThumbnail($response);

            return new ChannelInfo(
                channelId: $channelId,
                handle: $handle,
                name: $name ?? $handle,
                thumbnail: $thumbnail,
                url: $url,
            );
        } catch (\Exception $e) {
            Log::warning("Channel validation failed for {$channelIdentifier}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Make HTTP request to YouTube channel page
     */
    private function makeRequest(string $handle, string $url): LiveDetectionResult
    {
        // Note: Do NOT use cookies - YouTube returns different content without ytInitialData
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_ENCODING => '', // Accept all encodings - important for full response
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode === 429) {
            $this->startCooldown(120);
            throw new \Exception("HTTP 429 - Rate limited");
        }

        if ($httpCode === 403) {
            $this->startCooldown(60);
            throw new \Exception("HTTP 403 - Access forbidden");
        }

        if ($httpCode === 404) {
            throw new \Exception("HTTP 404 - Channel not found");
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP {$httpCode}");
        }

        if ($response === '') {
            throw new \Exception("Empty response");
        }

        // Check for challenge/bot detection
        $challengeResult = $this->detectChallenge($response, $url, $handle);
        if ($challengeResult !== null) {
            return $challengeResult;
        }

        // Parse the response
        return $this->parseResponse($response, $handle, $url);
    }

    /**
     * Get HTTP headers
     */
    private function getHeaders(): array
    {
        return [
            'User-Agent: ' . self::USER_AGENT,
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'Referer: https://www.youtube.com/',
        ];
    }

    /**
     * Detect if YouTube returned a bot/challenge page
     */
    private function detectChallenge(string $response, string $url, string $handle): ?LiveDetectionResult
    {
        // Check for verify page title
        if (preg_match('/<title[^>]*>\s*(verify|人机验证|验证|请验证)/i', $response)) {
            $this->startCooldown(300);
            return LiveDetectionResult::blocked(
                channelId: $this->normalizeIdentifier($handle),
                channelHandle: $handle,
                reason: 'Verify page detected',
            );
        }

        // Check for captcha iframe
        if (preg_match('/<iframe[^>]+src=["\'][^"\']*captcha[^"\']*["\']/i', $response)) {
            $this->startCooldown(300);
            return LiveDetectionResult::blocked(
                channelId: $this->normalizeIdentifier($handle),
                channelHandle: $handle,
                reason: 'Captcha iframe detected',
            );
        }

        // Check for unusual traffic
        if (preg_match('/unusual traffic|explain why|prove you\'re/i', $response)) {
            $this->startCooldown(180);
            return LiveDetectionResult::blocked(
                channelId: $this->normalizeIdentifier($handle),
                channelHandle: $handle,
                reason: 'Unusual traffic challenge',
            );
        }

        return null;
    }

    /**
     * Parse YouTube page response
     */
    private function parseResponse(string $html, string $handle, string $url): LiveDetectionResult
    {
        $channelId = $this->extractChannelId($html, $handle);
        $pageTitle = $this->extractPageTitle($html);

        // Method 1: ytInitialData with currentVideoEndpoint
        $ytInitialData = $this->extractYtInitialData($html);
        if ($ytInitialData) {
            // Check currentVideoEndpoint for live video
            if (isset($ytInitialData['currentVideoEndpoint']['watchEndpoint']['videoId'])) {
                $videoId = $ytInitialData['currentVideoEndpoint']['watchEndpoint']['videoId'];
                $title = $this->findVideoTitle($ytInitialData, $videoId);
                // Fallback to page title if no title found
                if (!$title && $pageTitle) {
                    $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $pageTitle);
                }
                $title = $title ?: 'Live Stream';
                $viewerCount = $this->findViewerCount($ytInitialData);

                return LiveDetectionResult::live(
                    channelId: $channelId,
                    channelHandle: $handle,
                    videoId: $videoId,
                    title: $title,
                    thumbnail: "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
                    viewerCount: $viewerCount,
                    startedAt: Carbon::now(),
                    detectionMethod: 'currentVideoEndpoint',
                );
            }

            // Check for LiveStreamability with enhanced title extraction
            $liveData = $this->parseLiveStreamability($ytInitialData);
            if ($liveData) {
                // Try to find actual title from ytInitialData using the videoId
                $title = $this->findVideoTitle($ytInitialData, $liveData['videoId']);
                // Fallback to page title if no title found
                if (!$title && $pageTitle) {
                    $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $pageTitle);
                }
                $title = $title ?: 'Live Stream';
                $viewerCount = $this->findViewerCount($ytInitialData);

                return LiveDetectionResult::live(
                    channelId: $channelId,
                    channelHandle: $handle,
                    videoId: $liveData['videoId'],
                    title: $title,
                    thumbnail: $liveData['thumbnail'],
                    viewerCount: $viewerCount ?? $liveData['viewerCount'] ?? null,
                    detectionMethod: 'LiveStreamability',
                );
            }
        }

        // Method 2: Page title containing "LIVE" or video info
        if ($pageTitle) {
            $videoId = $this->extractVideoIdFromHtml($html);
            if ($videoId) {
                // Use page title, clean it up
                $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $pageTitle);

                // If title is too generic, try to get better title from ytInitialData
                if (in_array(strtolower($title), ['live', 'live stream', 'youtube', $handle])) {
                    $ytInitialData = $this->extractYtInitialData($html);
                    if ($ytInitialData) {
                        $betterTitle = $this->findVideoTitle($ytInitialData, $videoId);
                        if ($betterTitle) {
                            $title = $betterTitle;
                        }
                    }
                }

                return LiveDetectionResult::live(
                    channelId: $channelId,
                    channelHandle: $handle,
                    videoId: $videoId,
                    title: $title,
                    thumbnail: "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
                    detectionMethod: 'page_title',
                );
            }

            // Even without video ID, if page title suggests live content
            if (stripos($pageTitle, 'live') !== false || stripos($pageTitle, 'streaming') !== false) {
                $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $pageTitle);
                // Try to find video in the page
                $videoId = $this->extractVideoIdFromHtml($html);
                if ($videoId) {
                    return LiveDetectionResult::live(
                        channelId: $channelId,
                        channelHandle: $handle,
                        videoId: $videoId,
                        title: $title ?: 'Live Stream',
                        thumbnail: "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
                        detectionMethod: 'page_title',
                    );
                }
            }
        }

        // No live stream found - channel is offline
        return LiveDetectionResult::offline(
            channelId: $channelId,
            channelHandle: $handle,
            detectionMethod: 'none',
        );
    }

    /**
     * Extract page title from HTML
     */
    private function extractPageTitle(string $html): ?string
    {
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    /**
     * Extract ytInitialData from HTML
     */
    private function extractYtInitialData(string $html): ?array
    {
        // Use a pattern that captures from ytInitialData = to the end, then use depth tracking
        // The old pattern {.+?} with non-greedy was stopping too early
        if (preg_match('/ytInitialData\s*=\s*(\{)/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $startOffset = $matches[1][1];
            $depth = 0;

            // Find the matching closing brace using depth tracking
            for ($i = $startOffset; $i < strlen($html); $i++) {
                if ($html[$i] === '{') {
                    $depth++;
                } elseif ($html[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        // Extract the complete JSON
                        $json = substr($html, $startOffset, $i - $startOffset + 1);
                        $data = json_decode($json, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $data;
                        }
                        break;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parse LiveStreamability data
     */
    private function parseLiveStreamability(array $data): ?array
    {
        $found = null;
        array_walk_recursive($data, function ($value, $key) use (&$found) {
            if ($key === 'LiveStreamability' || $key === 'liveStreamabilityRenderer') {
                $found = $value;
            }
        });

        if (!$found) {
            return null;
        }

        $thumbnail = $found['thumbnail'] ?? [];
        $videoId = $this->extractVideoIdFromThumbnail($thumbnail);

        if (!$videoId) {
            return null;
        }

        return [
            'videoId' => $videoId,
            'title' => 'Live Stream',
            'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
        ];
    }

    /**
     * Extract video ID from thumbnail URL
     */
    private function extractVideoIdFromThumbnail(array $thumbnail): ?string
    {
        if (isset($thumbnail['thumbnails'])) {
            foreach ($thumbnail['thumbnails'] as $t) {
                if (isset($t['url']) && preg_match('/\/vi\/([a-zA-Z0-9_-]{11})/', $t['url'], $m)) {
                    return $m[1];
                }
            }
        }
        return null;
    }

    /**
     * Find video title by videoId
     */
private function findVideoTitle(array $data, string $videoId): ?string
{
    $result = null;

    $walk = function ($node) use (&$walk, $videoId, &$result) {
        if (!is_array($node) || $result !== null) {
            return;
        }

        // Find an object containing the target videoId
        if (($node['videoId'] ?? null) === $videoId) {
            if (isset($node['title']['runs']) &&
is_array($node['title']['runs'])) {
                $title = '';

                foreach ($node['title']['runs'] as $run) {
                    $title .= $run['text'] ?? '';
                }

                if (trim($title) !== '') {
                    $result = trim($title);
                    return;
                }
            }

            if (isset($node['title']['simpleText'])) {
                $title = trim($node['title']['simpleText']);

                if ($title !== '') {
                    $result = $title;
                    return;
                }
            }
        }

        // Continue recursively through child arrays
        foreach ($node as $value) {
            if (is_array($value)) {
                $walk($value);
            }
        }
    };

    $walk($data);

    return $result;
}
    /**
     * Find viewer count in data
     */
    private function findViewerCount(array $data): ?int
    {
        $result = null;
        array_walk_recursive($data, function ($value, $key) use (&$result) {
            if ($key === 'originalViewCount' && is_numeric($value)) {
                $result = (int) $value;
            }
        });
        return $result;
    }

    /**
     * Extract channel ID from HTML
     */
    private function extractChannelId(string $html, string $handle): string
    {
        if (preg_match('/"channelId":"([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return $this->normalizeIdentifier($handle);
    }

    /**
     * Extract channel name
     */
    private function extractChannelName(string $html): ?string
    {
        if (preg_match('/<meta[^>]*name="title"[^>]*content="([^"]+)"/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            return html_entity_decode(str_replace(' - YouTube', '', $m[1]), ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    /**
     * Extract channel thumbnail (avatar)
     */
    private function extractChannelThumbnail(string $html): ?string
    {
        // First try to extract from ytInitialData (most reliable for avatar)
        $ytInitialData = $this->extractYtInitialData($html);
        if ($ytInitialData) {
            $avatar = $this->extractChannelAvatarFromData($ytInitialData);
            if ($avatar) {
                return $avatar;
            }
        }

        // Fallback: Look for avatar image URL in HTML
        // YouTube typically uses these patterns for channel avatars
        if (preg_match('/"avatar":\{"thumbnails":\[\{"url":"([^"]+)"/i', $html, $m)) {
            return $this->cleanAvatarUrl($m[1]);
        }

        if (preg_match('/<link[^>]*itemprop="image"[^>]*href="([^"]+)"/i', $html, $m)) {
            return $this->cleanAvatarUrl($m[1]);
        }

        if (preg_match('/<meta[^>]*property="og:image"[^>]*content="([^"]+)"/i', $html, $m)) {
            return $this->cleanAvatarUrl($m[1]);
        }

        return null;
    }

    /**
     * Extract channel avatar from ytInitialData
     */
    private function extractChannelAvatarFromData(array $data): ?string
    {
        // First try: Look for the specific "avatar" key in metadata or header sections
        // This is the most reliable way to get the channel's own avatar

        // Check metadata section
        if (isset($data['metadata']['channelMetadataRenderer']['avatar']['thumbnails'][0]['url'])) {
            return $this->cleanAvatarUrl($data['metadata']['channelMetadataRenderer']['avatar']['thumbnails'][0]['url']);
        }

        // Check c4TabbedHeaderRenderer (legacy but still used)
        if (isset($data['metadata']['c4TabbedHeaderRenderer']['avatar']['thumbnails'][0]['url'])) {
            return $this->cleanAvatarUrl($data['metadata']['c4TabbedHeaderRenderer']['avatar']['thumbnails'][0]['url']);
        }

        // Check header section
        if (isset($data['header']['c4TabbedHeaderRenderer']['avatar']['thumbnails'][0]['url'])) {
            return $this->cleanAvatarUrl($data['header']['c4TabbedHeaderRenderer']['avatar']['thumbnails'][0]['url']);
        }

        // Check for avatar in topbarRenderer
        if (isset($data['topbar']['topbarMenusRenderer']['topbarNavigationMenuRenderer']['navigationEndpoints'])) {
            foreach ($data['topbar']['topbarMenusRenderer']['topbarNavigationMenuRenderer']['navigationEndpoints'] ?? [] as $endpoint) {
                if (isset($endpoint['confirmDialogEndpoint']['content']['confirmDialogRenderer']['confirmButton']['buttonRenderer']['navigationEndpoint']['signinEndpoint']['nextRouteAction']['addToPlaylistRoute']['playlistId'])) {
                    // Found playlist route - look for avatar elsewhere
                }
            }
        }

        // Fallback: Look for avatar in macroMatchers or anywhere with specific patterns
        // The channel avatar typically has the channel ID encoded in it and contains "s900" or high-res sizes
        $found = null;
        $foundSize = 0;

        array_walk_recursive($data, function ($value, $key) use (&$found, &$foundSize) {
            if ($key === 'url' && is_string($value)) {
                // Check if it's a high-resolution avatar (s100 or higher)
                if (preg_match('/yt3\.googleusercontent\.com\/([^\/]+)=s(\d+)/', $value, $m)) {
                    $size = (int)$m[2];
                    // Prefer larger sizes and channel-specific URLs (not video thumbnails)
                    if ($size >= $foundSize && !preg_match('/\/vi\//', $value)) {
                        $found = $value;
                        $foundSize = $size;
                    }
                }
            }
        });

        if ($found) {
            return $this->cleanAvatarUrl($found);
        }

        return null;
    }

    /**
     * Clean and normalize avatar URL
     */
    private function cleanAvatarUrl(string $url): string
    {
        // Fix protocol-relative URLs
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        // Try to get a good size for the avatar (prefer higher resolution)
        // Extract current size
        if (preg_match('/=s(\d+)/', $url, $m)) {
            $currentSize = (int)$m[1];
            // If size is too small, try to get larger one
            if ($currentSize < 100) {
                $url = preg_replace('/=s\d+/', '=s200', $url);
            }
            // If size is reasonable, keep it but ensure it's at least s100
            if ($currentSize >= 100 && $currentSize < 200) {
                $url = preg_replace('/=s\d+/', '=s200', $url);
            }
        }

        return $url;
    }

    /**
     * Extract video ID from HTML
     */
    private function extractVideoIdFromHtml(string $html): ?string
    {
        if (preg_match('/"videoId":"([a-zA-Z0-9_-]{11})"/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/\/vi\/([a-zA-Z0-9_-]{11})\//', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Build URL from channel identifier
     */
    private function buildUrl(string $identifier, bool $includeLive = true): string
    {
        if (str_starts_with($identifier, 'http')) {
            return $identifier;
        }

        $handle = ltrim($identifier, '@');
        if ($includeLive) {
            return self::BASE_URL . "/@{$handle}/live";
        }
        return self::BASE_URL . "/@{$handle}";
    }

    /**
     * Extract handle from identifier
     */
    private function extractHandle(string $identifier): string
    {
        if (str_starts_with($identifier, 'http')) {
            // Extract handle from URL
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

    /**
     * Check if in cooldown period
     */
    private function isInCooldown(): bool
    {
        return time() < $this->cooldownUntil;
    }

    /**
     * Start cooldown period
     */
    private function startCooldown(int $seconds): void
    {
        $this->cooldownUntil = time() + $seconds;
        $this->consecutiveErrors = 0;
    }

    /**
     * Log detection result
     */
    private function logDetection(LiveDetectionResult $result): void
    {
        Log::channel('youtube')->info('YouTube detection', [
            'channel_id' => $result->channelId,
            'channel_handle' => $result->channelHandle,
            'status' => $result->status,
            'video_id' => $result->videoId,
            'title' => $result->title,
            'viewer_count' => $result->viewerCount,
            'response_time_ms' => round($result->responseTimeMs, 2),
            'detection_method' => $result->detectionMethod,
            'error' => $result->error,
        ]);
    }
}
