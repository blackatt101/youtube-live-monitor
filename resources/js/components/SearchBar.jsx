import { useState, useEffect } from 'react';

const FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'live', label: 'Live' },
    { value: 'offline', label: 'Offline' },
    { value: 'monitoring_on', label: 'Active' },
    { value: 'monitoring_off', label: 'Paused' },
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
        <div className="flex gap-3">
            <div className="relative flex-1">
                <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#717171]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    type="text"
                    value={localSearch}
                    onChange={(e) => setLocalSearch(e.target.value)}
                    placeholder="Search channels..."
                    className="w-full pl-10 pr-4 py-2 text-sm bg-[#121212] border border-[#3f3f3f] text-white placeholder-[#717171] rounded-full focus:outline-none focus:border-[#3ea6ff] focus:bg-[#1a1a1a]"
                />
            </div>
            <select
                value={filter}
                onChange={(e) => onFilterChange(e.target.value)}
                className="px-4 py-2 text-sm bg-[#212121] border border-[#3f3f3f] text-white rounded-full focus:outline-none focus:border-[#3ea6ff] cursor-pointer"
            >
                {FILTERS.map((f) => (
                    <option key={f.value} value={f.value} className="bg-[#212121]">{f.label}</option>
                ))}
            </select>
            <select
                value={sortBy}
                onChange={(e) => onSortChange(e.target.value)}
                className="px-4 py-2 text-sm bg-[#212121] border border-[#3f3f3f] text-white rounded-full focus:outline-none focus:border-[#3ea6ff] cursor-pointer"
            >
                {SORT_OPTIONS.map((s) => (
                    <option key={s.value} value={s.value} className="bg-[#212121]">{s.label}</option>
                ))}
            </select>
        </div>
    );
}
