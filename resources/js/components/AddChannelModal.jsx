import { useState, useEffect, useRef } from 'react';

export default function AddChannelModal({ isOpen, onClose, onAdd, loading, error }) {
    const [channelInput, setChannelInput] = useState('');
    const [validationError, setValidationError] = useState('');
    const inputRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            setChannelInput('');
            setValidationError('');
            setTimeout(() => inputRef.current?.focus(), 100);
        }
    }, [isOpen]);

    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && isOpen) onClose();
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [isOpen, onClose]);

    const validateInput = (input) => {
        const trimmed = input.trim();
        if (!trimmed) return 'Enter a channel URL, @handle, or channel ID';
        const isUrl = trimmed.startsWith('http');
        const isHandle = trimmed.startsWith('@');
        const isChannelId = trimmed.startsWith('UC') && trimmed.length >= 24;
        if (!isUrl && !isHandle && !isChannelId) {
            return 'Use @handle, channel URL, or UC... ID';
        }
        return null;
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const err = validateInput(channelInput);
        if (err) {
            setValidationError(err);
            return;
        }
        onAdd(channelInput.trim());
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
            {/* Backdrop with blur */}
            <div
                className="fixed inset-0 bg-black/70 backdrop-blur-md"
                onClick={onClose}
            />

            {/* Modal */}
            <div className="relative w-full max-w-md animate-modalIn">
                {/* Glow effect */}
                <div className="absolute -inset-1 bg-gradient-to-r from-blue-500/20 via-purple-500/20 to-pink-500/20 rounded-3xl blur-xl opacity-75" />

                <div className="relative bg-gradient-to-b from-[#252545] to-[#1a1a2e] rounded-2xl border border-[#3a3a5a] shadow-2xl overflow-hidden">
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-5 border-b border-[#3a3a5a]/50">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-pink-500 flex items-center justify-center shadow-lg shadow-red-500/30">
                                <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">Subscribe to Channel</h3>
                                <p className="text-xs text-gray-400">Get notified when they go live</p>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="p-2 text-gray-400 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="p-6">
                        <div className="mb-6">
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Channel URL, @handle, or ID
                            </label>
                            <div className="relative group">
                                <div className="absolute -inset-0.5 bg-gradient-to-r from-blue-500/50 to-purple-500/50 rounded-xl opacity-0 group-focus-within:opacity-100 blur transition-opacity duration-300" />
                                <div className="relative">
                                    <input
                                        ref={inputRef}
                                        type="text"
                                        value={channelInput}
                                        onChange={(e) => {
                                            setChannelInput(e.target.value);
                                            setValidationError('');
                                        }}
                                        placeholder="@username"
                                        className={`w-full px-4 py-3.5 text-sm bg-[#1a1a2e]/80 border text-white placeholder-gray-500 rounded-xl focus:outline-none transition-all duration-300 ${
                                            validationError || error
                                                ? 'border-red-500/50 focus:border-red-500'
                                                : 'border-[#3a3a5a] focus:border-blue-500/50'
                                        }`}
                                        disabled={loading}
                                    />
                                </div>
                            </div>
                            {(validationError || error) && (
                                <p className="mt-2 text-xs text-red-400 flex items-center gap-1.5">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {validationError || error}
                                </p>
                            )}

                            {/* Input hints */}
                            <div className="mt-3 flex flex-wrap gap-2">
                                <span className="px-2 py-1 text-[10px] bg-[#2a2a4a] text-gray-400 rounded-full">
                                    @handle
                                </span>
                                <span className="px-2 py-1 text-[10px] bg-[#2a2a4a] text-gray-400 rounded-full">
                                    youtube.com/channel/UC...
                                </span>
                                <span className="px-2 py-1 text-[10px] bg-[#2a2a4a] text-gray-400 rounded-full">
                                    youtube.com/@username
                                </span>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-5 py-2.5 text-sm text-gray-400 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200"
                                disabled={loading}
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading || !channelInput.trim()}
                                className="relative px-6 py-2.5 text-sm font-semibold text-white rounded-xl overflow-hidden disabled:opacity-50 transition-all duration-200"
                            >
                                {/* Button gradient background */}
                                <div className="absolute inset-0 bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600" />
                                {/* Shine effect */}
                                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700" />
                                <span className="relative flex items-center gap-2">
                                    {loading ? (
                                        <>
                                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            Adding...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Subscribe
                                        </>
                                    )}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
