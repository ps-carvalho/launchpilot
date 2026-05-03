import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const TYPES = [
    { value: 'one_off', label: 'One-off Launch', description: 'A single marketing push for a product launch, event, or announcement.' },
    { value: 'recurring', label: 'Recurring Content Stream', description: 'Regular content like weekly blog posts or monthly newsletters.' },
    { value: 'ongoing', label: 'Ongoing Presence', description: 'Continuous social media presence and brand awareness.' },
];

const CHANNELS = ['LinkedIn', 'Facebook', 'Instagram', 'Twitter/X', 'Blog', 'Email', 'Website'];

export default function CampaignCreate({ workspaces }) {
    const [workspaceId, setWorkspaceId] = useState(workspaces[0]?.id ?? '');
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [type, setType] = useState('one_off');
    const [goal, setGoal] = useState('');
    const [selectedChannels, setSelectedChannels] = useState([]);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const toggleChannel = (channel) => {
        setSelectedChannels((prev) =>
            prev.includes(channel)
                ? prev.filter((c) => c !== channel)
                : [...prev, channel]
        );
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);

        try {
            const res = await fetch('/campaigns', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    workspace_id: parseInt(workspaceId, 10),
                    title,
                    description,
                    type,
                    goal,
                    channels: selectedChannels,
                    start_date: startDate,
                    end_date: endDate,
                }),
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = `/campaigns/${data.campaign_id}`;
            } else {
                setError(data.error || 'Failed to create campaign.');
            }
        } catch (e) {
            setError('Something went wrong. Please try again.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Head title="New Campaign — LaunchPilot" />
            <div className="min-h-screen bg-slate-50">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                        <div className="flex items-center gap-4">
                            <Link href="/campaigns" className="text-sm text-muted hover:text-ink">Campaigns</Link>
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-3xl px-6 py-10">
                    <Link href="/campaigns" className="text-sm text-muted hover:text-ink mb-4 inline-block">
                        ← Back to campaigns
                    </Link>

                    <h1 className="text-2xl font-bold mb-1">Create a new campaign</h1>
                    <p className="text-sm text-muted mb-8">Set up a container for your marketing initiative.</p>

                    {error && (
                        <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Workspace */}
                        {workspaces.length > 1 && (
                            <div>
                                <label className="block text-sm font-medium mb-1.5">Workspace</label>
                                <select
                                    value={workspaceId}
                                    onChange={(e) => setWorkspaceId(e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                >
                                    {workspaces.map((ws) => (
                                        <option key={ws.id} value={ws.id}>{ws.name}</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Title */}
                        <div>
                            <label className="block text-sm font-medium mb-1.5">Campaign title <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="e.g. Summer Product Launch"
                                required
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                            />
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-sm font-medium mb-1.5">Description</label>
                            <textarea
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="What is this campaign about?"
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                            />
                        </div>

                        {/* Type */}
                        <div>
                            <label className="block text-sm font-medium mb-2">Campaign type</label>
                            <div className="grid gap-3 md:grid-cols-3">
                                {TYPES.map((t) => (
                                    <button
                                        key={t.value}
                                        type="button"
                                        onClick={() => setType(t.value)}
                                        className={`rounded-xl border p-4 text-left transition-colors ${
                                            type === t.value
                                                ? 'border-ink bg-ink/5'
                                                : 'border-line bg-white hover:border-ink/30'
                                        }`}
                                    >
                                        <div className="text-sm font-bold">{t.label}</div>
                                        <div className="mt-1 text-xs text-muted">{t.description}</div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Goal */}
                        <div>
                            <label className="block text-sm font-medium mb-1.5">Goal</label>
                            <input
                                type="text"
                                value={goal}
                                onChange={(e) => setGoal(e.target.value)}
                                placeholder="e.g. Drive 500 signups"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                            />
                        </div>

                        {/* Channels */}
                        <div>
                            <label className="block text-sm font-medium mb-2">Channels</label>
                            <div className="flex flex-wrap gap-2">
                                {CHANNELS.map((channel) => (
                                    <button
                                        key={channel}
                                        type="button"
                                        onClick={() => toggleChannel(channel)}
                                        className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                            selectedChannels.includes(channel)
                                                ? 'bg-ink text-white'
                                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                        }`}
                                    >
                                        {channel}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Dates */}
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium mb-1.5">Start date</label>
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1.5">End date</label>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex items-center gap-3 pt-4">
                            <button
                                type="submit"
                                disabled={submitting || !title.trim()}
                                className="rounded-lg bg-ink px-6 py-2.5 text-sm font-bold text-white hover:bg-ink/90 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {submitting ? 'Creating...' : 'Create campaign'}
                            </button>
                            <Link href="/campaigns" className="text-sm text-muted hover:text-ink">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </main>
            </div>
        </>
    );
}
