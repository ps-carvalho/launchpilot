import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import AppShell from '../../Components/AppShell';

export default function DashboardIndex({ user, workspaces, campaigns, documents, hasCompletedOnboarding, usage }) {
    useEffect(() => {
        if (!hasCompletedOnboarding) {
            window.location.href = '/onboarding';
        }
    }, [hasCompletedOnboarding]);

    if (!hasCompletedOnboarding) {
        return null;
    }

    const tierBadge = usage?.tier === 'pro'
        ? <span className="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-600 border border-green-100">Pro</span>
        : <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-600 border border-amber-100">{usage?.remaining ?? 10}/10 runs</span>;

    return (
        <>
            <Head title="Dashboard — LaunchPilot" />
            <AppShell>
                {/* Page Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    <p className="mt-1 text-sm text-muted">Welcome back, {user?.name?.split(' ')[0] || 'there'}.</p>
                </div>

                {/* Stats Row */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                        <div className="text-2xl font-extrabold">{campaigns.length}</div>
                        <div className="text-xs text-muted font-medium mt-1">Campaigns</div>
                    </div>
                    <div className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                        <div className="text-2xl font-extrabold">{documents.length}</div>
                        <div className="text-xs text-muted font-medium mt-1">Documents</div>
                    </div>
                    <div className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                        <div className="text-2xl font-extrabold">{usage?.daily_runs_used ?? 0}</div>
                        <div className="text-xs text-muted font-medium mt-1">Runs today</div>
                    </div>
                    <div className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                        <div className="text-2xl font-extrabold">{workspaces[0]?.name || '—'}</div>
                        <div className="text-xs text-muted font-medium mt-1">Workspace</div>
                    </div>
                </div>

                {/* Campaigns Preview */}
                <section className="mb-8">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h2 className="text-lg font-bold">Campaigns</h2>
                            <p className="text-sm text-muted">Your active marketing initiatives</p>
                        </div>
                        <div className="flex items-center gap-3">
                            {tierBadge}
                            <Link href="/campaigns" className="text-sm font-medium text-muted hover:text-ink transition-colors">
                                View all →
                            </Link>
                        </div>
                    </div>

                    {campaigns.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-14 shadow-elevation-1">
                            <div className="text-4xl mb-4">📋</div>
                            <h3 className="text-base font-bold">No campaigns yet</h3>
                            <p className="mt-1 text-sm text-muted">Create your first campaign to start planning your marketing.</p>
                            <Link href="/campaigns/create" className="mt-5 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 inline-block">
                                Create your first campaign
                            </Link>
                        </div>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {campaigns.slice(0, 3).map((campaign) => (
                                <Link
                                    href={`/campaigns/${campaign.id}`}
                                    key={campaign.id}
                                    className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-1 block"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                            campaign.status === 'active' ? 'bg-green-50 text-green-600 border border-green-100' :
                                            campaign.status === 'completed' ? 'bg-slate-100 text-slate-600 border border-slate-200' :
                                            'bg-amber-50 text-amber-600 border border-amber-100'
                                        }`}>
                                            {campaign.status}
                                        </span>
                                    </div>
                                    <h3 className="mt-3 text-base font-bold">{campaign.title}</h3>
                                    {campaign.goal && <p className="mt-1 text-xs text-muted line-clamp-2">{campaign.goal}</p>}
                                    <div className="mt-3 flex flex-wrap gap-1">
                                        {(() => {
                                            const ch = typeof campaign.channels === 'string'
                                                ? JSON.parse(campaign.channels || '[]')
                                                : (campaign.channels || []);
                                            return ch.slice(0, 3).map((channel) => (
                                                <span key={channel} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                                    {channel}
                                                </span>
                                            ));
                                        })()}
                                    </div>
                                </Link>
                            ))}
                            <Link
                                href="/campaigns/create"
                                className="rounded-xl bg-white p-5 border border-dashed border-line flex flex-col items-center justify-center text-center min-h-[140px] hover:border-accent/40 hover:bg-blue-50/30 transition-all group"
                            >
                                <div className="text-2xl mb-2 text-muted group-hover:text-accent transition-colors">+</div>
                                <span className="text-sm font-semibold text-muted group-hover:text-accent transition-colors">New Campaign</span>
                            </Link>
                        </div>
                    )}
                </section>

                {/* Knowledge Base Preview */}
                <section>
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h2 className="text-lg font-bold">Knowledge Base</h2>
                            <p className="text-sm text-muted">Context powering your AI agents</p>
                        </div>
                        <Link href="/knowledge-base" className="text-sm font-medium text-muted hover:text-ink transition-colors">
                            View all →
                        </Link>
                    </div>

                    {documents.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-line bg-white p-5 shadow-elevation-1">
                            <p className="text-sm text-muted">
                                No documents yet.{" "}
                                <Link href="/onboarding" className="font-medium text-ink hover:text-accent transition-colors">Add your website</Link>{" "}
                                to get started.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            {documents.slice(0, 3).map((doc) => {
                                const meta = JSON.parse(doc.metadata || '{}');
                                return (
                                    <Link
                                        key={doc.id}
                                        href={`/knowledge-base/${doc.id}`}
                                        className="rounded-xl bg-white p-4 border border-line/60 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 flex items-center gap-3"
                                    >
                                        <span className="text-xl flex-shrink-0">{doc.source_url ? '🌐' : '📄'}</span>
                                        <div className="min-w-0 flex-1">
                                            <h3 className="text-sm font-bold truncate">{doc.original_name || 'Untitled'}</h3>
                                            {meta.title && (
                                                <p className="text-xs text-muted truncate">{meta.title}</p>
                                            )}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </section>
            </AppShell>
        </>
    );
}
