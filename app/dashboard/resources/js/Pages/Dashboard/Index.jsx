import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function DashboardIndex({ user, workspaces, campaigns, documents, hasCompletedOnboarding, usage }) {
    useEffect(() => {
        if (!hasCompletedOnboarding) {
            router.visit('/onboarding');
        }
    }, [hasCompletedOnboarding]);

    if (!hasCompletedOnboarding) {
        return null;
    }

    return (
        <>
            <Head title="Dashboard — LaunchPilot" />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                {workspaces.length > 0 ? workspaces[0].name : 'Workspace'}
                            </span>
                        </div>
                        <div className="flex items-center gap-4">
                            {usage && usage.tier === 'free' && usage.remaining >= 0 && (
                                <span className="text-xs rounded-full bg-amber-50 px-2.5 py-1 text-amber-700 font-medium">
                                    {usage.remaining}/10 runs today
                                </span>
                            )}
                            {usage && usage.tier === 'pro' && (
                                <span className="text-xs rounded-full bg-green-50 px-2.5 py-1 text-green-700 font-medium">
                                    Pro
                                </span>
                            )}
                            <span className="text-sm text-muted">{user?.name}</span>
                            <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink">Knowledge Base</Link>
                            <Link href="/settings" className="text-sm text-muted hover:text-ink">Settings</Link>
                            <a href="/logout" className="text-sm font-semibold text-muted hover:text-ink">
                                Log out
                            </a>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10">
                    {/* Knowledge Base Summary */}
                    <section className="mb-10">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg font-bold">Knowledge Base</h2>
                                <p className="text-sm text-muted">Context powering your AI agents</p>
                            </div>
                            <Link href="/knowledge-base" className="text-sm font-medium text-ink hover:underline">
                                View all →
                            </Link>
                        </div>

                        {documents.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-line bg-white p-6">
                                <p className="text-sm text-muted">No documents yet. <Link href="/onboarding" className="text-ink font-medium hover:underline">Add your website</Link> to get started.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                {documents.slice(0, 3).map((doc) => {
                                    const meta = JSON.parse(doc.metadata || '{}');
                                    return (
                                        <Link
                                            key={doc.id}
                                            href={`/knowledge-base/${doc.id}`}
                                            className="rounded-lg border border-line bg-white p-4 hover:border-ink/30 transition-colors"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="text-lg">{doc.source_url ? '🌐' : '📄'}</span>
                                                <div className="min-w-0">
                                                    <h3 className="text-sm font-bold truncate">{doc.original_name || 'Untitled'}</h3>
                                                    {meta.title && (
                                                        <p className="text-xs text-muted truncate">{meta.title}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    {/* Campaigns */}
                    <section>
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg font-bold">Campaigns</h2>
                                <p className="text-sm text-muted">Your active marketing initiatives</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/campaigns" className="text-sm font-medium text-muted hover:text-ink">
                                    View all →
                                </Link>
                                <Link href="/campaigns/create" className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90">
                                    + New campaign
                                </Link>
                            </div>
                        </div>

                        {campaigns.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-16">
                                <div className="text-4xl mb-4">📋</div>
                                <h3 className="text-base font-bold">No campaigns yet</h3>
                                <p className="mt-1 text-sm text-muted">Create your first campaign to start planning your marketing.</p>
                                <Link href="/campaigns/create" className="mt-6 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90 inline-block">
                                    Create your first campaign
                                </Link>
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {campaigns.map((campaign) => (
                                    <Link href={`/campaigns/${campaign.id}`} key={campaign.id} className="rounded-xl border border-line bg-white p-5 hover:border-ink/30 transition-colors block">
                                        <div className="flex items-center justify-between">
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                campaign.status === 'active' ? 'bg-green-50 text-green-700' :
                                                campaign.status === 'completed' ? 'bg-slate-100 text-slate-700' :
                                                'bg-amber-50 text-amber-700'
                                            }`}>
                                                {campaign.status}
                                            </span>
                                        </div>
                                        <h3 className="mt-3 text-base font-bold">{campaign.title}</h3>
                                        {campaign.goal && <p className="mt-1 text-xs text-muted">{campaign.goal}</p>}
                                        <div className="mt-3 flex flex-wrap gap-1">
                                            {(campaign.channels || []).map((channel) => (
                                                <span key={channel} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                    {channel}
                                                </span>
                                            ))}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}
