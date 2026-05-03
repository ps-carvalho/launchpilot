import { Link, usePage } from '@inertiajs/react';

const NAV_ITEMS = [
    { href: '/dashboard', label: 'Dashboard', icon: '📊' },
    { href: '/campaigns', label: 'Campaigns', icon: '🎯' },
    { href: '/knowledge-base', label: 'Knowledge Base', icon: '📚' },
    { href: '/settings', label: 'Settings', icon: '⚙️' },
];

export default function AppShell({ children }) {
    const { url } = usePage();

    return (
        <div className="min-h-screen bg-paper">
            {/* Top Navigation */}
            <header className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-line shadow-elevation-1">
                <nav className="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 py-3">
                    <div className="flex items-center gap-8">
                        <Link href="/dashboard" className="flex items-center gap-2 text-lg font-bold tracking-tight hover:opacity-80 transition-opacity">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-ink text-white text-sm font-bold">LP</span>
                            <span className="hidden sm:inline">LaunchPilot</span>
                        </Link>
                        <div className="hidden md:flex items-center gap-1">
                            {NAV_ITEMS.map((item) => {
                                const isActive = url.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={`rounded-lg px-3 py-2 text-sm font-medium transition-all ${
                                            isActive
                                                ? 'bg-blue-50 text-blue-700'
                                                : 'text-muted hover:text-ink hover:bg-slate-50'
                                        }`}
                                    >
                                        <span className="mr-1.5">{item.icon}</span>
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <a
                            href="/logout"
                            className="rounded-lg px-3 py-2 text-sm font-medium text-muted hover:text-ink hover:bg-slate-50 transition-all"
                        >
                            Log out
                        </a>
                    </div>
                </nav>
            </header>

            {/* Mobile Navigation */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-line shadow-elevation-3">
                <div className="flex items-center justify-around px-2 py-2">
                    {NAV_ITEMS.map((item) => {
                        const isActive = url.startsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`flex flex-col items-center gap-0.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${
                                    isActive
                                        ? 'text-blue-600'
                                        : 'text-muted'
                                }`}
                            >
                                <span className="text-lg">{item.icon}</span>
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>

            {/* Main Content */}
            <main className="mx-auto max-w-7xl px-4 sm:px-6 py-6 pb-24 md:pb-6">
                {children}
            </main>
        </div>
    );
}
