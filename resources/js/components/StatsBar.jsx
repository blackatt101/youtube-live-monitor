export default function StatsBar({ stats, loading }) {
    if (loading && !stats?.total) {
        return (
            <div className="flex gap-4">
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="px-4 py-2 bg-[#212121] rounded-lg animate-pulse">
                        <div className="h-2 w-16 bg-[#3f3f3f] rounded mb-2" />
                        <div className="h-5 w-8 bg-[#3f3f3f] rounded" />
                    </div>
                ))}
            </div>
        );
    }

    const statItems = [
        { label: 'Subscribed', value: stats.total || 0, color: 'text-white' },
        { label: 'Live', value: stats.live || 0, color: 'text-red-500' },
        { label: 'Offline', value: stats.offline || 0, color: 'text-[#aaaaaa]' },
        { label: 'Active', value: stats.monitoringOn || 0, color: 'text-green-500' },
    ];

    return (
        <div className="flex gap-4">
            {statItems.map((stat, i) => (
                <div key={i} className="px-4 py-2 bg-[#212121] rounded-lg">
                    <p className="text-[10px] text-[#aaaaaa] uppercase tracking-wide mb-0.5">
                        {stat.label}
                    </p>
                    <p className={`text-lg font-bold ${stat.color}`}>
                        {stat.value}
                    </p>
                </div>
            ))}
        </div>
    );
}
