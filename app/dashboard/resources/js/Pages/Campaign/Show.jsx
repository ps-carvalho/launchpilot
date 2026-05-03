import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AgentChat from '../../Components/AgentChat';
import AppShell from '../../Components/AppShell';

const AGENTS = [
    { type: 'social', label: 'Social Agent', icon: '💬', description: 'LinkedIn, Facebook, and promotional posts' },
    { type: 'content', label: 'Content Agent', icon: '📝', description: 'Blog posts, success stories, announcements' },
    { type: 'seo', label: 'SEO Agent', icon: '🔍', description: 'SEO analysis and optimization suggestions' },
    { type: 'brainstorm', label: 'Brainstorm Agent', icon: '💡', description: 'Feature ideas and targeting advice' },
];

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

export default function CampaignShow({ campaign: initialCampaign, contentItems: initialItems, sessions, remainingRuns: initialRemainingRuns }) {
    const [campaign, setCampaign] = useState(initialCampaign);
    const [contentItems, setContentItems] = useState(initialItems);
    const [activeAgent, setActiveAgent] = useState('social');
    const [editingId, setEditingId] = useState(null);
    const [editText, setEditText] = useState('');
    const [isEditingCampaign, setIsEditingCampaign] = useState(false);
    const [editTitle, setEditTitle] = useState(campaign.title);
    const [editDescription, setEditDescription] = useState(campaign.description || '');
    const [editGoal, setEditGoal] = useState(campaign.goal || '');
    const [savingCampaign, setSavingCampaign] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const [remainingRuns, setRemainingRuns] = useState(initialRemainingRuns);

    const statusColors = {
        draft: 'bg-amber-50 text-amber-700 border-amber-100',
        approved: 'bg-blue-50 text-blue-700 border-blue-100',
        scheduled: 'bg-purple-50 text-purple-700 border-purple-100',
        published: 'bg-green-50 text-green-700 border-green-100',
    };

    const campaignStatusColors = {
        draft: 'bg-amber-50 text-amber-700 border-amber-100',
        active: 'bg-green-50 text-green-700 border-green-100',
        completed: 'bg-slate-100 text-slate-700 border-slate-200',
    };

    const statusTransitions = {
        draft: ['approved'],
        approved: ['scheduled', 'published', 'draft'],
        scheduled: ['published', 'draft', 'approved'],
        published: [],
    };

    const typeLabels = {
        social_post: 'Social Post',
        blog_post: 'Blog Post',
        seo_report: 'SEO Report',
        brainstorm_note: 'Brainstorm Note',
    };

    const updateStatus = async (itemId, newStatus) => {
        try {
            const res = await fetch(`/api/content-items/${itemId}/status`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: newStatus }),
            });
            const data = await res.json();
            if (data.success) {
                setContentItems((prev) => prev.map((item) =>
                    item.id === itemId ? { ...item, status: newStatus, published_at: newStatus === 'published' ? new Date().toISOString() : item.published_at } : item
                ));
            }
        } catch (e) {
            alert('Failed to update status.');
        }
    };

    const startEdit = (item) => {
        setEditingId(item.id);
        setEditText(item.content);
    };

    const saveEdit = async (itemId) => {
        try {
            const res = await fetch(`/api/content-items/${itemId}/edit`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: editText }),
            });
            const data = await res.json();
            if (data.success) {
                setContentItems((prev) => prev.map((item) =>
                    item.id === itemId ? { ...item, content: editText } : item
                ));
                setEditingId(null);
            }
        } catch (e) {
            alert('Failed to save edit.');
        }
    };

    const saveCampaignEdit = async () => {
        setSavingCampaign(true);
        try {
            const res = await fetch(`/campaigns/${campaign.id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: editTitle,
                    description: editDescription,
                    goal: editGoal,
                }),
            });
            const data = await res.json();
            if (data.success) {
                setCampaign((prev) => ({
                    ...prev,
                    title: editTitle,
                    description: editDescription,
                    goal: editGoal,
                }));
                setIsEditingCampaign(false);
            }
        } catch (e) {
            alert('Failed to save campaign.');
        } finally {
            setSavingCampaign(false);
        }
    };

    const handleArchive = async () => {
        if (!confirm('Archive this campaign? It will be moved to your archived campaigns list.')) return;
        setArchiving(true);
        try {
            const res = await fetch(`/campaigns/${campaign.id}/archive`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                setCampaign((prev) => ({ ...prev, archived_at: new Date().toISOString() }));
            }
        } catch (e) {
            alert('Failed to archive campaign.');
        } finally {
            setArchiving(false);
        }
    };

    const isArchived = campaign.archived_at !== null;
    const channels = typeof campaign.channels === 'string'
        ? JSON.parse(campaign.channels || '[]')
        : (campaign.channels || []);

    return (
        <>
            <Head title={`${campaign.title} — LaunchPilot`} />
            <AppShell>
                <Link href="/campaigns" className="text-sm text-muted hover:text-ink transition-colors inline-flex items-center gap-1 mb-4">
                    ← Back to Campaigns
                </Link>

                {/* Campaign Header */}
                <div className="rounded-xl bg-white p-6 border border-line/60 shadow-elevation-1 mb-6">
                    {isArchived && (
                        <div className="mb-4 inline-flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-sm font-medium text-amber-700">
                            <span>🗃️</span> This campaign is archived
                        </div>
                    )}

                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div className="flex-1">
                            {isEditingCampaign ? (
                                <div className="space-y-3">
                                    <input
                                        type="text"
                                        value={editTitle}
                                        onChange={(e) => setEditTitle(e.target.value)}
                                        className="w-full max-w-xl rounded-lg border border-line bg-paper px-3 py-2 text-lg font-bold focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                    />
                                    <textarea
                                        value={editDescription}
                                        onChange={(e) => setEditDescription(e.target.value)}
                                        placeholder="Description"
                                        rows={2}
                                        className="w-full rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                    />
                                    <textarea
                                        value={editGoal}
                                        onChange={(e) => setEditGoal(e.target.value)}
                                        placeholder="Goal"
                                        rows={2}
                                        className="w-full rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                    />
                                    <div className="flex gap-2">
                                        <button
                                            onClick={saveCampaignEdit}
                                            disabled={savingCampaign}
                                            className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all disabled:opacity-50"
                                        >
                                            {savingCampaign ? 'Saving...' : 'Save'}
                                        </button>
                                        <button
                                            onClick={() => setIsEditingCampaign(false)}
                                            className="rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-paper transition-all"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <div className="flex items-center gap-3 mb-2">
                                        <span className="text-2xl">{TYPE_ICONS[campaign.type] || '📋'}</span>
                                        <h1 className="text-2xl font-bold">{campaign.title}</h1>
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold border ${campaignStatusColors[campaign.status] || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                            {campaign.status}
                                        </span>
                                    </div>
                                    {campaign.description && (
                                        <p className="text-sm text-muted mb-2">{campaign.description}</p>
                                    )}
                                    {campaign.goal && (
                                        <p className="text-sm font-medium">🎯 {campaign.goal}</p>
                                    )}
                                    <div className="mt-3 flex items-center gap-2 text-xs text-muted flex-wrap">
                                        <span>{TYPE_LABELS[campaign.type] || campaign.type}</span>
                                        {channels.length > 0 && (
                                            <span>· {channels.join(', ')}</span>
                                        )}
                                        <span>· {new Date(campaign.created_at).toLocaleDateString()}</span>
                                    </div>
                                </>
                            )}
                        </div>

                        {!isEditingCampaign && !isArchived && (
                            <div className="flex items-center gap-2 shrink-0">
                                <a
                                    href={`/campaigns/${campaign.id}/export`}
                                    className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-paper hover:border-accent/30 shadow-elevation-1 hover:shadow-elevation-2 transition-all"
                                >
                                    Export
                                </a>
                                <button
                                    onClick={() => setIsEditingCampaign(true)}
                                    className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-paper hover:border-accent/30 shadow-elevation-1 hover:shadow-elevation-2 transition-all"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={handleArchive}
                                    disabled={archiving}
                                    className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-paper hover:border-accent/30 shadow-elevation-1 hover:shadow-elevation-2 transition-all disabled:opacity-50"
                                >
                                    {archiving ? 'Archiving...' : 'Archive'}
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    {/* Left: Agent Chat */}
                    <div className="lg:col-span-3">
                        <div className="flex gap-2 mb-4 overflow-x-auto pb-1">
                            {AGENTS.map((agent) => (
                                <button
                                    key={agent.type}
                                    onClick={() => setActiveAgent(agent.type)}
                                    className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition-all shadow-elevation-1 hover:shadow-elevation-2 hover:-translate-y-0.5 ${
                                        activeAgent === agent.type
                                            ? 'bg-accent text-white shadow-elevation-2'
                                            : 'bg-white text-ink border border-line/60 hover:border-accent/30'
                                    }`}
                                >
                                    <span>{agent.icon}</span>
                                    <span className="hidden sm:inline">{agent.label}</span>
                                </button>
                            ))}
                        </div>

                        <AgentChat
                            campaignId={campaign.id}
                            agentType={activeAgent}
                            agentLabel={AGENTS.find(a => a.type === activeAgent)?.label}
                            agentIcon={AGENTS.find(a => a.type === activeAgent)?.icon}
                            remainingRuns={remainingRuns}
                            onRemainingRunsChange={setRemainingRuns}
                        />
                    </div>

                    {/* Right: Content Items */}
                    <div className="lg:col-span-2">
                        <div className="rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-sm font-bold">Generated Content</h2>
                                <span className="text-xs text-muted">{contentItems.length} items</span>
                            </div>

                            {contentItems.length === 0 ? (
                                <div className="text-center py-8">
                                    <p className="text-sm text-muted">No content yet. Chat with an agent and click "Save to campaign" to add items.</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {contentItems.map((item) => (
                                        <div key={item.id} className="rounded-lg border border-line/60 p-3 hover:shadow-elevation-1 transition-all hover:-translate-y-0.5 bg-white">
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-semibold text-slate-700">{typeLabels[item.type] || item.type}</span>
                                                    {item.platform && <span className="text-xs text-muted">({item.platform})</span>}
                                                </div>
                                                <span className={`text-xs rounded-full px-2 py-0.5 font-medium border ${statusColors[item.status] || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                                    {item.status}
                                                </span>
                                            </div>

                                            {editingId === item.id ? (
                                                <div className="space-y-2">
                                                    <textarea
                                                        value={editText}
                                                        onChange={(e) => setEditText(e.target.value)}
                                                        rows={4}
                                                        className="w-full rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                                    />
                                                    <div className="flex gap-2">
                                                        <button
                                                            onClick={() => saveEdit(item.id)}
                                                            className="rounded-lg bg-ink px-3 py-1 text-xs font-bold text-white hover:bg-ink/90 shadow-elevation-1 transition-all"
                                                        >
                                                            Save
                                                        </button>
                                                        <button
                                                            onClick={() => setEditingId(null)}
                                                            className="rounded-lg border border-line bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-paper transition-all"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            ) : (
                                                <>
                                                    <p className="text-xs text-slate-700 line-clamp-3 whitespace-pre-wrap">{item.content}</p>
                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                        <button
                                                            onClick={() => navigator.clipboard.writeText(item.content)}
                                                            className="text-xs text-muted hover:text-ink underline transition-colors"
                                                        >
                                                            Copy
                                                        </button>
                                                        <button
                                                            onClick={() => startEdit(item)}
                                                            className="text-xs text-ink hover:text-accent underline transition-colors font-medium"
                                                        >
                                                            Edit
                                                        </button>
                                                        {statusTransitions[item.status]?.map((next) => (
                                                            <button
                                                                key={next}
                                                                onClick={() => updateStatus(item.id, next)}
                                                                className="text-xs text-accent hover:text-accent/80 underline transition-colors font-medium capitalize"
                                                            >
                                                                {next}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {sessions.length > 0 && (
                            <div className="mt-6 rounded-xl bg-white p-5 border border-line/60 shadow-elevation-1">
                                <h2 className="text-sm font-bold mb-3">Past Conversations</h2>
                                <div className="space-y-2">
                                    {sessions.map((s) => {
                                        const agent = AGENTS.find(a => a.type === s.agent_type);
                                        return (
                                            <div key={s.id} className="flex items-center gap-2 text-xs text-muted">
                                                <span>{agent?.icon || '🤖'}</span>
                                                <span className="font-medium text-ink">{agent?.label || s.agent_type}</span>
                                                <span>· {new Date(s.updated_at).toLocaleDateString()}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </AppShell>
        </>
    );
}
