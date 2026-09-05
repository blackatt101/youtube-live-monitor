<?php

namespace App\Services\POC;

use Carbon\Carbon;

/**
 * Data Transfer Object for YouTube Live Detection Results
 */
class YouTubeLiveResult
{
    public function __construct(
        public readonly string $channelId,
        public readonly string $channelHandle,
        public readonly string $channelUrl,
        public readonly bool $isLive,
        public readonly ?string $videoId = null,
        public readonly ?string $title = null,
        public readonly ?string $thumbnail = null,
        public readonly ?int $viewerCount = null,
        public readonly ?Carbon $scheduledStartTime = null,
        public readonly ?string $detectionMethod = null,
        public readonly ?string $rawStatus = null,
        public readonly ?string $error = null,
        public readonly float $responseTimeMs = 0,
        public readonly ?string $challengeDetected = null,
    ) {}

    /**
     * Create from successful live detection
     */
    public static function live(
        string $channelId,
        string $channelHandle,
        string $channelUrl,
        string $videoId,
        string $title,
        string $thumbnail,
        ?int $viewerCount = null,
        ?Carbon $scheduledStartTime = null,
        ?string $detectionMethod = null,
        ?string $rawStatus = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            channelUrl: $channelUrl,
            isLive: true,
            videoId: $videoId,
            title: $title,
            thumbnail: $thumbnail,
            viewerCount: $viewerCount,
            scheduledStartTime: $scheduledStartTime,
            detectionMethod: $detectionMethod,
            rawStatus: $rawStatus,
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * Create from offline detection
     */
    public static function offline(
        string $channelId,
        string $channelHandle,
        string $channelUrl,
        ?string $detectionMethod = null,
        ?string $rawStatus = null,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            channelUrl: $channelUrl,
            isLive: false,
            detectionMethod: $detectionMethod,
            rawStatus: $rawStatus,
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * Create from error
     */
    public static function error(
        string $channelId,
        string $channelHandle,
        string $channelUrl,
        string $error,
        float $responseTimeMs = 0,
    ): self {
        return new self(
            channelId: $channelId,
            channelHandle: $channelHandle,
            channelUrl: $channelUrl,
            isLive: false,
            error: $error,
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * Convert to array for reporting
     */
    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'channel_handle' => $this->channelHandle,
            'channel_url' => $this->channelUrl,
            'is_live' => $this->isLive,
            'video_id' => $this->videoId,
            'title' => $this->title,
            'thumbnail' => $this->thumbnail,
            'viewer_count' => $this->viewerCount,
            'scheduled_start_time' => $this->scheduledStartTime?->toIso8601String(),
            'detection_method' => $this->detectionMethod,
            'raw_status' => $this->rawStatus,
            'error' => $this->error,
            'response_time_ms' => round($this->responseTimeMs, 2),
            'challenge_detected' => $this->challengeDetected,
        ];
    }

    /**
     * Create a copy with challenge type set
     */
    public function withChallenge(string $challengeType): self
    {
        return new self(
            channelId: $this->channelId,
            channelHandle: $this->channelHandle,
            channelUrl: $this->channelUrl,
            isLive: $this->isLive,
            videoId: $this->videoId,
            title: $this->title,
            thumbnail: $this->thumbnail,
            viewerCount: $this->viewerCount,
            scheduledStartTime: $this->scheduledStartTime,
            detectionMethod: $this->detectionMethod,
            rawStatus: $this->rawStatus,
            error: $this->error,
            responseTimeMs: $this->responseTimeMs,
            challengeDetected: $challengeType,
        );
    }

    /**
     * Create a copy with response time set
     */
    public function withResponseTime(float $responseTimeMs): self
    {
        return new self(
            channelId: $this->channelId,
            channelHandle: $this->channelHandle,
            channelUrl: $this->channelUrl,
            isLive: $this->isLive,
            videoId: $this->videoId,
            title: $this->title,
            thumbnail: $this->thumbnail,
            viewerCount: $this->viewerCount,
            scheduledStartTime: $this->scheduledStartTime,
            detectionMethod: $this->detectionMethod,
            rawStatus: $this->rawStatus,
            error: $this->error,
            responseTimeMs: $responseTimeMs,
            challengeDetected: $this->challengeDetected,
        );
    }
}
