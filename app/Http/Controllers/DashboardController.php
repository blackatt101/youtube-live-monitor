<?php

namespace App\Http\Controllers;

use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with live streams and offline channels.
     */
    public function index(Request $request)
    {
        // Get monitored channels for the current user (or all if no auth)
        $query = MonitoredChannel::query();

        // If user is authenticated, filter by their channels
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $channels = $query->where('is_active', true)->get();

        // Get live streams for these channels
        $channelIds = $channels->pluck('id')->toArray();
        $liveStreamsQuery = LiveStream::whereIn('monitored_channel_id', $channelIds)
            ->where('status', 'live')
            ->with('monitoredChannel');

        // Get IDs of channels that are currently live
        $liveChannelIds = (clone $liveStreamsQuery)
            ->pluck('monitored_channel_id')
            ->toArray();

        // Get live streams data
        $liveStreams = $liveStreamsQuery
            ->get()
            ->map(function ($stream) {
                return [
                    'id' => $stream->id,
                    'monitored_channel_id' => $stream->monitored_channel_id,
                    'youtube_video_id' => $stream->youtube_video_id,
                    'title' => $stream->title,
                    'thumbnail' => $stream->thumbnail,
                    'channel_name' => $stream->monitoredChannel->channel_name,
                    'channel_thumbnail' => $stream->monitoredChannel->channel_thumbnail,
                    'viewer_count' => $stream->viewer_count,
                    'started_at' => $stream->started_at->toIso8601String(),
                    'status' => $stream->status,
                ];
            });

        // Get offline channels (channels without active live streams)
        $offlineChannels = $channels->reject(function ($channel) use ($liveChannelIds) {
            return in_array($channel->id, $liveChannelIds);
        })->map(function ($channel) {
            return [
                'id' => $channel->id,
                'youtube_channel_id' => $channel->youtube_channel_id,
                'channel_name' => $channel->channel_name,
                'channel_thumbnail' => $channel->channel_thumbnail,
                'channel_url' => $channel->channel_url,
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'liveStreams' => $liveStreams,
            'offlineChannels' => $offlineChannels,
        ]);
    }
}
