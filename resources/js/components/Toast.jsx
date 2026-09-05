import { useState, createContext, useContext, useCallback } from 'react';

const ToastContext = createContext(null);

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) throw new Error('useToast must be used within ToastProvider');
    return context;
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback((message, type = 'success', duration = 3000) => {
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
                <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
                    {toasts.map((toast) => (
                        <Toast key={toast.id} toast={toast} onRemove={removeToast} />
                    ))}
                </div>
            )}
        </ToastContext.Provider>
    );
}

function Toast({ toast, onRemove }) {
    const getIcon = () => {
        switch (toast.type) {
            case 'success':
                return <span className="text-green-500">✓</span>;
            case 'error':
                return <span className="text-red-500">✕</span>;
            case 'warning':
                return <span className="text-yellow-500">⚠</span>;
            default:
                return <span className="text-blue-500">ℹ</span>;
        }
    };

    return (
        <div className="bg-[#282828] rounded-lg px-4 py-3 flex items-center gap-3 shadow-xl animate-slideUp">
            {getIcon()}
            <p className="text-sm text-white">{toast.message}</p>
            <button
                onClick={() => onRemove(toast.id)}
                className="text-[#717171] hover:text-white ml-2"
            >
                <span className="text-lg">×</span>
            </button>
        </div>
    );
}
