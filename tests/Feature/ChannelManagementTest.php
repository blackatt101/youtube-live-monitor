<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_channels(): void
    {
        MonitoredChannel::factory()->count(3)->create();

        $response = $this->getJson('/api/channels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['total', 'live_count'],
            ]);
    }

    public function test_can_add_channel(): void
    {
        // Mock the detector to return valid channel info
        $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class, function ($mock) {
            $mock->shouldReceive('validateChannel')
                ->andReturn(new \App\Contracts\Services\ChannelInfo(
                    channelId: 'UC123456',
                    handle: 'testchannel',
                    name: 'Test Channel',
                    thumbnail: 'https://example.com/thumb.jpg',
                    url: 'https://www.youtube.com/@testchannel',
                ));
        });

        $response = $this->postJson('/api/channels', [
            'channel' => '@testchannel',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'channel' => [
                    'id',
                    'youtube_channel_id',
                    'channel_name',
                    'is_active',
                ],
            ]);

        $this->assertDatabaseHas('monitored_channels', [
            'channel_name' => 'testchannel',
            'youtube_channel_id' => 'UC123456',
        ]);
    }

    public function test_cannot_add_duplicate_channel(): void
    {
        MonitoredChannel::factory()->create(['channel_name' => 'testchannel']);

        $this->mock(\App\Contracts\Services\LiveDetectionProviderInterface::class, function ($mock) {
            $mock->shouldReceive('validateChannel')
                ->andReturn(new \App\Contracts\Services\ChannelInfo(
                    channelId: 'UC123456',
                    handle: 'testchannel',
                    name: 'Test Channel',
                ));
        });

        $response = $this->postJson('/api/channels', [
            'channel' => '@testchannel',
        ]);

        $response->assertStatus(409)
            ->assertJson(['error' => 'Channel already exists']);
    }

    public function test_can_get_live_channels(): void
    {
        // Create a live channel
        $channel = MonitoredChannel::factory()->create(['is_live' => true]);
        LiveStream::factory()->create([
            'monitored_channel_id' => $channel->id,
            'status' => 'live',
        ]);

        // Create an offline channel
        MonitoredChannel::factory()->create(['is_live' => false]);

        $response = $this->getJson('/api/channels/live');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.count'));
    }

    public function test_can_get_offline_channels(): void
    {
        MonitoredChannel::factory()->count(2)->create(['is_live' => false, 'is_active' => true]);
        MonitoredChannel::factory()->create(['is_live' => true, 'is_active' => true]);

        $response = $this->getJson('/api/channels/offline');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.count'));
    }

    public function test_can_update_channel_active_status(): void
    {
        $channel = MonitoredChannel::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/channels/{$channel->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('monitored_channels', [
            'id' => $channel->id,
            'is_active' => false,
        ]);
    }

    public function test_can_delete_channel(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $response = $this->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('monitored_channels', [
            'id' => $channel->id,
        ]);
    }

    public function test_can_show_single_channel(): void
    {
        $channel = MonitoredChannel::factory()->create();

        $response = $this->getJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'youtube_channel_id',
                    'channel_name',
                    'is_active',
                    'is_live',
                ],
            ]);
    }
}
