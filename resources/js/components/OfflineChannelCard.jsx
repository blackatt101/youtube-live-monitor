export default function OfflineChannelCard({ channel }) {
    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
            <div className="flex items-center gap-3">
                <img
                    src={channel.channel_thumbnail}
                    alt={channel.channel_name}
                    className="w-12 h-12 rounded-full"
                    onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextSibling.style.display = 'flex';
                    }}
                />
                <div className="w-12 h-12 rounded-full bg-gray-200 hidden items-center justify-center">
                    <span className="text-gray-500 text-sm">CH</span>
                </div>
                <div className="min-w-0 flex-1">
                    <h4 className="text-sm font-medium text-gray-900 truncate">{channel.channel_name}</h4>
                    <p className="text-xs text-gray-500">Offline</p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-3 h-3 bg-gray-300 rounded-full"></span>
                </div>
            </div>
        </div>
    );
}
