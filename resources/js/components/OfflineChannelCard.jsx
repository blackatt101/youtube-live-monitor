export default function OfflineChannelCard({ channel, onClick }) {
    return (
        <div
            className="group relative bg-gradient-to-br from-[#1a1a2e] to-[#12121a] rounded-2xl overflow-hidden shadow-lg shadow-black/20 transition-all duration-300 hover:shadow-xl hover:shadow-blue-500/5 hover:-translate-y-0.5 cursor-pointer border border-transparent hover:border-[#2a2a4a]"
            onClick={onClick}
        >
            <div className="p-4">
                <div className="flex items-center gap-3">
                    <img
                        src={channel.channel_thumbnail}
                        alt={channel.channel_name}
                        className="w-12 h-12 rounded-full ring-2 ring-[#2a2a4a] group-hover:ring-[#3a3a5a] transition-all duration-300"
                        onError={(e) => {
                            e.target.style.display = 'none';
                            e.target.nextSibling.style.display = 'flex';
                        }}
                    />
                    <div className="w-12 h-12 rounded-full bg-gradient-to-br from-[#2a2a4a] to-[#1a1a2a] hidden items-center justify-center ring-2 ring-[#2a2a4a]">
                        <span className="text-gray-500 text-xs">CH</span>
                    </div>
                    <div className="min-w-0 flex-1">
                        <h4 className="text-sm font-semibold text-white truncate group-hover:text-blue-400 transition-colors">{channel.channel_name}</h4>
                        <p className="text-xs text-gray-500 flex items-center gap-1.5 mt-0.5">
                            <span className="w-1.5 h-1.5 bg-gray-500 rounded-full" />
                            Offline
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="w-8 h-8 flex items-center justify-center rounded-xl bg-[#1a1a2e] group-hover:bg-[#2a2a4a] transition-colors">
                            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
