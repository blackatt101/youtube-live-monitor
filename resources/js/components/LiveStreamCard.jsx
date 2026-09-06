import { useState, useEffect } from 'react';

export default function LiveStreamCard({ stream, onClick }) {
    const [isHovered, setIsHovered] = useState(false);

    const formatViewers = (count) => {
        if (count >= 1000000) {
            return (count / 1000000).toFixed(1) + 'M';
        }
        if (count >= 1000) {
            return (count / 1000).toFixed(1) + 'K';
        }
        return count.toString();
    };

    const formatDuration = (startedAt) => {
        const start = new Date(startedAt);
        const now = new Date();
        const diff = Math.floor((now - start) / 1000);

        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    };

    return (
        <div
            className="group relative bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-2xl overflow-hidden shadow-xl shadow-black/20 transition-all duration-300 hover:shadow-2xl hover:shadow-red-500/10 hover:-translate-y-1 cursor-pointer"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
            onClick={onClick}
        >
            {/* Thumbnail */}
            <div className="relative aspect-video bg-gradient-to-br from-[#1a1a2e] to-[#0f0f1a]">
                <img
                    src={stream.thumbnail}
                    alt={stream.title}
                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                    onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextSibling.style.display = 'flex';
                    }}
                />
                <div className="absolute inset-0 bg-gradient-to-br from-[#1a1a2e] to-[#0f0f1a] hidden items-center justify-center">
                    <span className="text-gray-500">No thumbnail</span>
                </div>

                {/* Gradient overlay on hover */}
                <div className={`absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-opacity duration-300 ${isHovered ? 'opacity-100' : 'opacity-0'}`} />

                {/* Live Badge - Enhanced */}
                <div className="absolute top-3 left-3">
                    <div className="relative">
                        <span className="relative flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-red-600 to-red-500 text-white text-xs font-bold rounded-md shadow-lg shadow-red-500/50">
                            <span className="w-2 h-2 bg-white rounded-full animate-pulse" />
                            Live
                        </span>
                    </div>
                </div>

                {/* Viewer Count */}
                <div className="absolute top-3 right-3">
                    <span className="flex items-center gap-1 px-2 py-1 bg-black/80 backdrop-blur-sm text-white text-xs font-medium rounded-md">
                        <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                        {formatViewers(stream.viewer_count)}
                    </span>
                </div>

                {/* Duration */}
                <div className="absolute bottom-3 right-3">
                    <span className="px-2 py-1 bg-black/80 backdrop-blur-sm text-white text-xs font-medium rounded-md">
                        {formatDuration(stream.started_at)}
                    </span>
                </div>

                {/* Play button overlay */}
                <div className={`absolute inset-0 flex items-center justify-center transition-opacity duration-300 ${isHovered ? 'opacity-100' : 'opacity-0'}`}>
                    <div className="w-16 h-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center border-2 border-white/50 transform scale-90 group-hover:scale-100 transition-transform duration-300">
                        <svg className="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="p-4">
                {/* Channel Info */}
                <div className="flex items-center gap-3 mb-3">
                    <div className="relative">
                        <img
                            src={stream.channel_thumbnail}
                            alt={stream.channel_name}
                            className="w-10 h-10 rounded-full ring-2 ring-[#2a2a4a] group-hover:ring-red-500/50 transition-all duration-300"
                            onError={(e) => {
                                e.target.style.display = 'none';
                                e.target.nextSibling.style.display = 'flex';
                            }}
                        />
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#2a2a4a] to-[#1a1a2a] hidden items-center justify-center ring-2 ring-[#2a2a4a]">
                            <span className="text-gray-500 text-xs">CH</span>
                        </div>
                        {/* Live indicator dot */}
                        <span className="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-red-500 rounded-full border-2 border-[#0a0a0f]">
                            <span className="absolute inset-0 rounded-full bg-red-500 animate-ping opacity-75" />
                        </span>
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-white truncate group-hover:text-blue-400 transition-colors">{stream.channel_name}</p>
                        <p className="text-xs text-gray-500">Started {formatDuration(stream.started_at)} ago</p>
                    </div>
                </div>

                {/* Title */}
                <h3 className="text-sm font-semibold text-white line-clamp-2 leading-tight mb-3 group-hover:text-gray-200 transition-colors">
                    {stream.title}
                </h3>

                {/* Watch Button */}
                <a
                    href={`https://youtube.com/watch?v=${stream.youtube_video_id}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="group/btn relative block w-full py-2.5 px-4 text-sm font-semibold text-white text-center rounded-xl overflow-hidden"
                >
                    <div className="absolute inset-0 bg-gradient-to-r from-red-600 to-pink-600" />
                    <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/btn:translate-x-full transition-transform duration-700" />
                    <span className="relative flex items-center justify-center gap-2">
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Now
                    </span>
                </a>
            </div>
        </div>
    );
}
