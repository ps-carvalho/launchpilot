import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Components/AppShell';

const TYPES = [
    { value: 'one_off', label: 'One-off Launch', icon: '🚀', desc: 'A focused, time-bound launch.' },
    { value: 'recurring', label: 'Recurring Stream', icon: '🔄', desc: 'Repeats on a schedule.' },
    { value: 'ongoing', label: 'Ongoing Presence', icon: '📡', desc: 'Continuous brand visibility.' },
];

const CHANNELS = ['LinkedIn', 'Twitter', 'Facebook', 'Instagram', 'Blog', 'Email'];

export default function CampaignCreate({ workspaces }) {
    const [form, setForm] = useState({
        workspace_id: workspaces?.[0]?.id ?? '',
        title: '',
        description: '',
        goal: '',
        type: 'one_off',
        channels: [],
    });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});

    const toggleChannel = (ch) => {
        setForm((prev) => ({
            ...prev,
            channels: prev.channels.includes(ch)
                ? prev.channels.filter((c) => c !== ch)
                : [...prev.channels, ch],
        }));
    };

    const submit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const res = await fetch('/campaigns', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...form,
                    channels: form.channels,
                }),
            });

            const data = await res.json();

            if (data.success) {
                window.location.href = `/campaigns/${data.campaign.id}`;
                return;
            }

            if (data.errors) {
                setErrors(data.errors);
            } else {
                setErrors({ general: data.message || 'Failed to create campaign.' });
            }
        } catch (e) {
            setErrors({ general: 'Network error.' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="New Campaign — LaunchPilot" />
            <AppShell>
                <a href="/campaigns" className="text-sm text-muted hover:text-ink transition-colors inline-flex items-center gap-1 mb-4">
                    ← Back to Campaigns
                </a>

                <div className="mx-auto max-w-2xl">
                    <h1 className="text-2xl font-bold mb-1">New Campaign</h1>
                    <p className="text-sm text-muted mb-6">Plan your next marketing initiative</p>

                    <form onSubmit={submit} className="space-y-6">
                        {/* Workspace */}
                        <div>
                            <label className="block text-sm font-bold mb-1.5">Workspace</label>
                            <select
                                value={form.workspace_id}
                                onChange={(e) => setForm((prev) => ({ ...prev, workspace_id: e.target.value }))}
                                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                            >
                                {workspaces.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Title */}
                        <div>
                            <label className="block text-sm font-bold mb-1.5">Title</label>
                            <input
                                type="text"
                                value={form.title}
                                onChange={(e) => setForm((prev) => ({ ...prev, title: e.target.value }))}
                                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                placeholder="e.g. Q1 Product Launch"
                            />
                            {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title}</p>}
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-sm font-bold mb-1.5">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                                rows={3}
                                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                placeholder="What is this campaign about?"
                            />
                        </div>

                        {/* Goal */}
                        <div>
                            <label className="block text-sm font-bold mb-1.5">Goal</label>
                            <textarea
                                value={form.goal}
                                onChange={(e) => setForm((prev) => ({ ...prev, goal: e.target.value }))}
                                rows={2}
                                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                placeholder="e.g. Generate 100 signups"
                            />
                        </div>

                        {/* Type */}
                        <div>
                            <label className="block text-sm font-bold mb-2">Campaign Type</label>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {TYPES.map((t) => (
                                    <button
                                        key={t.value}
                                        type="button"
                                        onClick={() => setForm((prev) => ({ ...prev, type: t.value }))}
                                        className={`rounded-xl border p-4 text-left transition-all shadow-elevation-1 hover:shadow-elevation-2 hover:-translate-y-0.5 ${
                                            form.type === t.value
                                                ? 'border-accent bg-accent/5 shadow-elevation-2'
                                                : 'border-line/60 bg-white hover:border-accent/30'
                                        }`}
                                    >
                                        <div className="text-2xl mb-2">{t.icon}</div>
                                        <div className="text-sm font-bold">{t.label}</div>
                                        <div className="mt-1 text-xs text-muted">{t.desc}</div>
                                    </button>
                                ))}
                            </div>
                            {errors.type && <p className="mt-1 text-xs text-red-500">{errors.type}</p>}
                        </div>

                        {/* Channels */}
                        <div>
                            <label className="block text-sm font-bold mb-2">Channels</label>
                            <div className="flex flex-wrap gap-2">
                                {CHANNELS.map((ch) => (
                                    <button
                                        key={ch}
                                        type="button"
                                        onClick={() => toggleChannel(ch)}
                                        className={`rounded-full px-3.5 py-1.5 text-xs font-semibold transition-all shadow-sm hover:shadow-md ${
                                            form.channels.includes(ch)
                                                ? 'bg-accent text-white shadow-elevation-1'
                                                : 'bg-white text-slate-700 border border-line/60 hover:border-accent/30'
                                        }`}
                                    >
                                        {ch}
                                    </button>
                                ))}
                            </div>
                            {errors.channels && <p className="mt-1 text-xs text-red-500">{errors.channels}</p>}
                        </div>

                        {/* Submit */}
                        <div className="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={loading}
                                className="rounded-lg bg-ink px-6 py-2.5 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                            >
                                {loading ? 'Creating...' : 'Create Campaign'}
                            </button>
                            <a
                                href="/campaigns"
                                className="text-sm text-muted hover:text-ink transition-colors"
                            >
                                Cancel
                            </a>
                        </div>

                        {errors.general && (
                            <p className="text-sm text-red-500">{errors.general}</p>
                        )}
                    </form>
                </div>
            </AppShell>
        </>
    );
}
