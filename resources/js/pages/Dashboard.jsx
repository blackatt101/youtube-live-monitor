import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useChannels, useChannelActions } from '@/hooks/useChannels';
import StatsBar from '@/components/StatsBar';
import SearchBar from '@/components/SearchBar';
import ChannelRow from '@/components/ChannelRow';
import AddChannelModal from '@/components/AddChannelModal';
import { ToastProvider, useToast } from '@/components/Toast';

function DashboardInner() {
    const { success, error: showError } = useToast();

    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [sortBy, setSortBy] = useState('status');
    const [showAddModal, setShowAddModal] = useState(false);
    const [activeTab, setActiveTab] = useState('live'); // 'live' or 'channels'
    const [currentTime, setCurrentTime] = useState(new Date()); // For live duration updates

    const {
        channels,
        loading,
        error,
        lastUpdated,
        stats,
        refresh
    } = useChannels({ search, filter, sortBy, autoRefresh: true, refreshInterval: 45000, forceRefreshOnMount: true });

    // Update current time every second for live duration display
    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    const {
        loading: actionLoadingState,
        addChannel,
        toggleMonitoring,
        deleteChannel,
        checkChannel
    } = useChannelActions();

    const handleRefresh = async () => {
        await refresh(true); // Force detection on manual refresh
    };

    const handleAddChannel = async (channelInput) => {
        try {
            await addChannel(channelInput);
            setShowAddModal(false);
            success('Channel added');
        } catch (err) {
            showError(err.message);
            throw err;
        }
    };

    const handleToggle = async (id, isActive) => {
        try {
            await toggleMonitoring(id, isActive);
            success(isActive ? 'Monitoring on' : 'Monitoring off');
        } catch (err) {
            showError(err.message);
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteChannel(id);
            success('Channel removed');
        } catch (err) {
            showError(err.message);
        }
    };

    const handleCheck = async (id) => {
        try {
            await checkChannel(id);
            success('Channel checked');
        } catch (err) {
            showError(err.message);
        }
    };

    const formatLastUpdated = () => {
        if (!lastUpdated) return null;
        const diff = Math.floor((new Date() - lastUpdated) / 1000);
        if (diff < 5) return 'Just now';
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        return lastUpdated.toLocaleTimeString();
    };

    // Separate live and offline channels
    const liveChannels = channels.filter(c => c.is_live);

    // Format duration for display (HH:MM:SS format like YouTube)
    const formatDuration = (startedAt) => {
        if (!startedAt) return null;
        const diff = Math.floor((currentTime - new Date(startedAt)) / 1000);
        if (diff < 0) return '0:00';
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;

        if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        return `${m}:${String(s).padStart(2, '0')}`;
    };

    // Format viewer count
    const formatViewers = (count) => {
        if (!count && count !== 0) return null;
        if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
        if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
        return count?.toLocaleString() || null;
    };

    return (
        <div className="min-h-screen bg-[#0f0f0f]">
            {/* Header */}
            <header className="sticky top-0 z-50 bg-[#0f0f0f]/95 backdrop-blur-sm border-b border-[#3f3f3f]">
                <div className="px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setActiveTab('live')}
                            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                                activeTab === 'live'
                                    ? 'bg-[#272727] text-white'
                                    : 'text-[#aaaaaa] hover:text-white hover:bg-[#272727]/50'
                            }`}
                        >
                            For You
                        </button>
                        <button
                            onClick={() => setActiveTab('channels')}
                            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                                activeTab === 'channels'
                                    ? 'bg-[#272727] text-white'
                                    : 'text-[#aaaaaa] hover:text-white hover:bg-[#272727]/50'
                            }`}
                        >
                            Subscriptions
                        </button>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleRefresh}
                            disabled={loading}
                            className="p-2 text-[#aaaaaa] hover:text-white hover:bg-[#272727]/50 rounded-full transition-colors disabled:opacity-50"
                            title="Refresh"
                        >
                            <svg className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                        <button
                            onClick={() => setShowAddModal(true)}
                            className="px-4 py-2 text-sm font-medium text-black bg-[#f1f1f1] hover:bg-[#e5e5e5] rounded-lg transition-colors"
                        >
                            + Subscribe
                        </button>
                    </div>
                </div>
            </header>

            {/* Content */}
            <main className="px-4 py-4">
                {/* Error State */}
                {error && channels.length === 0 && (
                    <div className="bg-[#212121] rounded-lg p-4 mb-4">
                        <p className="text-sm text-[#ff4444]">Unable to load channels</p>
                        <button
                            onClick={refresh}
                            className="text-sm text-[#3ea6ff] hover:underline mt-1"
                        >
                            Retry
                        </button>
                    </div>
                )}

                {/* LIVE Tab - YouTube Style Grid */}
                {activeTab === 'live' && (
                    <div>
                        {/* Stats Bar */}
                        <div className="mb-6">
                            <StatsBar stats={stats} loading={loading} />
                        </div>

                        {/* Loading */}
                        {loading && liveChannels.length === 0 && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                {[...Array(10)].map((_, i) => (
                                    <div key={i} className="animate-pulse">
                                        <div className="aspect-video bg-[#272727] rounded-xl mb-3" />
                                        <div className="flex gap-3">
                                            <div className="w-9 h-9 bg-[#272727] rounded-full" />
                                            <div className="flex-1">
                                                <div className="h-4 bg-[#272727] rounded w-full mb-2" />
                                                <div className="h-3 bg-[#272727] rounded w-2/3" />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Live Streams Grid - YouTube Style */}
                        {!loading && liveChannels.length > 0 && (
                            <div className="mb-6">
                                <h2 className="text-lg font-bold text-white mb-4">Live Now</h2>
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-4 gap-y-10">
                                    {liveChannels.map((channel) => {
                                        const stream = channel.live_streams?.[0];
                                        if (!stream) return null;

                                        return (
                                            <a
                                                key={channel.id}
                                                href={`https://youtube.com/watch?v=${stream.youtube_video_id}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="group block"
                                            >
                                                {/* Thumbnail - 16:9 aspect ratio like YouTube */}
                                                <div className="relative w-full aspect-video bg-[#272727] rounded-lg overflow-hidden mb-3">
                                                    <img
                                                        src={stream.thumbnail || `https://i.ytimg.com/vi/${stream.youtube_video_id}/maxresdefault.jpg`}
                                                        alt={stream.title}
                                                        className="w-full h-full object-cover"
                                                        loading="lazy"
                                                        onError={(e) => {
                                                            e.target.src = `https://i.ytimg.com/vi/${stream.youtube_video_id}/hqdefault.jpg`;
                                                        }}
                                                    />
                                                    {/* LIVE Badge */}
                                                    <div className="absolute top-2 left-2">
                                                        <span className="px-1.5 py-0.5 bg-red-600 text-white text-xs font-medium rounded">
                                                            LIVE
                                                        </span>
                                                    </div>
                                                    {/* Duration */}
                                                    <div className="absolute bottom-2 right-2">
                                                        <span className="px-1.5 py-0.5 bg-black/80 text-white text-xs font-medium rounded">
                                                            {formatDuration(stream.started_at)}
                                                        </span>
                                                    </div>
                                                </div>

                                                {/* Channel Info - YouTube style */}
                                                <div className="flex gap-3">
                                                    {channel.channel_thumbnail ? (
                                                        <img
                                                            src={channel.channel_thumbnail}
                                                            alt=""
                                                            className="w-9 h-9 rounded-full flex-shrink-0 mt-0.5"
                                                            onError={(e) => {
                                                                e.target.style.display = 'none';
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="w-9 h-9 rounded-full bg-[#272727] flex-shrink-0 mt-0.5" />
                                                    )}
                                                    <div className="flex-1 min-w-0">
                                                        <h3 className="text-sm font-medium text-white line-clamp-2 leading-tight mb-0.5 group-hover:text-[#3ea6ff]">
                                                            {stream.title || 'Live Stream'}
                                                        </h3>
                                                        <p className="text-xs text-[#aaaaaa] hover:text-white">
                                                            {channel.channel_name}
                                                        </p>
                                                        <p className="text-xs text-[#aaaaaa]">
                                                            {stream.viewer_count ? `${formatViewers(stream.viewer_count)} watching` : 'Live now'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </a>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* No Live Streams */}
                        {!loading && liveChannels.length === 0 && channels.length > 0 && (
                            <div className="text-center py-12">
                                <div className="w-24 h-24 mx-auto mb-4 bg-[#272727] rounded-full flex items-center justify-center">
                                    <svg className="w-12 h-12 text-[#aaaaaa]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-medium text-white mb-2">No live streams right now</h3>
                                <p className="text-sm text-[#aaaaaa]">We'll notify you when your subscribed channels go live</p>
                            </div>
                        )}

                        {/* Empty State */}
                        {!loading && channels.length === 0 && (
                            <div className="text-center py-12">
                                <div className="w-24 h-24 mx-auto mb-4 bg-[#272727] rounded-full flex items-center justify-center">
                                    <svg className="w-12 h-12 text-[#aaaaaa]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-medium text-white mb-2">No subscribed channels</h3>
                                <p className="text-sm text-[#aaaaaa] mb-4">Add channels to start monitoring their live streams</p>
                                <button
                                    onClick={() => setShowAddModal(true)}
                                    className="px-6 py-2 text-sm font-medium text-black bg-[#3ea6ff] hover:bg-[#2d95e8] rounded-lg transition-colors"
                                >
                                    Subscribe to Channels
                                </button>
                            </div>
                        )}
                    </div>
                )}

                {/* Channels Tab - List View */}
                {activeTab === 'channels' && (
                    <div>
                        {/* Search & Filters */}
                        <div className="mb-4">
                            <SearchBar
                                search={search}
                                onSearchChange={setSearch}
                                filter={filter}
                                onFilterChange={setFilter}
                                sortBy={sortBy}
                                onSortChange={setSortBy}
                            />
                        </div>

                        {/* Loading */}
                        {loading && channels.length === 0 && (
                            <div className="space-y-2">
                                {[...Array(5)].map((_, i) => (
                                    <div key={i} className="h-16 bg-[#212121] rounded-lg animate-pulse" />
                                ))}
                            </div>
                        )}

                        {/* No Results */}
                        {!loading && channels.length === 0 && stats.total > 0 && (
                            <div className="text-center py-8">
                                <p className="text-sm text-[#aaaaaa]">No channels match your search</p>
                                <button
                                    onClick={() => { setSearch(''); setFilter('all'); }}
                                    className="text-sm text-[#3ea6ff] hover:underline mt-2"
                                >
                                    Clear filters
                                </button>
                            </div>
                        )}

                        {/* Channel List */}
                        {channels.length > 0 && (
                            <div className="space-y-2">
                                {channels.map((channel) => (
                                    <ChannelRow
                                        key={channel.id}
                                        channel={channel}
                                        onToggle={handleToggle}
                                        onDelete={handleDelete}
                                        onCheck={handleCheck}
                                        loading={actionLoadingState}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </main>

            <AddChannelModal
                isOpen={showAddModal}
                onClose={() => setShowAddModal(false)}
                onAdd={handleAddChannel}
                loading={actionLoadingState}
            />
        </div>
    );
}

export default function Dashboard() {
    const [showAddModal, setShowAddModal] = useState(false);

    return (
        <ToastProvider>
            <AppLayout>
                <DashboardInner />
            </AppLayout>
        </ToastProvider>
    );
}
