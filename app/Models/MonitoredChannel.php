<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredChannel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'channel_name',
        'channel_url',
        'channel_thumbnail',
        'is_active',
        'last_checked_at',
        'last_live_at',
        'is_live',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_live' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_live_at' => 'datetime',
    ];

    /**
     * Get the user that owns the monitored channel.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the live streams for this channel.
     */
    public function liveStreams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }

    /**
     * Get the current active live stream.
     */
    public function currentLiveStream(): ?LiveStream
    {
        return $this->liveStreams()
            ->where('status', LiveStream::STATUS_LIVE)
            ->latest('started_at')
            ->first();
    }

    /**
     * Check if channel is currently live.
     */
    public function isCurrentlyLive(): bool
    {
        return $this->is_live && $this->currentLiveStream() !== null;
    }

    /**
     * Get the YouTube channel URL.
     */
    public function getYouTubeUrlAttribute(): string
    {
        return "https://www.youtube.com/@{$this->channel_name}/live";
    }

    /**
     * Scope to get only active channels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only live channels.
     */
    public function scopeLive($query)
    {
        return $query->where('is_live', true);
    }

    /**
     * Scope to get channels not checked recently.
     */
    public function scopeNeedsChecking($query, int $minutes = 5)
    {
        return $query->where(function ($q) use ($minutes) {
            $q->where('last_checked_at', '<', now()->subMinutes($minutes))
              ->orWhereNull('last_checked_at');
        });
    }

    /**
     * Set channel name from URL or handle.
     */
    public static function extractHandleFromInput(string $input): string
    {
        // If it's a URL, extract the handle
        if (str_starts_with($input, 'http')) {
            if (preg_match('/youtube\.com\/@([^/\s]+)/', $input, $m)) {
                return $m[1];
            }
            if (preg_match('/youtube\.com\/channel\/([^/\s]+)/', $input, $m)) {
                return $m[1];
            }
        }

        // Otherwise, strip @ prefix
        return ltrim($input, '@');
    }
}
