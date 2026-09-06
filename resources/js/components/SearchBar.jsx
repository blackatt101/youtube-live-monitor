import { useState, useEffect } from 'react';

const FILTERS = [
    { value: 'all', label: 'All', icon: 'M4 6h16M4 12h16M4 18h16' },
    { value: 'live', label: 'Live', icon: 'M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z' },
    { value: 'offline', label: 'Offline', icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' },
    { value: 'monitoring_on', label: 'Active', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { value: 'monitoring_off', label: 'Paused', icon: 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const SORT_OPTIONS = [
    { value: 'status', label: 'Status' },
    { value: 'name', label: 'Name' },
    { value: 'last_checked', label: 'Last Checked' },
    { value: 'last_live', label: 'Last Live' },
];

export default function SearchBar({ search, onSearchChange, filter, onFilterChange, sortBy, onSortChange }) {
    const [localSearch, setLocalSearch] = useState(search);

    useEffect(() => {
        const timer = setTimeout(() => {
            onSearchChange(localSearch);
        }, 150);
        return () => clearTimeout(timer);
    }, [localSearch, onSearchChange]);

    return (
        <div className="flex flex-col sm:flex-row gap-3">
            {/* Search Input - Glass morphism style */}
            <div className="relative flex-1 group">
                {/* Glow effect on focus */}
                <div className="absolute -inset-0.5 bg-gradient-to-r from-blue-500/50 via-purple-500/50 to-pink-500/50 rounded-full opacity-0 group-focus-within:opacity-100 blur transition-opacity duration-300" />
                <div className="relative">
                    <svg className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 group-focus-within:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="text"
                        value={localSearch}
                        onChange={(e) => setLocalSearch(e.target.value)}
                        placeholder="Search channels..."
                        className="w-full pl-12 pr-4 py-3 text-sm bg-[#1a1a2e]/80 backdrop-blur-md border border-[#2a2a4a] text-white placeholder-gray-500 rounded-2xl focus:outline-none focus:border-blue-500/50 focus:bg-[#1a1a2e] transition-all duration-300"
                    />
                    {localSearch && (
                        <button
                            onClick={() => setLocalSearch('')}
                            className="absolute right-4 top-1/2 -translate-y-1/2 p-1 text-gray-500 hover:text-white transition-colors"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    )}
                </div>
            </div>

            {/* Filter Dropdown */}
            <div className="relative group">
                <div className="absolute -inset-0.5 bg-gradient-to-r from-emerald-500/50 to-teal-500/50 rounded-xl opacity-0 group-focus-within:opacity-100 blur transition-opacity duration-300" />
                <div className="relative flex items-center">
                    <select
                        value={filter}
                        onChange={(e) => onFilterChange(e.target.value)}
                        className="appearance-none pl-4 pr-10 py-3 text-sm bg-[#1a1a2e]/80 backdrop-blur-md border border-[#2a2a4a] text-white rounded-xl focus:outline-none focus:border-emerald-500/50 cursor-pointer hover:bg-[#1a1a2e] transition-all duration-300"
                    >
                        {FILTERS.map((f) => (
                            <option key={f.value} value={f.value} className="bg-[#1a1a2e]">{f.label}</option>
                        ))}
                    </select>
                    <svg className="absolute right-3 w-4 h-4 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            {/* Sort Dropdown */}
            <div className="relative group">
                <div className="absolute -inset-0.5 bg-gradient-to-r from-orange-500/50 to-amber-500/50 rounded-xl opacity-0 group-focus-within:opacity-100 blur transition-opacity duration-300" />
                <div className="relative flex items-center">
                    <select
                        value={sortBy}
                        onChange={(e) => onSortChange(e.target.value)}
                        className="appearance-none pl-4 pr-10 py-3 text-sm bg-[#1a1a2e]/80 backdrop-blur-md border border-[#2a2a4a] text-white rounded-xl focus:outline-none focus:border-orange-500/50 cursor-pointer hover:bg-[#1a1a2e] transition-all duration-300"
                    >
                        {SORT_OPTIONS.map((s) => (
                            <option key={s.value} value={s.value} className="bg-[#1a1a2e]">{s.label}</option>
                        ))}
                    </select>
                    <svg className="absolute right-3 w-4 h-4 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                </div>
            </div>
        </div>
    );
}
