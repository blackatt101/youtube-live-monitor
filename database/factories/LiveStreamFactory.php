<?php

namespace Database\Factories;

use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStream>
 */
class LiveStreamFactory extends Factory
{
    protected $model = LiveStream::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitored_channel_id' => MonitoredChannel::factory(),
            'youtube_video_id' => $this->faker->regexify('[a-zA-Z0-9_-]{11}'),
            'title' => $this->faker->sentence(4),
            'thumbnail' => $this->faker->imageUrl(640, 360),
            'started_at' => now()->subMinutes($this->faker->numberBetween(5, 120)),
            'ended_at' => null,
            'viewer_count' => $this->faker->numberBetween(10, 50000),
            'status' => LiveStream::STATUS_LIVE,
            'detected_at' => now(),
            'detection_method' => 'test',
        ];
    }

    /**
     * Indicate that the stream has ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LiveStream::STATUS_ENDED,
            'ended_at' => now(),
        ]);
    }

    /**
     * Indicate that the stream is currently live.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LiveStream::STATUS_LIVE,
            'ended_at' => null,
        ]);
    }
}
