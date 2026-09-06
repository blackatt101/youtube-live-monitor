import { useState } from 'react';

export default function AppLayout({ children }) {
    return (
        <div className="min-h-screen bg-[#0a0a0f] text-white antialiased">
            {children}
        </div>
    );
}
