import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Components/AppShell';

export default function SettingsIndex({ settings, gsc_configured }) {
    const [apiKey, setApiKey] = useState('');
    const [customPrompts, setCustomPrompts] = useState({ social: '', content: '', seo: '', brainstorm: '' });
    const [saved, setSaved] = useState(false);

    const handleSaveApiKey = async () => {
        const res = await fetch('/settings/api-key', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ api_key: apiKey }),
        });
        const data = await res.json();
        if (data.success) {
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } else {
            alert(data.error);
        }
    };

    const handleSavePrompts = async () => {
        const res = await fetch('/settings/custom-prompts', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompts: customPrompts }),
        });
        const data = await res.json();
        if (data.success) {
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } else {
            alert(data.error);
        }
    };

    return (
        <>
            <Head title="Settings — LaunchPilot" />
            <AppShell>
                <Link href="/dashboard" className="text-sm text-muted hover:text-ink transition-colors inline-flex items-center gap-1 mb-4">
                    ← Back to Dashboard
                </Link>

                <h1 className="text-2xl font-bold mb-6">Settings</h1>

                <div className="max-w-2xl space-y-5">
                    {/* Tier */}
                    <div className="rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1">
                        <h2 className="text-sm font-bold mb-3">Plan</h2>
                        <div className="flex items-center gap-3">
                            <span className={`text-sm font-semibold px-3 py-1 rounded-full border ${settings.tier === 'pro' ? 'bg-green-50 text-green-700 border-green-100' : 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                {settings.tier === 'pro' ? 'Pro' : 'Free'}
                            </span>
                            {settings.tier === 'free' && (
                                <span className="text-sm text-muted">{settings.remaining_runs} agent runs remaining today</span>
                            )}
                        </div>
                        {settings.tier === 'free' && (
                            <p className="mt-3 text-sm text-muted">
                                Upgrade to Pro for unlimited runs, custom agents, BYOK, and multi-workspace support.
                            </p>
                        )}
                    </div>

                    {/* Export */}
                    <div className="rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1">
                        <h2 className="text-sm font-bold mb-2">Export Knowledge Base</h2>
                        <p className="text-sm text-muted mb-4">Download all your documents and generated content as a Markdown file.</p>
                        <a
                            href="/settings/export"
                            className="inline-block rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5"
                        >
                            Export Markdown
                        </a>
                    </div>

                    {/* GSC */}
                    <div className="rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1">
                        <h2 className="text-sm font-bold mb-3">Google Search Console</h2>
                        {settings.has_gsc ? (
                            <div>
                                <p className="text-sm text-green-700 mb-3 flex items-center gap-1">
                                    <span>✓</span> Connected since {new Date(settings.gsc_connected_at).toLocaleDateString()}
                                </p>
                                <form action="/settings/gsc/disconnect" method="POST">
                                    <button type="submit" className="text-sm text-red-600 hover:text-red-800 underline transition-colors">
                                        Disconnect
                                    </button>
                                </form>
                            </div>
                        ) : (
                            <div>
                                <p className="text-sm text-muted mb-3">Connect your Google Search Console account for SEO insights.</p>
                                {gsc_configured ? (
                                    <a href="/settings/gsc/connect" className="inline-block rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5">
                                        Connect GSC
                                    </a>
                                ) : (
                                    <p className="text-sm text-amber-600">Not configured by administrator.</p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* BYOK */}
                    <div className={`rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1 ${settings.tier !== 'pro' ? 'opacity-50' : ''}`}>
                        <h2 className="text-sm font-bold mb-2">OpenRouter API Key</h2>
                        <p className="text-sm text-muted mb-3">Bring your own key for unlimited agent runs.</p>
                        <div className="flex gap-2">
                            <input
                                type="password"
                                value={apiKey}
                                onChange={(e) => setApiKey(e.target.value)}
                                placeholder={settings.has_custom_api_key ? '••••••••' : 'sk-or-v1-...'}
                                disabled={settings.tier !== 'pro'}
                                className="flex-1 rounded-lg border border-line bg-paper px-4 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all disabled:bg-paper/50"
                            />
                            <button
                                onClick={handleSaveApiKey}
                                disabled={settings.tier !== 'pro'}
                                className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                            >
                                Save
                            </button>
                        </div>
                    </div>

                    {/* Custom Prompts */}
                    <div className={`rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1 ${settings.tier !== 'pro' ? 'opacity-50' : ''}`}>
                        <h2 className="text-sm font-bold mb-2">Custom Agent Prompts</h2>
                        <p className="text-sm text-muted mb-4">Override the default system prompts for each agent.</p>
                        {['social', 'content', 'seo', 'brainstorm'].map((agent) => (
                            <div key={agent} className="mb-4">
                                <label className="block text-xs font-semibold uppercase tracking-wider text-muted mb-1 capitalize">{agent} Agent</label>
                                <textarea
                                    value={customPrompts[agent]}
                                    onChange={(e) => setCustomPrompts({ ...customPrompts, [agent]: e.target.value })}
                                    disabled={settings.tier !== 'pro'}
                                    placeholder="Custom system prompt..."
                                    rows={3}
                                    className="w-full rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all disabled:bg-paper/50"
                                />
                            </div>
                        ))}
                        <button
                            onClick={handleSavePrompts}
                            disabled={settings.tier !== 'pro'}
                            className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                        >
                            Save Prompts
                        </button>
                    </div>
                </div>

                {saved && (
                    <div className="fixed bottom-6 right-6 rounded-lg bg-green-600 px-4 py-2 text-sm font-bold text-white shadow-elevation-3 animate-in">
                        Saved!
                    </div>
                )}
            </AppShell>
        </>
    );
}
