import { useState, useRef, useEffect } from 'react';

export default function ChannelRow({ channel, onToggle, onDelete, onCheck, loading }) {
    const [showMenu, setShowMenu] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const handleClick = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) {
                setShowMenu(false);
            }
        };
        if (showMenu) {
            document.addEventListener('click', handleClick);
            return () => document.removeEventListener('click', handleClick);
        }
    }, [showMenu]);

    const formatTime = (date) => {
        if (!date) return 'Never';
        const diff = Math.floor((new Date() - new Date(date)) / 1000);
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    };

    const formatViewers = (count) => {
        if (!count) return null;
        if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
        return count.toLocaleString();
    };

    const currentStream = channel.live_streams?.find(s => s.status === 'live');

    return (
        <div className="group relative overflow-hidden flex items-center gap-4 px-4 py-3 bg-gradient-to-r from-[#1e1e2e]/80 to-[#1a1a2a]/80 hover:from-[#2a2a4a]/80 hover:to-[#252540]/80 rounded-2xl transition-all duration-300 border border-transparent hover:border-[#3a3a5a]/50">
            {/* Animated border glow on hover */}
            <div className="absolute inset-0 rounded-2xl bg-gradient-to-r from-red-500/0 via-red-500/0 to-red-500/0 group-hover:via-red-500/10 transition-all duration-500 pointer-events-none" />

            {/* Live indicator bar */}
            {channel.is_live && (
                <div className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-red-500 via-pink-500 to-red-500 rounded-l-2xl animate-pulse" />
            )}

            {/* Thumbnail */}
            <div className="flex-shrink-0 relative">
                {channel.channel_thumbnail ? (
                    <div className="relative">
                        <img
                            src={channel.channel_thumbnail}
                            alt=""
                            className="w-12 h-12 rounded-full ring-2 ring-[#3a3a5a] group-hover:ring-[#5a5a7a] transition-all duration-300"
                        />
                        {channel.is_live && (
                            <span className="absolute -bottom-1 -right-1 w-4 h-4 bg-red-500 rounded-full border-2 border-[#0f0f0f] animate-pulse">
                                <span className="absolute inset-0 rounded-full bg-red-500 animate-ping opacity-75" />
                            </span>
                        )}
                    </div>
                ) : (
                    <div className="w-12 h-12 rounded-full bg-gradient-to-br from-[#2a2a4a] to-[#1a1a2a] flex items-center justify-center ring-2 ring-[#3a3a5a]" />
                )}
            </div>

            {/* Channel Info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-white truncate group-hover:text-transparent group-hover:bg-gradient-to-r group-hover:from-white group-hover:to-gray-300 group-hover:bg-clip-text transition-all duration-300">
                        {channel.channel_name}
                    </span>
                    {channel.is_live ? (
                        <span className="flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-red-600 to-pink-600 text-white text-[10px] font-bold rounded-full shadow-lg shadow-red-500/30 animate-pulse">
                            <span className="w-1.5 h-1.5 bg-white rounded-full" />
                            LIVE
                        </span>
                    ) : (
                        <span className="px-2.5 py-1 bg-[#2a2a3a] text-[10px] text-gray-400 rounded-full">
                            Offline
                        </span>
                    )}
                </div>
                {currentStream && (
                    <div className="flex items-center gap-2 mt-1">
                        <p className="text-xs text-gray-400 truncate max-w-[200px]">
                            {currentStream.title}
                        </p>
                        {currentStream.viewer_count && (
                            <>
                                <span className="text-gray-600">•</span>
                                <p className="text-xs text-gray-500 flex items-center gap-1">
                                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                    {formatViewers(currentStream.viewer_count)}
                                </p>
                            </>
                        )}
                    </div>
                )}
            </div>

            {/* Last Checked */}
            <div className="flex-shrink-0 text-right hidden sm:block">
                <p className="text-[11px] text-gray-500">
                    Checked {formatTime(channel.last_checked_at)}
                </p>
            </div>

            {/* Actions */}
            <div className="flex-shrink-0 flex items-center gap-1">
                <button
                    onClick={() => onCheck(channel.id)}
                    disabled={loading}
                    className="group/btn px-3 py-1.5 text-xs text-gray-400 hover:text-white bg-[#252540] hover:bg-[#3a3a5a] rounded-xl transition-all duration-200 disabled:opacity-50 flex items-center gap-1.5"
                >
                    <svg className="w-3.5 h-3.5 group-hover/btn:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Check
                </button>
                <div className="relative" ref={menuRef}>
                    <button
                        onClick={() => setShowMenu(!showMenu)}
                        className="p-2 text-gray-500 hover:text-white bg-[#252540] hover:bg-[#3a3a5a] rounded-xl transition-all duration-200"
                    >
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    {showMenu && (
                        <div className="absolute right-0 mt-2 w-48 bg-gradient-to-b from-[#2a2a4a] to-[#1a1a2a] rounded-xl shadow-2xl border border-[#3a3a5a] py-2 z-20 animate-fadeIn">
                            <div className="px-4 py-2 border-b border-[#3a3a5a]">
                                <p className="text-[10px] text-gray-500 uppercase tracking-wider">Channel Settings</p>
                            </div>
                            <button
                                onClick={() => {
                                    onToggle(channel.id, !channel.is_active);
                                    setShowMenu(false);
                                }}
                                className="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-[#3a3a5a] flex items-center gap-2 transition-colors"
                            >
                                {channel.is_active ? (
                                    <>
                                        <svg className="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Pause Monitoring
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Resume Monitoring
                                    </>
                                )}
                            </button>
                            <div className="border-t border-[#3a3a5a] my-1" />
                            <button
                                onClick={() => {
                                    onDelete(channel.id);
                                    setShowMenu(false);
                                }}
                                className="w-full px-4 py-2.5 text-left text-sm text-red-400 hover:bg-red-500/10 flex items-center gap-2 transition-colors"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Unsubscribe
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
