import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AgentChat from '../../Components/AgentChat';

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
    const [remainingRuns, setRemainingRuns] = useState(initialRemainingRuns);
    const [activeAgent, setActiveAgent] = useState('social');
    const [editingId, setEditingId] = useState(null);
    const [editText, setEditText] = useState('');

    // Campaign edit state
    const [isEditingCampaign, setIsEditingCampaign] = useState(false);
    const [editTitle, setEditTitle] = useState(campaign.title);
    const [editDescription, setEditDescription] = useState(campaign.description || '');
    const [editGoal, setEditGoal] = useState(campaign.goal || '');
    const [savingCampaign, setSavingCampaign] = useState(false);
    const [archiving, setArchiving] = useState(false);

    const statusColors = {
        draft: 'bg-amber-50 text-amber-700',
        approved: 'bg-blue-50 text-blue-700',
        scheduled: 'bg-purple-50 text-purple-700',
        published: 'bg-green-50 text-green-700',
    };

    const campaignStatusColors = {
        draft: 'bg-amber-50 text-amber-700',
        active: 'bg-green-50 text-green-700',
        completed: 'bg-slate-100 text-slate-700',
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
                headers: { 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                router.visit('/campaigns');
            }
        } catch (e) {
            alert('Failed to archive campaign.');
            setArchiving(false);
        }
    };

    const isArchived = campaign.archived_at !== null;

    return (
        <>
            <Head title={`${campaign.title} — LaunchPilot`} />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link href="/campaigns" className="text-sm text-muted hover:text-ink">Campaigns</Link>
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                            <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink">Knowledge Base</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-6 py-8">
                    {/* Campaign Header */}
                    <div className="mb-8">
                        <Link href="/campaigns" className="text-sm text-muted hover:text-ink mb-2 inline-block">
                            ← Back to campaigns
                        </Link>

                        {isArchived && (
                            <div className="mb-3 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                                <span>🗃️</span> This campaign is archived
                            </div>
                        )}

                        <div className="flex items-start justify-between gap-4 mt-2">
                            <div className="min-w-0">
                                {isEditingCampaign ? (
                                    <div className="space-y-3">
                                        <input
                                            type="text"
                                            value={editTitle}
                                            onChange={(e) => setEditTitle(e.target.value)}
                                            className="w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-lg font-bold focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                        />
                                        <textarea
                                            value={editDescription}
                                            onChange={(e) => setEditDescription(e.target.value)}
                                            placeholder="Description"
                                            rows={2}
                                            className="w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                        />
                                        <input
                                            type="text"
                                            value={editGoal}
                                            onChange={(e) => setEditGoal(e.target.value)}
                                            placeholder="Goal"
                                            className="w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                        />
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={saveCampaignEdit}
                                                disabled={savingCampaign}
                                                className="rounded-lg bg-ink px-4 py-1.5 text-sm font-bold text-white hover:bg-ink/90 disabled:opacity-50"
                                            >
                                                {savingCampaign ? 'Saving...' : 'Save'}
                                            </button>
                                            <button
                                                onClick={() => {
                                                    setIsEditingCampaign(false);
                                                    setEditTitle(campaign.title);
                                                    setEditDescription(campaign.description || '');
                                                    setEditGoal(campaign.goal || '');
                                                }}
                                                className="text-sm text-muted hover:text-ink"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="flex items-center gap-3 flex-wrap">
                                            <h1 className="text-2xl font-bold">{campaign.title}</h1>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${campaignStatusColors[campaign.status] || 'bg-slate-100 text-slate-700'}`}>
                                                {campaign.status}
                                            </span>
                                        </div>
                                        {campaign.description && (
                                            <p className="mt-1 text-sm text-muted">{campaign.description}</p>
                                        )}
                                        {campaign.goal && (
                                            <p className="mt-1 text-sm text-slate-600">Goal: {campaign.goal}</p>
                                        )}
                                        <div className="mt-2 flex items-center gap-3 text-xs text-muted">
                                            <span>{TYPE_ICONS[campaign.type] || '📋'} {TYPE_LABELS[campaign.type] || campaign.type}</span>
                                            {(campaign.channels || []).length > 0 && (
                                                <span>
                                                    {(typeof campaign.channels === 'string' ? JSON.parse(campaign.channels) : campaign.channels).join(', ')}
                                                </span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>

                            {!isEditingCampaign && !isArchived && (
                                <div className="flex items-center gap-2 shrink-0">
                                    <a
                                        href={`/campaigns/${campaign.id}/export`}
                                        className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:border-ink/30"
                                    >
                                        Export
                                    </a>
                                    <button
                                        onClick={() => setIsEditingCampaign(true)}
                                        className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:border-ink/30"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={handleArchive}
                                        disabled={archiving}
                                        className="rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:border-ink/30 disabled:opacity-50"
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
                                        className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
                                            activeAgent === agent.type
                                                ? 'bg-ink text-white'
                                                : 'bg-white border border-line text-slate-700 hover:border-ink/30'
                                        }`}
                                    >
                                        <span>{agent.icon}</span>
                                        {agent.label}
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
                            <div className="rounded-xl border border-line bg-white p-5">
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-sm font-bold">Generated Content</h2>
                                    <span className="text-xs text-muted">{contentItems.length} items</span>
                                </div>

                                {contentItems.length === 0 ? (
                                    <p className="text-sm text-muted py-8 text-center">
                                        No content yet. Chat with an agent and click "Save to campaign" to add items.
                                    </p>
                                ) : (
                                    <div className="space-y-3 max-h-[500px] overflow-y-auto">
                                        {contentItems.map((item) => (
                                            <div key={item.id} className="rounded-lg border border-line p-3">
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="text-xs font-semibold text-slate-700">
                                                        {typeLabels[item.type] || item.type}
                                                    </span>
                                                    <span className={`text-xs rounded-full px-2 py-0.5 font-medium ${statusColors[item.status] || 'bg-slate-100 text-slate-700'}`}>
                                                        {item.status}
                                                    </span>
                                                </div>

                                                {editingId === item.id ? (
                                                    <div className="mt-2">
                                                        <textarea
                                                            value={editText}
                                                            onChange={(e) => setEditText(e.target.value)}
                                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                                            rows={4}
                                                        />
                                                        <div className="mt-2 flex gap-2">
                                                            <button
                                                                onClick={() => saveEdit(item.id)}
                                                                className="text-xs bg-ink text-white px-3 py-1 rounded font-medium"
                                                            >
                                                                Save
                                                            </button>
                                                            <button
                                                                onClick={() => setEditingId(null)}
                                                                className="text-xs text-muted hover:text-ink"
                                                            >
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <>
                                                        <p className="text-xs text-slate-600 line-clamp-3">{item.content}</p>
                                                        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                                            <button
                                                                onClick={() => navigator.clipboard.writeText(item.content)}
                                                                className="text-xs text-muted hover:text-ink underline"
                                                            >
                                                                Copy
                                                            </button>
                                                            <button
                                                                onClick={() => startEdit(item)}
                                                                className="text-xs text-muted hover:text-ink underline"
                                                            >
                                                                Edit
                                                            </button>
                                                            {statusTransitions[item.status]?.map((next) => (
                                                                <button
                                                                    key={next}
                                                                    onClick={() => updateStatus(item.id, next)}
                                                                    className="text-xs text-ink hover:text-ink/80 underline font-medium capitalize"
                                                                >
                                                                    → {next}
                                                                </button>
                                                            ))}
                                                            {item.platform && (
                                                                <span className="text-xs text-muted">{item.platform}</span>
                                                            )}
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {sessions.length > 0 && (
                                <div className="mt-4 rounded-xl border border-line bg-white p-5">
                                    <h2 className="text-sm font-bold mb-3">Past Conversations</h2>
                                    <div className="space-y-2">
                                        {sessions.map((s) => {
                                            const agent = AGENTS.find(a => a.type === s.agent_type);
                                            const msgCount = JSON.parse(s.messages || '[]').length;
                                            return (
                                                <div key={s.id} className="flex items-center justify-between text-xs">
                                                    <div className="flex items-center gap-2">
                                                        <span>{agent?.icon || '🤖'}</span>
                                                        <span className="font-medium">{agent?.label || s.agent_type}</span>
                                                    </div>
                                                    <span className="text-muted">{msgCount} messages</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
