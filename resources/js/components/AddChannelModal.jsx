import { useState, useEffect, useRef } from 'react';

export default function AddChannelModal({ isOpen, onClose, onAdd, loading, error }) {
    const [channelInput, setChannelInput] = useState('');
    const [validationError, setValidationError] = useState('');
    const inputRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            setChannelInput('');
            setValidationError('');
            setTimeout(() => inputRef.current?.focus(), 50);
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
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/80" onClick={onClose} />
            <div className="relative w-full max-w-md bg-[#212121] rounded-xl border border-[#3f3f3f] shadow-2xl">
                <div className="flex items-center justify-between px-5 py-4 border-b border-[#3f3f3f]">
                    <h3 className="text-base font-medium text-white">Subscribe to Channel</h3>
                    <button onClick={onClose} className="text-[#717171] hover:text-white">
                        <span className="text-xl">×</span>
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="p-5">
                    <div className="mb-4">
                        <input
                            ref={inputRef}
                            type="text"
                            value={channelInput}
                            onChange={(e) => {
                                setChannelInput(e.target.value);
                                setValidationError('');
                            }}
                            placeholder="@username or channel URL"
                            className={`w-full px-4 py-3 text-sm bg-[#121212] border rounded-lg text-white placeholder-[#717171] focus:outline-none focus:border-[#3ea6ff] ${
                                validationError || error ? 'border-red-500' : 'border-[#3f3f3f]'
                            }`}
                            disabled={loading}
                        />
                        {(validationError || error) && (
                            <p className="mt-2 text-xs text-red-500">
                                {validationError || error}
                            </p>
                        )}
                    </div>
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm text-[#aaaaaa] hover:text-white"
                            disabled={loading}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={loading || !channelInput.trim()}
                            className="px-5 py-2 text-sm font-medium text-black bg-[#3ea6ff] hover:bg-[#2d95e8] rounded-lg disabled:opacity-50"
                        >
                            {loading ? 'Adding...' : 'Subscribe'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
