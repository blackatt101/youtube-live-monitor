<?php

namespace App\Contracts\Services;

/**
 * Interface for live stream detection providers
 *
 * This abstraction allows for different detection implementations:
 * - Direct YouTube scraping (POC)
 * - YouTube InnerTube API
 * - Holodex API
 * - YouTube Data API v3
 */
interface LiveDetectionProviderInterface
{
    /**
     * Detect live status for a single channel
     */
    public function detect(string $channelIdentifier): LiveDetectionResult;

    /**
     * Detect live status for multiple channels
     *
     * @param array<string> $channelIdentifiers
     * @return array<LiveDetectionResult>
     */
    public function detectBatch(array $channelIdentifiers): array;

    /**
     * Validate a channel identifier and return channel info
     */
    public function validateChannel(string $channelIdentifier): ?ChannelInfo;

    /**
     * Get the provider name
     */
    public function getProviderName(): string;

    /**
     * Check if the provider supports the given channel identifier
     */
    public function supportsChannel(string $channelIdentifier): bool;
}

/**
 * Result of live detection
 */
class LiveDetectionResult
{
    public const STATUS_LIVE = 'live';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_ERROR = 'error';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_UNKNOWN = 'unknown';

    private function __construct(
        public readonly string $channelId,
        public readonly string $channelHandle,
        public readonly string $status,
        public readonly ?string $videoId = null,
        public readonly ?string $title = null,
        public readonly ?string $thumbnail = null,
        public readonly ?int $viewerCount = null,
        public readonly ?\DateTimeInterface $startedAt = null,
        public readonly ?\DateTimeInterface $scheduledStartTime = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly float $responseTimeMs = 0,
        public readonly ?string $detectionMethod = null,
    ) {}

    public static function live(
        string $channelId,
        string $channelHandle,
        string $videoId,
        string $title,
        ?string $thumbnail = null,
        ?int $viewerCount = null,
        ?\DateTimeInterface $startedAt = null,
        ?string $detectionMethod = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            status: self::STATUS_LIVE,
            videoId: $videoId,
            title: $title,
            thumbnail: $thumbnail,
            viewerCount: $viewerCount,
            startedAt: $startedAt,
            detectionMethod: $detectionMethod,
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function offline(
        string $channelId,
        string $channelHandle,
        ?string $detectionMethod = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            status: self::STATUS_OFFLINE,
            detectionMethod: $detectionMethod,
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function error(
        string $channelId,
        string $channelHandle,
        string $error,
        ?string $errorCode = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            status: self::STATUS_ERROR,
            error: $error,
            errorCode: $errorCode,
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function blocked(
        string $channelId,
        string $channelHandle,
        string $reason,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            status: self::STATUS_BLOCKED,
            error: $reason,
            errorCode: 'BLOCKED',
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function unknown(
        string $channelId,
        string $channelHandle,
        ?string $reason = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            status: self::STATUS_UNKNOWN,
            error: $reason,
            responseTimeMs: $responseTimeMs,
        );
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isOffline(): bool
    {
        return $this->status === self::STATUS_OFFLINE;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isUnknown(): bool
    {
        return $this->status === self::STATUS_UNKNOWN;
    }

    /**
     * Create a copy with updated response time
     */
    public function withResponseTime(float $ms): self
    {
        return new self(
            channelId: $this->channelId,
            channelHandle: $this->channelHandle,
            status: $this->status,
            videoId: $this->videoId,
            title: $this->title,
            thumbnail: $this->thumbnail,
            viewerCount: $this->viewerCount,
            startedAt: $this->startedAt,
            scheduledStartTime: $this->scheduledStartTime,
            error: $this->error,
            errorCode: $this->errorCode,
            responseTimeMs: $ms,
            detectionMethod: $this->detectionMethod,
        );
    }

    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'channel_handle' => $this->channelHandle,
            'status' => $this->status,
            'video_id' => $this->videoId,
            'title' => $this->title,
            'thumbnail' => $this->thumbnail,
            'viewer_count' => $this->viewerCount,
            'started_at' => $this->startedAt?->format('c'),
            'scheduled_start_time' => $this->scheduledStartTime?->format('c'),
            'error' => $this->error,
            'error_code' => $this->errorCode,
            'response_time_ms' => round($this->responseTimeMs, 2),
            'detection_method' => $this->detectionMethod,
        ];
    }
}

/**
 * Channel information from validation
 */
class ChannelInfo
{
    public function __construct(
        public readonly string $channelId,
        public readonly string $handle,
        public readonly string $name,
        public readonly ?string $thumbnail = null,
        public readonly ?string $url = null,
    ) {}

    public static function fromHandle(string $handle): self
    {
        return new self(
            channelId: ltrim($handle, '@'),
            handle: ltrim($handle, '@'),
            name: ltrim($handle, '@'),
        );
    }

    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'handle' => $this->handle,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail,
            'url' => $this->url,
        ];
    }
}
