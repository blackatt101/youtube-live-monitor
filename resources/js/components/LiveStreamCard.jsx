export default function LiveStreamCard({ stream }) {
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
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            {/* Thumbnail */}
            <div className="relative aspect-video bg-gray-200">
                <img
                    src={stream.thumbnail}
                    alt={stream.title}
                    className="w-full h-full object-cover"
                    onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextSibling.style.display = 'flex';
                    }}
                />
                <div className="absolute inset-0 bg-gray-300 hidden items-center justify-center">
                    <span className="text-gray-500">No thumbnail</span>
                </div>

                {/* Live Badge */}
                <div className="absolute top-3 left-3">
                    <span className="inline-flex items-center gap-1 px-2 py-1 bg-red-600 text-white text-xs font-bold uppercase rounded">
                        <span className="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                        Live
                    </span>
                </div>

                {/* Viewer Count */}
                <div className="absolute bottom-3 right-3">
                    <span className="inline-flex items-center gap-1 px-2 py-1 bg-black/75 text-white text-xs font-medium rounded">
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                        {formatViewers(stream.viewer_count)}
                    </span>
                </div>
            </div>

            {/* Content */}
            <div className="p-4">
                {/* Channel Info */}
                <div className="flex items-center gap-3 mb-3">
                    <img
                        src={stream.channel_thumbnail}
                        alt={stream.channel_name}
                        className="w-10 h-10 rounded-full"
                        onError={(e) => {
                            e.target.style.display = 'none';
                            e.target.nextSibling.style.display = 'flex';
                        }}
                    />
                    <div className="w-10 h-10 rounded-full bg-gray-200 hidden items-center justify-center">
                        <span className="text-gray-500 text-xs">CH</span>
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">{stream.channel_name}</p>
                        <p className="text-xs text-gray-500">Started {formatDuration(stream.started_at)} ago</p>
                    </div>
                </div>

                {/* Title */}
                <h3 className="text-sm font-semibold text-gray-900 line-clamp-2 mb-3">
                    {stream.title}
                </h3>

                {/* Watch Button */}
                <a
                    href={`https://youtube.com/watch?v=${stream.youtube_video_id}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block w-full py-2 px-4 bg-red-600 text-white text-sm font-medium text-center rounded-lg hover:bg-red-700 transition-colors"
                >
                    Watch Now
                </a>
            </div>
        </div>
    );
}
