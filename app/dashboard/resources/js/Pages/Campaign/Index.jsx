import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const TYPE_LABELS = {
    one_off: 'One-off Launch',
    recurring: 'Recurring Stream',
    ongoing: 'Ongoing Presence',
};

const TYPE_ICONS = {
    one_off: '🚀',
    recurring: '🔄',
    ongoing: '📡',
};

export default function CampaignIndex({ campaigns, filter }) {
    const [archivingId, setArchivingId] = useState(null);
    const [restoringId, setRestoringId] = useState(null);

    const handleArchive = async (id) => {
        setArchivingId(id);
        try {
            const res = await fetch(`/campaigns/${id}/archive`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                router.reload();
            }
        } catch (e) {
            alert('Failed to archive campaign.');
        } finally {
            setArchivingId(null);
        }
    };

    const handleRestore = async (id) => {
        setRestoringId(id);
        try {
            const res = await fetch(`/campaigns/${id}/restore`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                router.reload();
            }
        } catch (e) {
            alert('Failed to restore campaign.');
        } finally {
            setRestoringId(null);
        }
    };

    return (
        <>
            <Head title="Campaigns — LaunchPilot" />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                            <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink">Knowledge Base</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold">Campaigns</h1>
                            <p className="text-sm text-muted">Organize and manage your marketing initiatives</p>
                        </div>
                        <Link
                            href="/campaigns/create"
                            className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90"
                        >
                            + New campaign
                        </Link>
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-1 rounded-lg bg-slate-100 p-1 w-fit mb-6">
                        <Link
                            href="/campaigns?tab=active"
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${
                                filter === 'active'
                                    ? 'bg-white text-ink shadow-sm'
                                    : 'text-slate-600 hover:text-ink'
                            }`}
                        >
                            Active
                        </Link>
                        <Link
                            href="/campaigns?tab=archived"
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${
                                filter === 'archived'
                                    ? 'bg-white text-ink shadow-sm'
                                    : 'text-slate-600 hover:text-ink'
                            }`}
                        >
                            Archived
                        </Link>
                    </div>

                    {campaigns.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-16">
                            <div className="text-4xl mb-4">📋</div>
                            <h3 className="text-base font-bold">
                                {filter === 'archived' ? 'No archived campaigns' : 'No campaigns yet'}
                            </h3>
                            <p className="mt-1 text-sm text-muted">
                                {filter === 'archived'
                                    ? 'Archived campaigns will appear here.'
                                    : 'Create your first campaign to start planning your marketing.'}
                            </p>
                            {filter !== 'archived' && (
                                <Link
                                    href="/campaigns/create"
                                    className="mt-6 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90"
                                >
                                    Create your first campaign
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {campaigns.map((campaign) => (
                                <div
                                    key={campaign.id}
                                    className="rounded-xl border border-line bg-white p-5 hover:border-ink/30 transition-colors flex flex-col"
                                >
                                    <div className="flex items-center justify-between mb-3">
                                        <div className="flex items-center gap-2">
                                            <span className="text-lg">{TYPE_ICONS[campaign.type] || '📋'}</span>
                                            <span className="text-xs font-medium text-slate-500">
                                                {TYPE_LABELS[campaign.type] || campaign.type}
                                            </span>
                                        </div>
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                            campaign.status === 'active' ? 'bg-green-50 text-green-700' :
                                            campaign.status === 'completed' ? 'bg-slate-100 text-slate-700' :
                                            'bg-amber-50 text-amber-700'
                                        }`}>
                                            {campaign.status}
                                        </span>
                                    </div>

                                    <Link href={`/campaigns/${campaign.id}`} className="block group">
                                        <h3 className="text-base font-bold group-hover:text-ink/80">{campaign.title}</h3>
                                        {campaign.description && (
                                            <p className="mt-1 text-sm text-muted line-clamp-2">{campaign.description}</p>
                                        )}
                                    </Link>

                                    {campaign.goal && (
                                        <p className="mt-2 text-xs text-slate-500">Goal: {campaign.goal}</p>
                                    )}

                                    {(campaign.channels || []).length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-1">
                                            {(typeof campaign.channels === 'string'
                                                ? JSON.parse(campaign.channels)
                                                : campaign.channels
                                            ).map((channel) => (
                                                <span key={channel} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                    {channel}
                                                </span>
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-auto pt-4 flex items-center gap-3">
                                        <Link
                                            href={`/campaigns/${campaign.id}`}
                                            className="text-sm font-medium text-ink hover:underline"
                                        >
                                            Open →
                                        </Link>
                                        {filter === 'archived' ? (
                                            <button
                                                onClick={() => handleRestore(campaign.id)}
                                                disabled={restoringId === campaign.id}
                                                className="text-sm text-muted hover:text-ink disabled:opacity-50"
                                            >
                                                {restoringId === campaign.id ? 'Restoring...' : 'Restore'}
                                            </button>
                                        ) : (
                                            <button
                                                onClick={() => handleArchive(campaign.id)}
                                                disabled={archivingId === campaign.id}
                                                className="text-sm text-muted hover:text-ink disabled:opacity-50"
                                            >
                                                {archivingId === campaign.id ? 'Archiving...' : 'Archive'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
