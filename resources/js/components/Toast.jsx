import { useState, createContext, useContext, useCallback } from 'react';

const ToastContext = createContext(null);

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) throw new Error('useToast must be used within ToastProvider');
    return context;
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback((message, type = 'success', duration = 3500) => {
        const id = Date.now() + Math.random();
        setToasts(prev => [...prev, { id, message, type }]);
        if (duration > 0) {
            setTimeout(() => {
                setToasts(prev => prev.filter(t => t.id !== id));
            }, duration);
        }
    }, []);

    const removeToast = useCallback((id) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    const success = useCallback((message) => addToast(message, 'success'), [addToast]);
    const error = useCallback((message) => addToast(message, 'error'), [addToast]);
    const warning = useCallback((message) => addToast(message, 'warning'), [addToast]);
    const info = useCallback((message) => addToast(message, 'info'), [addToast]);

    return (
        <ToastContext.Provider value={{ addToast, removeToast, success, error, warning, info }}>
            {children}
            {toasts.length > 0 && (
                <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] flex flex-col gap-3 pointer-events-none">
                    {toasts.map((toast) => (
                        <Toast key={toast.id} toast={toast} onRemove={removeToast} />
                    ))}
                </div>
            )}
        </ToastContext.Provider>
    );
}

function Toast({ toast, onRemove }) {
    const getConfig = () => {
        switch (toast.type) {
            case 'success':
                return {
                    icon: (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    ),
                    gradient: 'from-emerald-500/20 to-green-500/20',
                    border: 'border-emerald-500/50',
                    textColor: 'text-emerald-400',
                    glow: 'shadow-emerald-500/20'
                };
            case 'error':
                return {
                    icon: (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    ),
                    gradient: 'from-red-500/20 to-pink-500/20',
                    border: 'border-red-500/50',
                    textColor: 'text-red-400',
                    glow: 'shadow-red-500/20'
                };
            case 'warning':
                return {
                    icon: (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    ),
                    gradient: 'from-amber-500/20 to-orange-500/20',
                    border: 'border-amber-500/50',
                    textColor: 'text-amber-400',
                    glow: 'shadow-amber-500/20'
                };
            default:
                return {
                    icon: (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    ),
                    gradient: 'from-blue-500/20 to-cyan-500/20',
                    border: 'border-blue-500/50',
                    textColor: 'text-blue-400',
                    glow: 'shadow-blue-500/20'
                };
        }
    };

    const config = getConfig();

    return (
        <div className={`pointer-events-auto bg-gradient-to-r ${config.gradient} backdrop-blur-xl border ${config.border} rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-2xl ${config.glow} animate-slideUp`}>
            <span className={config.textColor}>
                {config.icon}
            </span>
            <p className="text-sm text-white font-medium">{toast.message}</p>
            <button
                onClick={() => onRemove(toast.id)}
                className="ml-2 text-gray-400 hover:text-white transition-colors p-1 hover:bg-white/10 rounded-lg"
            >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}
