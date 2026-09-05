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
        <div className="group flex items-center gap-4 px-4 py-3 bg-[#212121] hover:bg-[#2a2a2a] rounded-lg transition-colors">
            {/* Thumbnail */}
            <div className="flex-shrink-0">
                {channel.channel_thumbnail ? (
                    <img
                        src={channel.channel_thumbnail}
                        alt=""
                        className="w-10 h-10 rounded-full"
                    />
                ) : (
                    <div className="w-10 h-10 rounded-full bg-[#3f3f3f]" />
                )}
            </div>

            {/* Channel Info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-white truncate">
                        {channel.channel_name}
                    </span>
                    {channel.is_live ? (
                        <span className="px-2 py-0.5 bg-red-600 text-white text-[10px] font-bold rounded">
                            LIVE
                        </span>
                    ) : (
                        <span className="text-[10px] text-[#aaaaaa]">Offline</span>
                    )}
                </div>
                {currentStream && (
                    <p className="text-xs text-[#aaaaaa] truncate">
                        {currentStream.title}
                        {currentStream.viewer_count && ` · ${formatViewers(currentStream.viewer_count)} watching`}
                    </p>
                )}
            </div>

            {/* Last Checked */}
            <div className="flex-shrink-0 text-right">
                <p className="text-xs text-[#717171]">
                    Checked {formatTime(channel.last_checked_at)}
                </p>
            </div>

            {/* Actions */}
            <div className="flex-shrink-0 flex items-center gap-1">
                <button
                    onClick={() => onCheck(channel.id)}
                    disabled={loading}
                    className="px-3 py-1.5 text-xs text-[#aaaaaa] hover:text-white hover:bg-[#3f3f3f] rounded transition-colors disabled:opacity-50"
                >
                    Check
                </button>
                <div className="relative" ref={menuRef}>
                    <button
                        onClick={() => setShowMenu(!showMenu)}
                        className="p-1.5 text-[#717171] hover:text-white hover:bg-[#3f3f3f] rounded transition-colors"
                    >
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="5" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="12" cy="19" r="2" />
                        </svg>
                    </button>
                    {showMenu && (
                        <div className="absolute right-0 mt-1 w-44 bg-[#2a2a2a] rounded-lg shadow-xl border border-[#3f3f3f] py-1 z-10">
                            <button
                                onClick={() => {
                                    onToggle(channel.id, !channel.is_active);
                                    setShowMenu(false);
                                }}
                                className="w-full px-4 py-2 text-left text-sm text-white hover:bg-[#3f3f3f]"
                            >
                                {channel.is_active ? 'Pause Monitoring' : 'Resume Monitoring'}
                            </button>
                            <div className="border-t border-[#3f3f3f] my-1" />
                            <button
                                onClick={() => {
                                    onDelete(channel.id);
                                    setShowMenu(false);
                                }}
                                className="w-full px-4 py-2 text-left text-sm text-red-500 hover:bg-[#3f3f3f]"
                            >
                                Unsubscribe
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
