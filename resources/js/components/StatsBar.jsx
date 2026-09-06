export default function StatsBar({ stats, loading }) {
    if (loading && !stats?.total) {
        return (
            <div className="flex flex-wrap gap-3">
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="px-5 py-3 bg-gradient-to-br from-[#1a1a2e] to-[#16213e] rounded-2xl animate-pulse border border-[#2a2a4a]">
                        <div className="h-2 w-16 bg-[#3f3f5f] rounded mb-2" />
                        <div className="h-6 w-10 bg-[#3f3f5f] rounded" />
                    </div>
                ))}
            </div>
        );
    }

    const statItems = [
        {
            label: 'Subscribed',
            value: stats.total || 0,
            icon: (
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
            ),
            color: 'from-blue-500 to-cyan-500',
            textColor: 'text-blue-400',
            glow: 'group-hover:shadow-blue-500/30'
        },
        {
            label: 'Live',
            value: stats.live || 0,
            icon: (
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="4" className="animate-pulse" />
                </svg>
            ),
            color: 'from-red-500 to-pink-500',
            textColor: 'text-red-400',
            glow: 'group-hover:shadow-red-500/30'
        },
        {
            label: 'Offline',
            value: stats.offline || 0,
            icon: (
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            ),
            color: 'from-gray-500 to-slate-500',
            textColor: 'text-gray-400',
            glow: ''
        },
        {
            label: 'Active',
            value: stats.monitoringOn || 0,
            icon: (
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            color: 'from-emerald-500 to-teal-500',
            textColor: 'text-emerald-400',
            glow: 'group-hover:shadow-emerald-500/30'
        },
    ];

    return (
        <div className="flex flex-wrap gap-3">
            {statItems.map((stat, i) => (
                <div
                    key={i}
                    className={`group px-5 py-3 bg-gradient-to-br from-[#1a1a2e] to-[#16213e] rounded-2xl border border-[#2a2a4a] hover:border-opacity-80 transition-all duration-300 hover:shadow-lg ${stat.glow} hover:-translate-y-0.5`}
                    style={{ animationDelay: `${i * 100}ms` }}
                >
                    <div className="flex items-center gap-2 mb-1">
                        <span className={`${stat.textColor} opacity-70 group-hover:opacity-100 transition-opacity`}>
                            {stat.icon}
                        </span>
                        <p className="text-[10px] text-gray-400 uppercase tracking-wider font-medium">
                            {stat.label}
                        </p>
                    </div>
                    <p className={`text-2xl font-bold bg-gradient-to-r ${stat.color} bg-clip-text text-transparent`}>
                        {stat.value}
                    </p>
                </div>
            ))}
        </div>
    );
}
