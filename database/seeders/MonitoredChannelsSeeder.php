<?php

namespace Database\Seeders;

use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use App\Models\User;
use Illuminate\Database\Seeder;

class MonitoredChannelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // Real YouTube channels for testing
        // Mix of channels that are typically live (24/7 streams) and regular channels
        $channels = [
            [
                'youtube_channel_id' => 'UCSJ4gkVC6NrvII8umztf0Ow', // Lofi Girl
                'channel_name' => 'LofiGirl',
                'channel_url' => 'https://www.youtube.com/@LofiGirl',
                'channel_thumbnail' => 'https://yt3.googleusercontent.com/ytc/APkrFKY1MGrS9GsPjdWUBqS6t0I8i0nTj2r8F7VYJvk=s176-c-k-c0x00ffffff-no-rj',
            ],
            [
                'youtube_channel_id' => 'UC_gUO6VHmVXq6y7fmAKtcpQ', // SpaceX
                'channel_name' => 'SpaceX',
                'channel_url' => 'https://www.youtube.com/@SpaceX',
                'channel_thumbnail' => 'https://yt3.googleusercontent.com/ytc/APkrFKY1MGrS9GsPjdWUBqS6t0I8i0nTj2r8F7VYJvk=s176-c-k-c0x00ffffff-no-rj',
            ],
            [
                'youtube_channel_id' => 'UC乏', // NASA (placeholder - use real ID)
                'channel_name' => 'NASA',
                'channel_url' => 'https://www.youtube.com/@NASA',
                'channel_thumbnail' => null,
            ],
            [
                'youtube_channel_id' => 'UCTG26BCD5X5VvlllXjRskJA', // Deddy Corbuzier
                'channel_name' => 'DeddyCorbuzier',
                'channel_url' => 'https://www.youtube.com/@DeddyCorbuzier',
                'channel_thumbnail' => null,
            ],
            [
                'youtube_channel_id' => 'UCaXfD1VZBs7D1UWLNibU5QQ', // Indonesian gaming channel
                'channel_name' => 'BoommPotato',
                'channel_url' => 'https://www.youtube.com/@BoommPotato',
                'channel_thumbnail' => null,
            ],
            [
                'youtube_channel_id' => 'UCqA4u27GoV5m7l17rW9A_5g', // Linus Tech Tips
                'channel_name' => 'LinusTechTips',
                'channel_url' => 'https://www.youtube.com/@LinusTechTips',
                'channel_thumbnail' => null,
            ],
        ];

        foreach ($channels as $channelData) {
            MonitoredChannel::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'youtube_channel_id' => $channelData['youtube_channel_id'],
                ],
                [
                    'channel_name' => $channelData['channel_name'],
                    'channel_url' => $channelData['channel_url'],
                    'channel_thumbnail' => $channelData['channel_thumbnail'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Sample data seeded successfully!');
        $this->command->info("Created {$user->name} user with " . count($channels) . " monitored channels.");
        $this->command->info('Note: Run `php artisan monitor:check --sync` to detect live channels.');
    }
}
