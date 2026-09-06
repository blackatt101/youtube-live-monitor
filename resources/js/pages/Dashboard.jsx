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
    } = useChannels({ search, filter, sortBy, autoRefresh: true, refreshInterval: 45000 });

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
        // Just refresh data from database (scheduler handles detection)
        await refresh(false);
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
        // If no startedAt provided, show nothing
        if (!startedAt) return null;

        // Parse the date - handle both ISO8601 and other formats
        let startTime;
        try {
            startTime = new Date(startedAt);
            // Check if valid
            if (isNaN(startTime.getTime())) {
                return null;
            }
        } catch (e) {
            return null;
        }

        // Calculate difference in seconds
        const diff = Math.floor((currentTime - startTime) / 1000);

        // If diff is negative or zero, show 0:00
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
        <div className="min-h-screen bg-[#0a0a0f] relative overflow-hidden">
            {/* Animated background gradient */}
            <div className="fixed inset-0 pointer-events-none">
                <div className="absolute top-0 left-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-[128px] animate-pulse" />
                <div className="absolute top-1/3 right-1/4 w-80 h-80 bg-purple-500/10 rounded-full blur-[128px] animate-pulse" style={{ animationDelay: '1s' }} />
                <div className="absolute bottom-1/4 left-1/3 w-72 h-72 bg-pink-500/10 rounded-full blur-[128px] animate-pulse" style={{ animationDelay: '2s' }} />
            </div>

            {/* Header */}
            <header className="sticky top-0 z-50 bg-[#0a0a0f]/80 backdrop-blur-xl border-b border-[#1a1a2e]">
                <div className="px-4 py-3 flex items-center justify-between">
                    {/* Logo & Tabs */}
                    <div className="flex items-center gap-4">
                        {/* Animated Logo */}
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 via-pink-500 to-purple-500 flex items-center justify-center shadow-lg shadow-red-500/30">
                                    <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
                                    </svg>
                                </div>
                                {/* Pulsing dot */}
                                <span className="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-[#0a0a0f]">
                                    <span className="absolute inset-0 rounded-full bg-green-500 animate-ping opacity-75" />
                                </span>
                            </div>
                            <span className="hidden sm:block text-white font-bold text-lg">Live<span className="text-gradient bg-gradient-to-r from-red-400 to-pink-400 bg-clip-text text-transparent">Monitor</span></span>
                        </div>

                        {/* Tab Buttons */}
                        <div className="flex items-center gap-1 p-1 bg-[#12121a] rounded-xl">
                            <button
                                onClick={() => setActiveTab('live')}
                                className={`relative px-4 py-2 text-sm font-medium rounded-lg transition-all duration-300 ${
                                    activeTab === 'live'
                                        ? 'text-white'
                                        : 'text-gray-400 hover:text-white'
                                }`}
                            >
                                {activeTab === 'live' && (
                                    <span className="absolute inset-0 bg-gradient-to-r from-red-500/20 to-pink-500/20 rounded-lg border border-red-500/30" />
                                )}
                                <span className="relative flex items-center gap-2">
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="4" className="animate-pulse" />
                                    </svg>
                                    For You
                                    {liveChannels.length > 0 && (
                                        <span className="ml-1 px-1.5 py-0.5 text-[10px] bg-red-500 rounded-full animate-pulse">
                                            {liveChannels.length}
                                        </span>
                                    )}
                                </span>
                            </button>
                            <button
                                onClick={() => setActiveTab('channels')}
                                className={`relative px-4 py-2 text-sm font-medium rounded-lg transition-all duration-300 ${
                                    activeTab === 'channels'
                                        ? 'text-white'
                                        : 'text-gray-400 hover:text-white'
                                }`}
                            >
                                {activeTab === 'channels' && (
                                    <span className="absolute inset-0 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg border border-blue-500/30" />
                                )}
                                <span className="relative flex items-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                    </svg>
                                    Subscriptions
                                </span>
                            </button>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-2">
                        {/* Last Updated Indicator */}
                        {lastUpdated && (
                            <span className="hidden sm:flex items-center gap-1.5 text-[11px] text-gray-500">
                                <span className="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse" />
                                Updated {formatLastUpdated()}
                            </span>
                        )}

                        <button
                            onClick={handleRefresh}
                            disabled={loading}
                            className="group p-2.5 text-gray-400 hover:text-white hover:bg-[#1a1a2e] rounded-xl transition-all duration-200 disabled:opacity-50"
                            title="Refresh"
                        >
                            <svg className={`w-5 h-5 ${loading ? 'animate-spin' : 'group-hover:rotate-180'} transition-transform duration-500`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        <button
                            onClick={() => setShowAddModal(true)}
                            className="group relative px-4 py-2.5 text-sm font-semibold text-white rounded-xl overflow-hidden"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600" />
                            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700" />
                            <span className="relative flex items-center gap-2">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Subscribe
                            </span>
                        </button>
                    </div>
                </div>
            </header>

            {/* Content */}
            <main className="relative px-4 py-6">
                {/* Error State */}
                {error && channels.length === 0 && (
                    <div className="mb-6 bg-gradient-to-r from-red-500/10 to-pink-500/10 border border-red-500/30 rounded-2xl p-5">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center">
                                <svg className="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm text-red-400 font-medium">Unable to load channels</p>
                                <button
                                    onClick={refresh}
                                    className="text-xs text-red-300 hover:text-red-200 hover:underline mt-0.5"
                                >
                                    Retry
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* LIVE Tab - Enhanced YouTube Style Grid */}
                {activeTab === 'live' && (
                    <div>
                        {/* Stats Bar */}
                        <div className="mb-8">
                            <StatsBar stats={stats} loading={loading} />
                        </div>

                        {/* Loading */}
                        {loading && liveChannels.length === 0 && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-4 gap-y-10">
                                {[...Array(10)].map((_, i) => (
                                    <div key={i} className="animate-pulse" style={{ animationDelay: `${i * 50}ms` }}>
                                        <div className="aspect-video bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-2xl mb-3 overflow-hidden">
                                            <div className="w-full h-full bg-gradient-to-r from-transparent via-white/5 to-transparent animate-shimmer" />
                                        </div>
                                        <div className="flex gap-3">
                                            <div className="w-11 h-11 bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-full" />
                                            <div className="flex-1 space-y-2">
                                                <div className="h-4 bg-gradient-to-r from-[#1a1a2e] to-[#12121a] rounded-lg w-full" />
                                                <div className="h-3 bg-gradient-to-r from-[#1a1a2e] to-[#12121a] rounded-lg w-2/3" />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Live Streams Grid - Enhanced YouTube Style */}
                        {!loading && liveChannels.length > 0 && (
                            <div className="mb-8">
                                <div className="flex items-center gap-3 mb-6">
                                    <h2 className="text-xl font-bold text-white">Live Now</h2>
                                    <span className="px-3 py-1 text-xs font-semibold bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-full shadow-lg shadow-red-500/30">
                                        {liveChannels.length} {liveChannels.length === 1 ? 'stream' : 'streams'}
                                    </span>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-4 gap-y-10">
                                    {liveChannels.map((channel, index) => {
                                        const stream = channel.live_streams?.[0];
                                        if (!stream) return null;

                                        return (
                                            <a
                                                key={channel.id}
                                                href={`https://youtube.com/watch?v=${stream.youtube_video_id}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="group block"
                                                style={{ animationDelay: `${index * 50}ms` }}
                                            >
                                                {/* Thumbnail - Enhanced with glow effect */}
                                                <div className="relative w-full aspect-video bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-2xl overflow-hidden mb-3 shadow-xl shadow-black/30 transition-all duration-300 group-hover:shadow-2xl group-hover:shadow-red-500/10 group-hover:-translate-y-1">
                                                    <img
                                                        src={stream.thumbnail || `https://i.ytimg.com/vi/${stream.youtube_video_id}/maxresdefault.jpg`}
                                                        alt={stream.title}
                                                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                        loading="lazy"
                                                        onError={(e) => {
                                                            e.target.src = `https://i.ytimg.com/vi/${stream.youtube_video_id}/hqdefault.jpg`;
                                                        }}
                                                    />

                                                    {/* Gradient overlay */}
                                                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />

                                                    {/* LIVE Badge - Enhanced with glow */}
                                                    <div className="absolute top-3 left-3">
                                                        <div className="relative">
                                                            <span className="relative px-2 py-1 bg-gradient-to-r from-red-600 to-red-500 text-white text-xs font-bold rounded-md shadow-lg shadow-red-500/50 flex items-center gap-1">
                                                                <span className="w-2 h-2 bg-white rounded-full animate-pulse" />
                                                                LIVE
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Viewer Count - Enhanced */}
                                                    {stream.viewer_count && (
                                                        <div className="absolute top-3 right-3">
                                                            <span className="px-2 py-1 bg-black/80 backdrop-blur-sm text-white text-xs font-medium rounded-md flex items-center gap-1">
                                                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                                                </svg>
                                                                {formatViewers(stream.viewer_count)}
                                                            </span>
                                                        </div>
                                                    )}

                                                    {/* Duration */}
                                                    <div className="absolute bottom-3 right-3">
                                                        <span className="px-2 py-1 bg-black/80 backdrop-blur-sm text-white text-xs font-medium rounded-md">
                                                            {formatDuration(stream.started_at)}
                                                        </span>
                                                    </div>

                                                    {/* Play button overlay */}
                                                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                        <div className="w-16 h-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center border-2 border-white/50">
                                                            <svg className="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M8 5v14l11-7z"/>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Channel Info - Enhanced YouTube style */}
                                                <div className="flex gap-3">
                                                    {channel.channel_thumbnail ? (
                                                        <div className="relative flex-shrink-0">
                                                            <img
                                                                src={channel.channel_thumbnail}
                                                                alt=""
                                                                className="w-11 h-11 rounded-full ring-2 ring-[#2a2a4a] group-hover:ring-red-500/50 transition-all duration-300"
                                                                onError={(e) => {
                                                                    e.target.style.display = 'none';
                                                                }}
                                                            />
                                                            {channel.is_live && (
                                                                <span className="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-red-500 rounded-full border-2 border-[#0a0a0f]">
                                                                    <span className="absolute inset-0 rounded-full bg-red-500 animate-ping opacity-75" />
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="w-11 h-11 rounded-full bg-gradient-to-br from-[#2a2a4a] to-[#1a1a2a] flex-shrink-0" />
                                                    )}
                                                    <div className="flex-1 min-w-0">
                                                        <h3 className="text-sm font-semibold text-white line-clamp-2 leading-tight mb-1 group-hover:text-blue-400 transition-colors duration-200">
                                                            {stream.title || 'Live Stream'}
                                                        </h3>
                                                        <p className="text-xs text-gray-400 hover:text-white transition-colors">
                                                            {channel.channel_name}
                                                        </p>
                                                        <p className="text-xs text-gray-500 mt-0.5">
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
                            <div className="text-center py-16 relative">
                                {/* Animated background */}
                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div className="w-64 h-64 bg-gradient-to-r from-blue-500/5 to-purple-500/5 rounded-full blur-[100px]" />
                                </div>

                                <div className="relative">
                                    <div className="w-28 h-28 mx-auto mb-6 bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-full flex items-center justify-center border border-[#2a2a4a]">
                                        <svg className="w-14 h-14 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-xl font-semibold text-white mb-2">No live streams right now</h3>
                                    <p className="text-sm text-gray-400 max-w-sm mx-auto">We'll notify you when your subscribed channels go live. Stay tuned!</p>
                                </div>
                            </div>
                        )}

                        {/* Empty State */}
                        {!loading && channels.length === 0 && (
                            <div className="text-center py-16 relative">
                                {/* Animated background */}
                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div className="w-80 h-80 bg-gradient-to-r from-red-500/10 via-pink-500/10 to-purple-500/10 rounded-full blur-[120px] animate-pulse" />
                                </div>

                                <div className="relative">
                                    <div className="w-28 h-28 mx-auto mb-6 bg-gradient-to-br from-red-500/20 to-pink-500/20 rounded-full flex items-center justify-center border border-red-500/30">
                                        <svg className="w-14 h-14 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-xl font-semibold text-white mb-2">No subscribed channels</h3>
                                    <p className="text-sm text-gray-400 mb-6">Add channels to start monitoring their live streams</p>
                                    <button
                                        onClick={() => setShowAddModal(true)}
                                        className="group relative px-6 py-3 text-sm font-semibold text-white rounded-xl overflow-hidden inline-flex items-center gap-2"
                                    >
                                        <div className="absolute inset-0 bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600" />
                                        <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700" />
                                        <span className="relative flex items-center gap-2">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Subscribe to Channels
                                        </span>
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Channels Tab - Enhanced List View */}
                {activeTab === 'channels' && (
                    <div>
                        {/* Search & Filters */}
                        <div className="mb-6">
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
                            <div className="space-y-3">
                                {[...Array(5)].map((_, i) => (
                                    <div key={i} className="h-20 bg-gradient-to-r from-[#1e1e2e]/80 to-[#1a1a2a]/80 rounded-2xl animate-pulse" />
                                ))}
                            </div>
                        )}

                        {/* No Results */}
                        {!loading && channels.length === 0 && stats.total > 0 && (
                            <div className="text-center py-12">
                                <div className="w-20 h-20 mx-auto mb-4 bg-[#1a1a2e] rounded-full flex items-center justify-center border border-[#2a2a4a]">
                                    <svg className="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <p className="text-sm text-gray-400">No channels match your search</p>
                                <button
                                    onClick={() => { setSearch(''); setFilter('all'); }}
                                    className="text-sm text-blue-400 hover:text-blue-300 hover:underline mt-2"
                                >
                                    Clear filters
                                </button>
                            </div>
                        )}

                        {/* Channel List */}
                        {channels.length > 0 && (
                            <div className="space-y-3">
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
    return (
        <ToastProvider>
            <AppLayout>
                <DashboardInner />
            </AppLayout>
        </ToastProvider>
    );
}
