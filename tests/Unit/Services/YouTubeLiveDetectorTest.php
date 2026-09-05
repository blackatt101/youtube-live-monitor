<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\LiveDetectionResult;
use App\Services\Detection\YouTubeLiveDetector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeLiveDetectorTest extends TestCase
{
    private YouTubeLiveDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new YouTubeLiveDetector(
            timeout: 5,
            maxRetries: 1,
            retryDelayMs: 100,
            concurrency: 3,
            requestDelayMs: 100
        );
    }

    public function test_live_detection_result_creation(): void
    {
        $result = LiveDetectionResult::live(
            channelId: 'UC123456',
            channelHandle: 'testchannel',
            videoId: 'abc123xyz',
            title: 'Test Live Stream',
            thumbnail: 'https://example.com/thumb.jpg',
            viewerCount: 100,
            detectionMethod: 'test'
        );

        $this->assertTrue($result->isLive());
        $this->assertFalse($result->isOffline());
        $this->assertEquals('live', $result->status);
        $this->assertEquals('abc123xyz', $result->videoId);
        $this->assertEquals('Test Live Stream', $result->title);
        $this->assertEquals(100, $result->viewerCount);
    }

    public function test_offline_detection_result_creation(): void
    {
        $result = LiveDetectionResult::offline(
            channelId: 'UC123456',
            channelHandle: 'testchannel',
            detectionMethod: 'none'
        );

        $this->assertTrue($result->isOffline());
        $this->assertFalse($result->isLive());
        $this->assertEquals('offline', $result->status);
        $this->assertNull($result->videoId);
    }

    public function test_error_detection_result_creation(): void
    {
        $result = LiveDetectionResult::error(
            channelId: 'UC123456',
            channelHandle: 'testchannel',
            error: 'HTTP 404',
            errorCode: 'CHANNEL_NOT_FOUND'
        );

        $this->assertTrue($result->isError());
        $this->assertEquals('error', $result->status);
        $this->assertEquals('HTTP 404', $result->error);
        $this->assertEquals('CHANNEL_NOT_FOUND', $result->errorCode);
    }

    public function test_blocked_detection_result_creation(): void
    {
        $result = LiveDetectionResult::blocked(
            channelId: 'UC123456',
            channelHandle: 'testchannel',
            reason: 'Captcha detected'
        );

        $this->assertTrue($result->isBlocked());
        $this->assertEquals('blocked', $result->status);
        $this->assertEquals('Captcha detected', $result->error);
    }

    public function test_supports_channel_formats(): void
    {
        // Test @handle format
        $this->assertTrue($this->detector->supportsChannel('@testchannel'));

        // Test UC... format
        $this->assertTrue($this->detector->supportsChannel('UC1234567890abcdefghijklmnop'));

        // Test full URL format
        $this->assertTrue($this->detector->supportsChannel('https://www.youtube.com/@testchannel'));

        // Test invalid format
        $this->assertFalse($this->detector->supportsChannel('invalid'));
    }

    public function test_normalize_identifier(): void
    {
        // Using reflection to test private method
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('normalizeIdentifier');
        $method->setAccessible(true);

        $this->assertEquals('testchannel', $method->invoke($this->detector, '@testchannel'));
        $this->assertEquals('testchannel', $method->invoke($this->detector, 'testchannel'));
        $this->assertEquals('UC123456', $method->invoke($this->detector, 'UC123456'));
    }

    public function test_build_url(): void
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);

        $this->assertEquals(
            'https://www.youtube.com/@testchannel/live',
            $method->invoke($this->detector, '@testchannel')
        );

        $this->assertEquals(
            'https://www.youtube.com/@testchannel/live',
            $method->invoke($this->detector, 'testchannel')
        );

        $this->assertEquals(
            'https://www.youtube.com/@UC123456/live',
            $method->invoke($this->detector, 'UC123456')
        );

        // Full URL should be returned as-is
        $fullUrl = 'https://www.youtube.com/@testchannel/live';
        $this->assertEquals($fullUrl, $method->invoke($this->detector, $fullUrl));
    }

    public function test_extract_handle(): void
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractHandle');
        $method->setAccessible(true);

        $this->assertEquals('testchannel', $method->invoke($this->detector, '@testchannel'));
        $this->assertEquals('testchannel', $method->invoke($this->detector, 'testchannel'));
        $this->assertEquals('UC123456', $method->invoke($this->detector, 'UC123456'));
        $this->assertEquals('testchannel', $method->invoke($this->detector, 'https://www.youtube.com/@testchannel'));
    }

    public function test_result_to_array(): void
    {
        $result = LiveDetectionResult::live(
            channelId: 'UC123',
            channelHandle: '@test',
            videoId: 'vid123',
            title: 'Test Stream',
            viewerCount: 500,
            detectionMethod: 'test'
        );

        $array = $result->toArray();

        $this->assertEquals('UC123', $array['channel_id']);
        $this->assertEquals('@test', $array['channel_handle']);
        $this->assertEquals('live', $array['status']);
        $this->assertEquals('vid123', $array['video_id']);
        $this->assertEquals('Test Stream', $array['title']);
        $this->assertEquals(500, $array['viewer_count']);
    }
}
