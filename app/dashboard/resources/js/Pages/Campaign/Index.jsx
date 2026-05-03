import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Components/AppShell';

const TYPE_ICONS = {
    one_off: '🚀',
    recurring: '🔄',
    ongoing: '📡',
};

const TYPE_LABELS = {
    one_off: 'One-off Launch',
    recurring: 'Recurring Stream',
    ongoing: 'Ongoing Presence',
};

export default function CampaignIndex({ campaigns, filter: initialFilter }) {
    const [filter, setFilter] = useState(initialFilter || 'active');

    const filtered = campaigns.filter((c) => {
        if (filter === 'archived') return c.archived_at !== null;
        return c.archived_at === null;
    });

    return (
        <>
            <Head title="Campaigns — LaunchPilot" />
            <AppShell>
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">Campaigns</h1>
                        <p className="mt-1 text-sm text-muted">Organize and manage your marketing initiatives</p>
                    </div>
                    <Link
                        href="/campaigns/create"
                        className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 flex items-center gap-1.5"
                    >
                        <span>+</span>
                        <span className="hidden sm:inline">New campaign</span>
                    </Link>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 rounded-xl bg-white border border-line/60 p-1 w-fit mb-6 shadow-elevation-1">
                    <button
                        onClick={() => setFilter('active')}
                        className={`rounded-lg px-4 py-1.5 text-sm font-medium transition-all ${
                            filter === 'active'
                                ? 'bg-ink text-white shadow-sm'
                                : 'text-muted hover:text-ink hover:bg-slate-50'
                        }`}
                    >
                        Active
                    </button>
                    <button
                        onClick={() => setFilter('archived')}
                        className={`rounded-lg px-4 py-1.5 text-sm font-medium transition-all ${
                            filter === 'archived'
                                ? 'bg-ink text-white shadow-sm'
                                : 'text-muted hover:text-ink hover:bg-slate-50'
                        }`}
                    >
                        Archived
                    </button>
                </div>

                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-16 shadow-elevation-1">
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
                                className="mt-6 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 inline-block"
                            >
                                Create your first campaign
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filtered.map((campaign) => (
                            <Link
                                href={`/campaigns/${campaign.id}`}
                                key={campaign.id}
                                className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-1 block group"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-lg">{TYPE_ICONS[campaign.type] || '📋'}</span>
                                        <span className="text-xs font-medium text-slate-500">
                                            {TYPE_LABELS[campaign.type] || campaign.type}
                                        </span>
                                    </div>
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                        campaign.status === 'active' ? 'bg-green-50 text-green-600 border border-green-100' :
                                        campaign.status === 'completed' ? 'bg-slate-100 text-slate-600 border border-slate-200' :
                                        'bg-amber-50 text-amber-600 border border-amber-100'
                                    }`}>
                                        {campaign.status}
                                    </span>
                                </div>
                                <h3 className="mt-3 text-base font-bold group-hover:text-accent transition-colors">{campaign.title}</h3>
                                {campaign.description && (
                                    <p className="mt-1 text-xs text-muted line-clamp-2">{campaign.description}</p>
                                )}
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
                                <div className="mt-3 text-xs text-muted">
                                    {new Date(campaign.created_at).toLocaleDateString()}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </AppShell>
        </>
    );
}
