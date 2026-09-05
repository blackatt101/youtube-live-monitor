<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\ChannelInfo;
use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Contracts\Services\LiveDetectionResult;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\MonitoredChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChannelController extends Controller
{
    public function __construct(
        private LiveDetectionProviderInterface $detector
    ) {}

    /**
     * List all monitored channels.
     */
    public function index(Request $request): JsonResponse
    {
        $forceRefresh = $request->boolean('refresh', false);

        $channels = MonitoredChannel::with(['liveStreams' => function ($query) {
            $query->where('status', 'live')->latest('started_at')->limit(1);
        }])->get();

        // If refresh is requested, trigger detection for each channel and update database
        if ($forceRefresh) {
            foreach ($channels as $channel) {
                $result = $this->detector->detect($channel->channel_name);
                $this->updateChannelFromDetection($channel, $result);
            }
            // Refresh the collection from database
            $channels = MonitoredChannel::with(['liveStreams' => function ($query) {
                $query->where('status', 'live')->latest('started_at')->limit(1);
            }])->get();
        }

        // Transform to include is_live status
        $data = $channels->map(function ($channel) {
            return [
                'id' => $channel->id,
                'youtube_channel_id' => $channel->youtube_channel_id,
                'channel_name' => $channel->channel_name,
                'channel_url' => $channel->channel_url,
                'channel_thumbnail' => $channel->channel_thumbnail,
                'is_active' => $channel->is_active,
                'is_live' => $channel->is_live,
                'last_checked_at' => $channel->last_checked_at?->toIso8601String(),
                'last_live_at' => $channel->last_live_at?->toIso8601String(),
                'live_streams' => $channel->liveStreams->map(function ($stream) {
                    return [
                        'id' => $stream->id,
                        'youtube_video_id' => $stream->youtube_video_id,
                        'title' => $stream->title,
                        'thumbnail' => $stream->thumbnail,
                        'viewer_count' => $stream->viewer_count,
                        'started_at' => $stream->started_at?->toIso8601String(),
                        'status' => $stream->status,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $channels->count(),
                'live_count' => $channels->where('is_live', true)->count(),
            ],
        ]);
    }

    /**
     * Update channel and streams from detection result
     */
    private function updateChannelFromDetection(MonitoredChannel $channel, LiveDetectionResult $result): void
    {
        $channel->update(['last_checked_at' => now()]);

        if ($result->status === LiveDetectionResult::STATUS_LIVE) {
            // Find existing active stream for this channel
            $existingStream = LiveStream::where('monitored_channel_id', $channel->id)
                ->where('status', LiveStream::STATUS_LIVE)
                ->where('youtube_video_id', $result->videoId)
                ->first();

            if ($existingStream) {
                // Update existing stream (title, viewer count, etc.)
                $existingStream->update([
                    'title' => $result->title ?? $existingStream->title,
                    'thumbnail' => $result->thumbnail ?? $existingStream->thumbnail,
                    'viewer_count' => $result->viewerCount,
                    'detected_at' => now(),
                ]);
            } else {
                // End any previous live streams for this channel
                LiveStream::where('monitored_channel_id', $channel->id)
                    ->where('status', LiveStream::STATUS_LIVE)
                    ->update([
                        'status' => LiveStream::STATUS_ENDED,
                        'ended_at' => now(),
                    ]);

                // Create new stream record
                LiveStream::create([
                    'monitored_channel_id' => $channel->id,
                    'youtube_video_id' => $result->videoId,
                    'title' => $result->title,
                    'thumbnail' => $result->thumbnail,
                    'started_at' => $result->startedAt ?? now(),
                    'viewer_count' => $result->viewerCount,
                    'status' => LiveStream::STATUS_LIVE,
                    'detected_at' => now(),
                ]);
            }

            // Update channel status
            $channel->update([
                'is_live' => true,
                'last_live_at' => now(),
            ]);
        } else {
            // Channel is offline - end all active live streams
            LiveStream::where('monitored_channel_id', $channel->id)
                ->where('status', LiveStream::STATUS_LIVE)
                ->update([
                    'status' => LiveStream::STATUS_ENDED,
                    'ended_at' => now(),
                ]);

            // Update channel status
            $channel->update(['is_live' => false]);
        }
    }

    /**
     * Add a new channel to monitor.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $handle = MonitoredChannel::extractHandleFromInput($request->input('channel'));

        // Check for duplicates
        $existing = MonitoredChannel::where('channel_name', $handle)->first();
        if ($existing) {
            return response()->json([
                'error' => 'Channel already exists',
                'channel' => $existing,
            ], 409);
        }

        // Validate channel exists on YouTube
        $channelInfo = $this->detector->validateChannel($handle);

        if ($channelInfo === null) {
            return response()->json([
                'error' => 'Channel not found on YouTube',
            ], 404);
        }

        // Create the monitored channel
        $channel = MonitoredChannel::create([
            'youtube_channel_id' => $channelInfo->channelId,
            'channel_name' => $channelInfo->handle,
            'channel_url' => $channelInfo->url ?? "https://www.youtube.com/@{$channelInfo->handle}",
            'channel_thumbnail' => $channelInfo->thumbnail,
            'is_active' => true,
            'is_live' => false,
        ]);

        return response()->json([
            'message' => 'Channel added successfully',
            'channel' => $channel,
        ], 201);
    }

    /**
     * Get a specific channel.
     */
    public function show(int $id): JsonResponse
    {
        $channel = MonitoredChannel::with(['liveStreams' => function ($query) {
            $query->where('status', 'live')->latest('started_at');
        }])->findOrFail($id);

        return response()->json(['data' => $channel]);
    }

    /**
     * Update a channel.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $channel = MonitoredChannel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('is_active')) {
            $channel->update(['is_active' => $request->boolean('is_active')]);
        }

        return response()->json([
            'message' => 'Channel updated',
            'channel' => $channel->fresh(),
        ]);
    }

    /**
     * Remove a channel from monitoring.
     */
    public function destroy(int $id): JsonResponse
    {
        $channel = MonitoredChannel::findOrFail($id);

        // Optionally keep historical stream data
        // LiveStream::where('monitored_channel_id', $channel->id)->delete();

        $channel->delete();

        return response()->json(['message' => 'Channel removed']);
    }

    /**
     * Manually trigger a check for a channel.
     */
    public function check(Request $request, int $id): JsonResponse
    {
        $channel = MonitoredChannel::findOrFail($id);

        $result = $this->detector->detect($channel->channel_name);

        // Update database with detection result
        $this->updateChannelFromDetection($channel, $result);

        return response()->json([
            'channel' => $channel->fresh()->load(['liveStreams' => function ($query) {
                $query->where('status', 'live')->latest('started_at')->limit(1);
            }]),
            'detection' => $result->toArray(),
        ]);
    }

    /**
     * Get live channels only.
     */
    public function live(): JsonResponse
    {
        $channels = MonitoredChannel::with(['liveStreams' => function ($query) {
            $query->where('status', 'live')->latest('started_at');
        }])
        ->where('is_live', true)
        ->get();

        return response()->json([
            'data' => $channels,
            'meta' => ['count' => $channels->count()],
        ]);
    }

    /**
     * Get offline channels only.
     */
    public function offline(): JsonResponse
    {
        $channels = MonitoredChannel::with(['liveStreams' => function ($query) {
            $query->where('status', 'live');
        }])
        ->where('is_active', true)
        ->where('is_live', false)
        ->get();

        return response()->json([
            'data' => $channels,
            'meta' => ['count' => $channels->count()],
        ]);
    }
}
