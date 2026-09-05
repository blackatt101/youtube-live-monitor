<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStream extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'monitored_channel_id',
        'youtube_video_id',
        'title',
        'thumbnail',
        'started_at',
        'ended_at',
        'viewer_count',
        'status',
        'detected_at',
        'detection_method',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'detected_at' => 'datetime',
        'viewer_count' => 'integer',
    ];

    /**
     * Status constants
     */
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';

    /**
     * Get the monitored channel that this stream belongs to.
     */
    public function monitoredChannel(): BelongsTo
    {
        return $this->belongsTo(MonitoredChannel::class);
    }

    /**
     * Check if the stream is currently live.
     */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Check if the stream has ended.
     */
    public function hasEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    /**
     * Get the YouTube watch URL.
     */
    public function getWatchUrlAttribute(): string
    {
        return "https://youtube.com/watch?v={$this->youtube_video_id}";
    }

    /**
     * Get the YouTube thumbnail URL (high quality).
     */
    public function getThumbnailUrlAttribute(): string
    {
        return "https://i.ytimg.com/vi/{$this->youtube_video_id}/maxresdefault.jpg";
    }

    /**
     * Scope to get only live streams.
     */
    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    /**
     * Scope to get ended streams.
     */
    public function scopeEnded($query)
    {
        return $query->where('status', self::STATUS_ENDED);
    }

    /**
     * Scope to get streams detected within a time window.
     */
    public function scopeRecentlyDetected($query, int $minutes = 5)
    {
        return $query->where('detected_at', '>=', now()->subMinutes($minutes));
    }
}
