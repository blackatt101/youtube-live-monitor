<?php

namespace Database\Factories;

use App\Models\MonitoredChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoredChannel>
 */
class MonitoredChannelFactory extends Factory
{
    protected $model = MonitoredChannel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handle = $this->faker->unique()->userName;

        return [
            'youtube_channel_id' => 'UC' . $this->faker->regexify('[a-zA-Z0-9_-]{21}'),
            'channel_name' => $handle,
            'channel_url' => "https://www.youtube.com/@{$handle}",
            'channel_thumbnail' => $this->faker->imageUrl(100, 100),
            'is_active' => true,
            'is_live' => false,
            'last_checked_at' => null,
            'last_live_at' => null,
        ];
    }

    /**
     * Indicate that the channel is currently live.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_live' => true,
            'last_live_at' => now(),
        ]);
    }

    /**
     * Indicate that the channel is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
