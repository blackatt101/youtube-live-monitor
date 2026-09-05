<?php

namespace App\Services\POC;

use Carbon\Carbon;

/**
 * Direct YouTube Live Detection Service using cURL
 *
 * This POC tests direct HTTP requests to YouTube channel pages
 * without using the YouTube Data API.
 */
class YouTubeLiveDetector
{
    private int $concurrency;
    private int $timeout;
    private int $maxRetries;
    private int $retryDelayMs;
    private bool $verbose;

    /** @var array<string> */
    private array $testChannels = [];

    public function __construct(
        int $concurrency = 5,
        int $timeout = 10,
        int $maxRetries = 2,
        int $retryDelayMs = 1000,
        bool $verbose = false
    ) {
        $this->concurrency = $concurrency;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
        $this->verbose = $verbose;
    }

    /**
     * Set test channels for the POC
     *
     * @param array<string> $channels Array of YouTube handles or channel IDs
     */
    public function setTestChannels(array $channels): self
    {
        $this->testChannels = $channels;
        return $this;
    }

    /**
     * Set verbose mode
     */
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }

    /**
     * Detect live status for a single channel
     */
    public function detect(string $handle): YouTubeLiveResult
    {
        $startTime = microtime(true);
        $url = $this->buildUrl($handle);

        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            $attempt++;
            try {
                $result = $this->makeCurlRequest($handle, $url);
                return $result->withResponseTime((microtime(true) - $startTime) * 1000);
            } catch (\Exception $e) {
                $lastError = $e->getMessage();

                if ($this->verbose) {
                    echo "  [Attempt {$attempt}] Error: {$lastError}\n";
                }

                if ($attempt <= $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        return YouTubeLiveResult::error(
            channelId: $this->normalizeHandle($handle),
            channelHandle: $handle,
            channelUrl: $url,
            error: "Failed after {$this->maxRetries} retries: {$lastError}",
            responseTimeMs: (microtime(true) - $startTime) * 1000
        );
    }

    /**
     * Detect live status for multiple channels
     *
     * @param array<string> $channels
     * @return array<YouTubeLiveResult>
     */
    public function detectBatch(array $channels): array
    {
        $results = [];

        foreach ($channels as $index => $handle) {
            $displayHandle = ltrim($handle, '@');
            if ($this->verbose) {
                echo "  Checking @{$displayHandle}...\n";
            }

            // Rate limiting: wait between individual requests
            if ($index > 0) {
                usleep(500000); // 500ms between requests
            }

            $result = $this->detect($handle);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Run the POC with default test channels
     *
     * @return array{results: array<YouTubeLiveResult>, summary: array}
     */
    public function runPOC(): array
    {
        $channels = $this->testChannels ?: $this->getDefaultTestChannels();

        echo "\n";
        echo "==================================================\n";
        echo "YouTube Live Detection POC - Direct cURL Method\n";
        echo "==================================================\n";
        echo "\n";
        echo "Configuration:\n";
        echo "  Timeout: {$this->timeout}s\n";
        echo "  Max Retries: {$this->maxRetries}\n";
        echo "  Channels: " . count($channels) . "\n";
        echo "\n";
        echo "Testing channels...\n\n";

        $startTime = microtime(true);
        $results = $this->detectBatch($channels);
        $totalTime = (microtime(true) - $startTime) * 1000;

        return [
            'results' => $results,
            'summary' => $this->generateSummary($results, $totalTime),
        ];
    }

    /**
     * Make cURL request to YouTube channel page
     */
    private function makeCurlRequest(string $handle, string $url): YouTubeLiveResult
    {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
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
            ],
            CURLOPT_ENCODING => '', // Accept all encodings
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseTimeMs = (microtime(true) - $startTime) * 1000;

        if ($response === false || !empty($error)) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP {$httpCode}");
        }

        if ($response === '') {
            throw new \Exception("Empty response");
        }

        // Check for bot/challenge detection
        $challengeResult = $this->detectChallenge($response, $url);
        if ($challengeResult !== null) {
            return $challengeResult->withResponseTime($responseTimeMs);
        }

        // Parse the response
        return $this->parseResponse($response, $handle, $url, $responseTimeMs);
    }

    /**
     * Detect if YouTube returned a bot/challenge page
     */
    private function detectChallenge(string $response, string $url): ?YouTubeLiveResult
    {
        // More specific challenge detection - check for actual challenge pages
        // NOT just the presence of "captcha" in config (like RECAPTCHA_SITEKEY)

        // Pattern 1: Check for verify page title or heading
        if (preg_match('/<title[^>]*>\s*(verify|人机验证|验证|请验证|I\'m not a robot)/i', $response)) {
            return YouTubeLiveResult::error(
                channelId: $this->normalizeHandle($url),
                channelHandle: basename(parse_url($url, PHP_URL_PATH)),
                channelUrl: $url,
                error: "Challenge detected: Verify page",
                responseTimeMs: 0
            )->withChallenge('verify_page');
        }

        // Pattern 2: Check for captcha form or iframe (not just config)
        if (preg_match('/<iframe[^>]+src=["\'][^"\']*captcha[^"\']*["\']/i', $response)) {
            return YouTubeLiveResult::error(
                channelId: $this->normalizeHandle($url),
                channelHandle: basename(parse_url($url, PHP_URL_PATH)),
                channelUrl: $url,
                error: "Challenge detected: Captcha iframe",
                responseTimeMs: 0
            )->withChallenge('captcha_iframe');
        }

        // Pattern 3: Check for specific blocked patterns
        $blockedPatterns = [
            'access_denied',
            'This page isn\'t available',
            'Not Found - YouTube',
            '人机验证',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (stripos($response, $pattern) !== false) {
                return YouTubeLiveResult::error(
                    channelId: $this->normalizeHandle($url),
                    channelHandle: basename(parse_url($url, PHP_URL_PATH)),
                    channelUrl: $url,
                    error: "Challenge detected (blocked): {$pattern}",
                    responseTimeMs: 0
                )->withChallenge('blocked');
            }
        }

        // Pattern 4: Check for unusual traffic message
        if (preg_match('/unusual traffic|explain why|prove you\'re/i', $response)) {
            return YouTubeLiveResult::error(
                channelId: $this->normalizeHandle($url),
                channelHandle: basename(parse_url($url, PHP_URL_PATH)),
                channelUrl: $url,
                error: "Challenge detected: Unusual traffic",
                responseTimeMs: 0
            )->withChallenge('unusual_traffic');
        }

        return null;
    }

    /**
     * Parse YouTube page response to extract live status
     */
    private function parseResponse(string $html, string $handle, string $url, float $responseTimeMs): YouTubeLiveResult
    {
        $channelId = $this->extractChannelId($html, $handle);

        // Method 1: Look for ytInitialData in script tags (most reliable)
        $ytInitialData = $this->extractYtInitialData($html);

        if ($ytInitialData) {
            $liveData = $this->parseYtInitialData($ytInitialData, $handle);
            if ($liveData) {
                return $this->createResultFromLiveData($liveData, $channelId, $handle, $url, $responseTimeMs, 'ytInitialData');
            }

            // Method 1b: Check currentVideoEndpoint for live video
            $liveData = $this->parseCurrentVideoEndpoint($ytInitialData, $handle);
            if ($liveData) {
                return $this->createResultFromLiveData($liveData, $channelId, $handle, $url, $responseTimeMs, 'currentVideoEndpoint');
            }
        }

        // Method 2: Look for schema.org JSON-LD (second most reliable)
        $schemaData = $this->extractSchemaOrg($html);
        if ($schemaData) {
            $liveData = $this->parseSchemaOrg($schemaData, $handle);
            if ($liveData) {
                return $this->createResultFromLiveData($liveData, $channelId, $handle, $url, $responseTimeMs, 'schema.org');
            }
        }

        // Method 3: Look for page title containing "LIVE" (reliable indicator)
        $liveData = $this->parsePageTitle($html, $handle);
        if ($liveData) {
            return $this->createResultFromLiveData($liveData, $channelId, $handle, $url, $responseTimeMs, 'page_title');
        }

        // Method 4: Look for Open Graph video tags with live indicator
        $ogData = $this->extractOpenGraph($html);
        if ($ogData && isset($ogData['og:video:type']) && str_contains($ogData['og:video:type'], 'youtube')) {
            $liveData = $this->parseOpenGraph($ogData, $handle);
            if ($liveData) {
                return $this->createResultFromLiveData($liveData, $channelId, $handle, $url, $responseTimeMs, 'OpenGraph');
            }
        }

        // NO LIVE STREAM FOUND - Channel is OFFLINE
        // We do NOT mark as LIVE based on generic patterns in the HTML
        // (sidebar recommendations, other channels, etc.)
        return YouTubeLiveResult::offline(
            channelId: $channelId,
            channelHandle: $handle,
            channelUrl: $url,
            detectionMethod: 'none',
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Parse currentVideoEndpoint for live video
     */
    private function parseCurrentVideoEndpoint(array $data, string $handle): ?array
    {
        if (isset($data['currentVideoEndpoint']['watchEndpoint']['videoId'])) {
            $videoId = $data['currentVideoEndpoint']['watchEndpoint']['videoId'];

            // Extract title from ytInitialData if available
            $title = $this->findVideoTitle($data, $videoId);

            return [
                'videoId' => $videoId,
                'title' => $title ?? 'Live Stream',
                'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
                'status' => 'live',
            ];
        }

        return null;
    }

    /**
     * Find video title by videoId in ytInitialData
     */
    private function findVideoTitle(array $data, string $videoId): ?string
    {
        $walk = function (array $array, callable $callback) use (&$walk) {
            foreach ($array as $value) {
                if (is_array($value)) {
                    $callback($value);
                    $walk($value, $callback);
                }
            }
        };

        $result = null;
        $walk($data, function ($item) use ($videoId, &$result) {
            if (isset($item['videoRenderer']['videoId']) && $item['videoRenderer']['videoId'] === $videoId) {
                $video = $item['videoRenderer'];
                if (isset($video['title']['runs'])) {
                    $result = implode('', array_column($video['title']['runs'], 'text'));
                }
            }
        });

        return $result;
    }

    /**
     * Parse page title for LIVE indicator
     */
    private function parsePageTitle(string $html, string $handle): ?array
    {
        // Check if page title contains "LIVE" - reliable indicator
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');

            // Must contain "LIVE" in the title
            if (stripos($title, 'LIVE') !== false) {
                // Extract video ID from the currentVideoEndpoint or other sources
                $videoId = $this->extractVideoIdFromHtml($html);

                if ($videoId) {
                    // Clean up title (remove " - YouTube" suffix)
                    $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $title);

                    return [
                        'videoId' => $videoId,
                        'title' => $title,
                        'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
                        'status' => 'live',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check if HTML contains a live indicator (DEPRECATED - too broad)
     * @deprecated Use structured data methods instead
     */
    private function containsLiveIndicator(string $html): bool
    {
        // Look for "LIVE" text followed by video context
        $patterns = [
            '/"label":"LIVE"/i',
            '/"title":"LIVE"/i',
            '/class="[^"]*live-badge[^"]*"/i',
            '/data-live-badge/i',
            '/"LIVE NOW"/i',
            '/\.LIVE\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract ytInitialData from HTML
     */
    private function extractYtInitialData(string $html): ?array
    {
        // Find ytInitialData start position
        if (preg_match('/ytInitialData\s*=\s*(\{)/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $startOffset = $matches[1][1];

            // Find the matching closing brace using depth tracking
            $depth = 0;
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
     * Parse ytInitialData to find live stream information
     */
    private function parseYtInitialData(array $data, string $handle): ?array
    {
        try {
            // Look for LiveStreamability in the data
            $streamability = $this->findInArray($data, 'LiveStreamability');
            if ($streamability) {
                return $this->parseLiveStreamability($streamability);
            }

            // Try to find videoRenderer with live badge
            $videos = $this->findLiveVideos($data);
            if (!empty($videos)) {
                return $videos[0];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find value in nested array
     */
    private function findInArray(array $data, string $key): ?array
    {
        foreach ($data as $k => $v) {
            if ($k === $key) {
                return $v;
            }
            if (is_array($v)) {
                $result = $this->findInArray($v, $key);
                if ($result) return $result;
            }
        }
        return null;
    }

    /**
     * Find videos with live badges in the data
     */
    private function findLiveVideos(array $data): array
    {
        $videos = [];

        $walk = function (array $array, callable $callback) use (&$walk) {
            foreach ($array as $value) {
                if (is_array($value)) {
                    $callback($value);
                    $walk($value, $callback);
                }
            }
        };

        $walk($data, function (array $item) use (&$videos) {
            if (isset($item['videoRenderer'])) {
                $video = $item['videoRenderer'];
                if ($this->isLiveVideo($video)) {
                    $videos[] = $this->extractVideoData($video);
                }
            }
        });

        return $videos;
    }

    /**
     * Check if a video renderer represents a live stream
     */
    private function isLiveVideo(array $video): bool
    {
        // Check for "LIVE NOW" badge
        if (isset($video['badges'])) {
            foreach ($video['badges'] as $badge) {
                $label = $badge['metadataBadgeRenderer'] ?? $badge['liveBadge'] ?? [];
                if (isset($label['label']) && str_contains(strtolower($label['label']), 'live')) {
                    return true;
                }
            }
        }

        // Check for thumbnail overlay
        if (isset($video['thumbnailOverlays'])) {
            foreach ($video['thumbnailOverlays'] as $overlay) {
                if (isset($overlay['thumbnailOverlayTimeStatusRenderer'])) {
                    $status = $overlay['thumbnailOverlayTimeStatusRenderer']['text'] ?? [];
                    if (isset($status['simpleText']) && strtolower($status['simpleText']) === 'live') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract video data from videoRenderer
     */
    private function extractVideoData(array $video): array
    {
        return [
            'videoId' => $video['videoId'] ?? null,
            'title' => $this->extractText($video['title'] ?? [], 'runs') ?? 'Unknown',
            'thumbnail' => $this->extractThumbnail($video['thumbnail'] ?? []),
            'viewCount' => $this->extractViewCount($video),
            'status' => 'live',
        ];
    }

    /**
     * Parse LiveStreamability data
     */
    private function parseLiveStreamability(array $data): ?array
    {
        $streamability = $data['LiveStreamability'] ?? $data['liveStreamabilityRenderer'] ?? null;

        if (!$streamability) {
            return null;
        }

        $thumbnail = $streamability['thumbnail'] ?? [];
        $title = $streamability['liveStreamabilityRenderer']['offlineSlatePlayerOverlayRenderer'] ?? [];

        return [
            'videoId' => $this->extractVideoIdFromThumbnail($thumbnail),
            'title' => 'Live Stream',
            'thumbnail' => $this->extractThumbnail($thumbnail),
            'status' => 'live',
        ];
    }

    /**
     * Extract video ID from thumbnail URL
     */
    private function extractVideoIdFromThumbnail(array $thumbnail): ?string
    {
        if (isset($thumbnail['thumbnails'])) {
            foreach ($thumbnail['thumbnails'] as $t) {
                if (isset($t['url'])) {
                    // Thumbnail URLs contain video ID
                    if (preg_match('/\/vi\/([a-zA-Z0-9_-]{11})/', $t['url'], $m)) {
                        return $m[1];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract schema.org JSON-LD data
     */
    private function extractSchemaOrg(string $html): ?array
    {
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>([\s\S]*?)<\/script>/i', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Parse schema.org data for live stream info
     */
    private function parseSchemaOrg(array $data, string $handle): ?array
    {
        if (isset($data['@type']) && $data['@type'] === 'VideoObject') {
            if (isset($data['liveBroadcast']) || isset($data['publication']['LiveBroadcastEvent'])) {
                return [
                    'videoId' => $this->extractVideoIdFromUrl($data['url'] ?? ''),
                    'title' => $data['name'] ?? 'Live Stream',
                    'thumbnail' => $data['thumbnailUrl'] ?? null,
                    'status' => 'live',
                ];
            }
        }

        return null;
    }

    /**
     * Extract Open Graph meta tags
     */
    private function extractOpenGraph(string $html): array
    {
        $data = [];

        $ogPatterns = [
            'og:video:type' => '/<meta[^>]+property=["\']og:video:type["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            'og:video:secure_url' => '/<meta[^>]+property=["\']og:video:secure_url["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            'og:title' => '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            'og:image' => '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        ];

        foreach ($ogPatterns as $key => $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $data[$key] = $m[1];
            }
        }

        return $data;
    }

    /**
     * Parse Open Graph data
     */
    private function parseOpenGraph(array $data, string $handle): ?array
    {
        if (isset($data['og:video:type']) && str_contains($data['og:video:type'], 'youtube')) {
            return [
                'videoId' => $this->extractVideoIdFromUrl($data['og:video:secure_url'] ?? ''),
                'title' => $data['og:title'] ?? 'Live Stream',
                'thumbnail' => $data['og:image'] ?? null,
                'status' => 'live',
            ];
        }

        return null;
    }

    /**
     * Parse HTML patterns for live indicators
     */
    private function parseHtmlPatterns(string $html, string $handle): ?array
    {
        // Look for "LIVE" text in the page
        $livePatterns = [
            '/"label":"LIVE"/i',
            '/"title":"LIVE"/i',
            '/class="[^"]*live-badge[^"]*"/i',
            '/data-live-badge/i',
            '/"LIVE NOW"/i',
        ];

        foreach ($livePatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                $videoId = $this->extractVideoIdFromHtml($html);
                $title = $this->extractTitleFromHtml($html);
                $thumbnail = $this->extractThumbnailFromHtml($html);
                $viewerCount = $this->extractViewerCountFromHtml($html);

                return [
                    'videoId' => $videoId,
                    'title' => $title ?? 'Live Stream',
                    'thumbnail' => $thumbnail,
                    'viewerCount' => $viewerCount,
                    'status' => 'live',
                ];
            }
        }

        return null;
    }

    /**
     * Extract viewer count from HTML
     */
    private function extractViewerCountFromHtml(string $html): ?int
    {
        // Look for "X watching" pattern (common for live streams)
        if (preg_match('/([\d,.]+)\s*(?:watching|viewers?)/i', $html, $m)) {
            return (int) str_replace([',', '.'], '', $m[1]);
        }

        // Look for short text view count in live context
        if (preg_match('/"viewCountText":\s*\{"simpleText":\s*"([^"]+)"/i', $html, $m)) {
            // This might be "1.2M views" or "5,432 watching"
            if (preg_match('/[\d,.]+/', $m[1], $count)) {
                return (int) str_replace([',', '.'], '', $count[0]);
            }
        }

        return null;
    }

    /**
     * Parse for upcoming/upcoming streams
     */
    private function parseUpcomingStream(string $html, string $handle): ?array
    {
        if (preg_match('/"label":"UPCOMING"/i', $html) || preg_match('/"scheduledStartTime":"([^"]+)"/i', $html, $m)) {
            $startTime = null;
            if (isset($m[1])) {
                // Check if it's a Unix timestamp (digits only)
                if (is_numeric($m[1])) {
                    $startTime = Carbon::createFromTimestamp((int) $m[1]);
                } else {
                    $startTime = Carbon::parse($m[1]);
                }
            }
            return [
                'title' => 'Upcoming Stream',
                'scheduledStartTime' => $startTime,
            ];
        }

        return null;
    }

    /**
     * Create result from live data
     */
    private function createResultFromLiveData(
        array $liveData,
        string $channelId,
        string $handle,
        string $url,
        float $responseTimeMs,
        string $method
    ): YouTubeLiveResult {
        return YouTubeLiveResult::live(
            channelId: $channelId,
            channelHandle: $handle,
            channelUrl: $url,
            videoId: $liveData['videoId'] ?? $this->extractVideoIdFromHtml($liveData['thumbnail'] ?? ''),
            title: $liveData['title'] ?? 'Live Stream',
            thumbnail: $liveData['thumbnail'] ?? null,
            viewerCount: $liveData['viewCount'] ?? null,
            scheduledStartTime: $liveData['scheduledStartTime'] ?? null,
            detectionMethod: $method,
            rawStatus: $liveData['status'] ?? 'live',
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Create result for upcoming stream
     */
    private function createUpcomingResult(
        array $data,
        string $channelId,
        string $handle,
        string $url,
        float $responseTimeMs
    ): YouTubeLiveResult {
        return new YouTubeLiveResult(
            channelId: $channelId,
            channelHandle: $handle,
            channelUrl: $url,
            isLive: false,
            title: $data['title'] ?? 'Upcoming Stream',
            scheduledStartTime: $data['scheduledStartTime'] ?? null,
            detectionMethod: 'upcoming',
            rawStatus: 'upcoming',
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Extract text from YouTube structured text arrays
     */
    private function extractText(array $textData, string $type): ?string
    {
        if ($type === 'runs' && isset($textData['runs'])) {
            return implode('', array_column($textData['runs'], 'text'));
        }
        if (isset($textData['simpleText'])) {
            return $textData['simpleText'];
        }
        return null;
    }

    /**
     * Extract thumbnail URL
     */
    private function extractThumbnail(array $thumbnailData): ?string
    {
        if (isset($thumbnailData['thumbnails'])) {
            $thumbnails = $thumbnailData['thumbnails'];
            // Get the last (highest quality) thumbnail
            $last = end($thumbnails);
            return $last['url'] ?? null;
        }
        return null;
    }

    /**
     * Extract view count from video data
     */
    private function extractViewCount(array $video): ?int
    {
        if (isset($video['viewCountText'])) {
            $text = $this->extractText($video['viewCountText'], 'runs')
                ?? $video['viewCountText']['simpleText'] ?? null;
            if ($text) {
                preg_match('/[\d,.]+/', $text, $matches);
                if ($matches) {
                    return (int) str_replace(',', '', $matches[0]);
                }
            }
        }
        return null;
    }

    /**
     * Extract channel ID from HTML
     */
    private function extractChannelId(string $html, string $handle): string
    {
        if (preg_match('/"channelId":"([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return $this->normalizeHandle($handle);
    }

    /**
     * Extract video ID from YouTube URL
     */
    private function extractVideoIdFromUrl(string $url): ?string
    {
        if (preg_match('/youtube\.com\/watch\?v=([^&\s]+)/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('/youtu\.be\/([^?\s]+)/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract video ID from HTML
     */
    private function extractVideoIdFromHtml(string $html): ?string
    {
        // Look for videoId in JSON data
        if (preg_match('/"videoId":"([a-zA-Z0-9_-]{11})"/', $html, $m)) {
            return $m[1];
        }

        // Look for video IDs in URLs
        if (preg_match('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $m)) {
            return $m[1];
        }

        // Look for thumbnail URLs containing video ID
        if (preg_match('/\/vi\/([a-zA-Z0-9_-]{11})\//', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract title from HTML
     */
    private function extractTitleFromHtml(string $html): ?string
    {
        // Try to find og:title first
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        // Try to find videoRenderer title
        if (preg_match('/"title":\s*\{"runs":\s*\[{"text":\s*"([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        // Try standard title tag
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        return null;
    }

    /**
     * Extract thumbnail from HTML
     */
    private function extractThumbnailFromHtml(string $html): ?string
    {
        // Look for og:image
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return $m[1];
        }

        // Look for thumbnail URLs in img tags
        if (preg_match('/<img[^>]+src=["\']([^"\']*ytimg[^"\']+)["\'][^>]*>/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Build full URL from handle
     */
    private function buildUrl(string $handle): string
    {
        if (str_starts_with($handle, 'http')) {
            return $handle;
        }

        $handle = ltrim($handle, '@');
        return "https://www.youtube.com/@{$handle}/live";
    }

    /**
     * Normalize handle to channel ID format
     */
    private function normalizeHandle(string $handle): string
    {
        $handle = ltrim($handle, '@');

        if (str_starts_with($handle, 'UC')) {
            return $handle;
        }

        return $handle;
    }

    /**
     * Generate summary report
     */
    private function generateSummary(array $results, float $totalTime): array
    {
        $liveCount = 0;
        $offlineCount = 0;
        $errorCount = 0;
        $challengeCount = 0;
        $totalResponseTime = 0;
        $responseTimes = [];
        $methods = [];
        $errors = [];

        foreach ($results as $result) {
            if ($result->error) {
                $errorCount++;
                $errors[] = [
                    'channel' => $result->channelHandle,
                    'error' => $result->error,
                ];
            } elseif ($result->challengeDetected) {
                $challengeCount++;
            } elseif ($result->isLive) {
                $liveCount++;
            } else {
                $offlineCount++;
            }

            if ($result->responseTimeMs > 0) {
                $totalResponseTime += $result->responseTimeMs;
                $responseTimes[] = $result->responseTimeMs;
            }

            if ($result->detectionMethod) {
                $methods[$result->detectionMethod] = ($methods[$result->detectionMethod] ?? 0) + 1;
            }
        }

        $avgResponseTime = count($responseTimes) > 0
            ? array_sum($responseTimes) / count($responseTimes)
            : 0;

        return [
            'total_channels' => count($results),
            'live_count' => $liveCount,
            'offline_count' => $offlineCount,
            'error_count' => $errorCount,
            'challenge_count' => $challengeCount,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'total_time_ms' => round($totalTime, 2),
            'detection_methods' => $methods,
            'errors' => $errors,
        ];
    }

    /**
     * Get default test channels for POC
     */
    private function getDefaultTestChannels(): array
    {
        return [
            '@NASA',
            '@SpaceX',
            '@LinusTechTips',
            '@MKBHD',
            '@TheVerge',
        ];
    }
}
