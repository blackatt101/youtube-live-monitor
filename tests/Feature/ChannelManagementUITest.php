<?php

namespace Tests\Feature;

use App\Models\MonitoredChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelManagementUITest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test channel API returns proper structure
     */
    public function test_channels_endpoint_returns_proper_structure(): void
    {
        MonitoredChannel::factory()->count(3)->create();

        $response = $this->getJson('/api/channels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'youtube_channel_id',
                        'channel_name',
                        'channel_url',
                        'is_active',
                        'is_live',
                        'last_checked_at',
                        'last_live_at',
                        'live_streams',
                    ]
                ],
                'meta' => [
                    'total',
                    'live_count',
                ]
            ]);
    }

    /**
     * Test adding a channel via API
     */
    public function test_can_add_valid_channel(): void
    {
        // Mock the detector to return valid channel info
        $this->mockDetectionProvider();

        $response = $this->postJson('/api/channels', [
            'channel' => '@testchannel'
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Channel added successfully',
            ])
            ->assertJsonPath('channel.channel_name', 'testchannel');
    }

    /**
     * Test duplicate channel is rejected
     */
    public function test_duplicate_channel_returns_conflict(): void
    {
        MonitoredChannel::factory()->create([
            'channel_name' => 'existing_channel'
        ]);

        $this->mockDetectionProvider();

        $response = $this->postJson('/api/channels', [
            'channel' => '@existing_channel'
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Channel already exists',
            ]);
    }

    /**
     * Test invalid channel input is validated
     */
    public function test_invalid_channel_input_is_rejected(): void
    {
        $response = $this->postJson('/api/channels', [
            'channel' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    /**
     * Test toggle monitoring status
     */
    public function test_can_toggle_monitoring_status(): void
    {
        $channel = MonitoredChannel::factory()->create([
            'is_active' => true
        ]);

        // Disable monitoring
        $response = $this->putJson("/api/channels/{$channel->id}", [
            'is_active' => false
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('monitored_channels', [
            'id' => $channel->id,
            'is_active' => false,
        ]);

        // Enable monitoring
        $response = $this->putJson("/api/channels/{$channel->id}", [
            'is_active' => true
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('monitored_channels', [
            'id' => $channel->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test delete channel
     */
    public function test_can_delete_channel(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $response = $this->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Channel removed',
            ]);

        $this->assertDatabaseMissing('monitored_channels', [
            'id' => $channel->id,
        ]);
    }

    /**
     * Test live channels endpoint
     */
    public function test_live_channels_endpoint_returns_only_live(): void
    {
        // Create mixed channels
        MonitoredChannel::factory()->create(['is_live' => true]);
        MonitoredChannel::factory()->create(['is_live' => true]);
        MonitoredChannel::factory()->create(['is_live' => false]);

        $response = $this->getJson('/api/channels/live');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test offline channels endpoint
     */
    public function test_offline_channels_endpoint_returns_only_offline(): void
    {
        // Create mixed channels
        MonitoredChannel::factory()->create(['is_live' => false, 'is_active' => true]);
        MonitoredChannel::factory()->create(['is_live' => true]);
        MonitoredChannel::factory()->create(['is_live' => false, 'is_active' => true]);

        $response = $this->getJson('/api/channels/offline');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test get single channel
     */
    public function test_can_get_single_channel(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $response = $this->getJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $channel->id)
            ->assertJsonPath('data.channel_name', $channel->channel_name);
    }

    /**
     * Test 404 for non-existent channel
     */
    public function test_returns_404_for_non_existent_channel(): void
    {
        $response = $this->getJson('/api/channels/99999');

        $response->assertStatus(404);
    }

    /**
     * Mock the detection provider for testing
     */
    private function mockDetectionProvider(): void
    {
        $mock = \Mockery::mock(\App\Contracts\Services\LiveDetectionProviderInterface::class);
        $mock->shouldReceive('validateChannel')
            ->andReturn(new \App\Contracts\Services\ChannelInfo(
                channelId: 'UCtest123456789',
                handle: 'testchannel',
                name: 'Test Channel',
                thumbnail: 'https://example.com/thumb.jpg',
                url: 'https://youtube.com/@testchannel',
            ));
        $mock->shouldReceive('getProviderName')->andReturn('mock');
        $mock->shouldReceive('supportsChannel')->andReturn(true);

        $this->app->instance(\App\Contracts\Services\LiveDetectionProviderInterface::class, $mock);
    }
}
