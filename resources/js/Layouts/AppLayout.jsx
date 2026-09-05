import { useState } from 'react';

export default function AppLayout({ children }) {
    return (
        <div className="min-h-screen bg-[#0f0f0f]">
            {children}
        </div>
    );
}
