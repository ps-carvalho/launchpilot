import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AgentChat from '../../Components/AgentChat';
import AppShell from '../../Components/AppShell';

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

const MODES = [
    { key: 'text', label: 'Text', icon: '📝', description: 'Content, copy, strategy' },
    { key: 'image', label: 'Image', icon: '🖼️', description: 'Generate visuals' },
    { key: 'video', label: 'Video', icon: '🎬', description: 'Scripts & video generation' },
    { key: 'audio', label: 'Audio', icon: '🔊', description: 'Generate voice clips' },
];

const PROMPT_TEMPLATES = {
    text: [
        { id: 'linkedin', label: 'LinkedIn Post', template: 'Write a punchy LinkedIn post about {topic} for {audience}. Include a hook, 2-3 value points, and a CTA.' },
        { id: 'blog', label: 'Blog Article', template: 'Write a {word_count}-word blog post titled "{title}". Tone: {tone}. Include an intro, 3 sections with H2s, and a conclusion.' },
        { id: 'email', label: 'Email Sequence', template: 'Write a {n}-email nurture sequence for {goal}. Subject lines, preview text, and full body for each.' },
        { id: 'seo', label: 'SEO Brief', template: 'Create an SEO content brief for the keyword "{keyword}". Include search intent, H2 outline, and meta description.' },
    ],
    image: [
        { id: 'social_graphic', label: 'Social Graphic', template: 'A bold, eye-catching social media graphic about {topic}. Style: {style}. Aspect ratio: {aspect}.' },
        { id: 'product_hero', label: 'Product Hero', template: 'A clean, professional product hero image showing {product}. Background: {background}. Lighting: {lighting}.' },
        { id: 'blog_header', label: 'Blog Header', template: 'An editorial blog header illustration for an article about {topic}. Style: {style}. Mood: {mood}.' },
    ],
    video: [
        { id: 'explainer', label: 'Explainer Script', template: 'Write a {duration}-second explainer video script about {topic}. Hook, problem, solution, CTA. Read time check included.' },
        { id: 'product_demo', label: 'Product Demo', template: 'Script a {duration}-second product demo video for {product}. Focus on {feature}. Tone: {tone}.' },
        { id: 'social_reel', label: 'Social Reel', template: 'A {duration}-second vertical social reel script about {topic}. Fast-paced, trend-aware, with on-screen text cues.' },
    ],
    audio: [
        { id: 'voiceover', label: 'Voiceover', template: 'A calm, professional voiceover script for {topic}. Tone: {tone}. Pace: {pace}.' },
        { id: 'podcast_intro', label: 'Podcast Intro', template: 'A catchy 15-second podcast intro for {show_name}. Energetic, memorable, with a tagline.' },
        { id: 'announcement', label: 'Announcement', template: 'A clear, engaging audio announcement about {topic}. Include urgency and a call to action.' },
    ],
};

export default function CampaignShow({ campaign: initialCampaign, contentItems: initialItems, sessions, remainingRuns: initialRemainingRuns, isPro, agentModels, mediaAssets: initialMediaAssets, modalityModels }) {
    const [campaign, setCampaign] = useState(initialCampaign);
    const [contentItems, setContentItems] = useState(initialItems);
    const [activeMode, setActiveMode] = useState('text');
    const [activeTab, setActiveTab] = useState('content');
    const [mediaAssets, setMediaAssets] = useState(initialMediaAssets || []);
    const [remainingRuns, setRemainingRuns] = useState(initialRemainingRuns);
    const [editingId, setEditingId] = useState(null);
    const [editText, setEditText] = useState('');

    const statusTransitions = {
        draft: ['review', 'approved'],
        review: ['draft', 'approved'],
        approved: ['published'],
        published: [],
    };

    const typeLabels = {
        social_post: 'Social Post',
        blog_post: 'Blog Post',
        seo_report: 'SEO Report',
        media_plan: 'Media Plan',
        brainstorm_note: 'Brainstorm Note',
        text_content: 'Text',
        image: 'Image',
        video: 'Video',
        audio: 'Audio',
    };

    const updateStatus = async (itemId, nextStatus) => {
        try {
            await fetch(`/api/content-items/${itemId}/status`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: nextStatus }),
            });
            setContentItems((prev) =>
                prev.map((item) => (item.id === itemId ? { ...item, status: nextStatus } : item))
            );
        } catch (e) {
            alert('Failed to update status.');
        }
    };

    const handleDeleteItem = async (itemId) => {
        if (!confirm('Delete this content item?')) return;
        try {
            const res = await fetch(`/api/content-items/${itemId}/delete`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                setContentItems((prev) => prev.filter((item) => item.id !== itemId));
            }
        } catch (e) {
            alert('Failed to delete item.');
        }
    };

    const handleArchive = async () => {
        if (!confirm('Archive this campaign?')) return;
        try {
            await fetch(`/api/campaigns/${campaign.id}/archive`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            router.visit('/campaigns');
        } catch (e) {
            alert('Failed to archive campaign.');
        }
    };

    // SSE for real-time video status updates
    useEffect(() => {
        const sources = new Map();

        mediaAssets.forEach((asset) => {
            if (asset.type !== 'video' || (asset.status !== 'pending' && asset.status !== 'processing')) {
                return;
            }

            const es = new EventSource(`/api/media/${asset.id}/stream`);
            sources.set(asset.id, es);

            es.addEventListener('status', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.asset) {
                        setMediaAssets((prev) =>
                            prev.map((a) => (a.id === asset.id ? data.asset : a))
                        );
                        // Close connection if terminal state reached
                        if (data.asset.status === 'ready' || data.asset.status === 'failed') {
                            es.close();
                            sources.delete(asset.id);
                        }
                    }
                } catch (err) {
                    console.error('SSE parse error', err);
                }
            });

            es.addEventListener('error', (e) => {
                console.error('SSE error for asset', asset.id, e);
                es.close();
                sources.delete(asset.id);
            });
        });

        return () => {
            sources.forEach((es) => es.close());
        };
    }, [mediaAssets.map((a) => `${a.id}:${a.status}`).join(',')]);

    const pollVideo = async (assetId) => {
        // Fallback manual poll — SSE handles most updates automatically
        try {
            const res = await fetch(`/api/media/${assetId}/poll`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.asset) {
                setMediaAssets((prev) =>
                    prev.map((a) => (a.id === assetId ? data.asset : a))
                );
            }
        } catch (e) {
            console.error('Poll failed', e);
        }
    };

    const downloadMedia = (assetId) => {
        window.open(`/api/media/${assetId}/download`, '_blank');
    };

    const deleteMedia = async (assetId) => {
        if (!confirm('Delete this media asset?')) return;
        try {
            const res = await fetch(`/api/media/${assetId}/delete`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.message) {
                setMediaAssets((prev) => prev.filter((a) => a.id !== assetId));
            }
        } catch (e) {
            alert('Failed to delete media.');
        }
    };

    const freePromptCount = 2;
    const availablePrompts = isPro ? PROMPT_TEMPLATES[activeMode] : PROMPT_TEMPLATES[activeMode]?.slice(0, freePromptCount) || [];

    return (
        <>
            <Head title={`${campaign.title} — LaunchPilot`} />
            <AppShell>
                <div className="max-w-7xl mx-auto">
                    {/* Header */}
                    <div className="mb-6">
                        <Link href="/campaigns" className="text-sm text-muted hover:text-ink transition-colors inline-flex items-center gap-1 mb-3">
                            ← Back to Campaigns
                        </Link>
                        <div className="flex items-start justify-between">
                            <div>
                                <h1 className="text-2xl font-bold">{campaign.title}</h1>
                                <div className="flex items-center gap-3 mt-1.5">
                                    <span className="text-xs font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                        {TYPE_ICONS[campaign.type]} {TYPE_LABELS[campaign.type]}
                                    </span>
                                    <span className={`text-xs font-medium px-2.5 py-0.5 rounded-full border ${
                                        campaign.status === 'active' ? 'bg-green-50 text-green-700 border-green-100' :
                                        campaign.status === 'completed' ? 'bg-blue-50 text-blue-700 border-blue-100' :
                                        'bg-amber-50 text-amber-700 border-amber-100'
                                    }`}>
                                        {campaign.status}
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/campaigns/${campaign.id}/export`}
                                    className="text-xs font-medium text-slate-600 bg-white border border-line/60 px-3 py-1.5 rounded-lg hover:bg-paper transition-colors"
                                >
                                    Export
                                </Link>
                                <button
                                    onClick={handleArchive}
                                    className="text-xs font-medium text-red-600 bg-white border border-red-100 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                >
                                    Archive
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">
                        {/* Left: Agent Chat */}
                        <div className="lg:col-span-3">
                            <AgentChat
                                campaignId={campaign.id}
                                activeMode={activeMode}
                                setActiveMode={setActiveMode}
                                modes={MODES}
                                prompts={availablePrompts}
                                remainingRuns={remainingRuns}
                                onRemainingRunsChange={setRemainingRuns}
                                isPro={isPro}
                                agentModels={agentModels}
                                modalityModels={modalityModels}
                                onMediaAsset={(asset) => setMediaAssets((prev) => [asset, ...prev])}
                                onPollVideo={pollVideo}
                            />
                        </div>

                        {/* Right: Assets Panel */}
                        <div className="lg:col-span-2">
                            <div className="rounded-xl bg-white border border-line/60 shadow-elevation-1">
                                {/* Tabs */}
                                <div className="flex border-b border-line/60">
                                    <button
                                        onClick={() => setActiveTab('content')}
                                        className={`flex-1 px-4 py-3 text-xs font-bold transition-colors ${
                                            activeTab === 'content'
                                                ? 'text-ink border-b-2 border-ink'
                                                : 'text-muted hover:text-ink'
                                        }`}
                                    >
                                        Content ({contentItems.length})
                                    </button>
                                    <button
                                        onClick={() => setActiveTab('media')}
                                        className={`flex-1 px-4 py-3 text-xs font-bold transition-colors ${
                                            activeTab === 'media'
                                                ? 'text-ink border-b-2 border-ink'
                                                : 'text-muted hover:text-ink'
                                        }`}
                                    >
                                        Media ({mediaAssets.length})
                                    </button>
                                </div>

                                {/* Content Tab */}
                                {activeTab === 'content' && (
                                    <div className="p-4 max-h-[600px] overflow-y-auto">
                                        {contentItems.length === 0 && (
                                            <div className="text-center py-8">
                                                <p className="text-sm text-muted">No content yet.</p>
                                                <p className="text-xs text-muted mt-1">Generate content with the agent and save it here.</p>
                                            </div>
                                        )}
                                        <div className="space-y-3">
                                            {contentItems.map((item) => (
                                                <div key={item.id} className="rounded-lg border border-line/40 p-3 bg-paper group">
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                                            {typeLabels[item.type] || item.type}
                                                        </span>
                                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide ${
                                                            item.status === 'draft' ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                                            item.status === 'review' ? 'bg-blue-50 text-blue-700 border border-blue-100' :
                                                            item.status === 'approved' ? 'bg-green-50 text-green-700 border border-green-100' :
                                                            'bg-slate-100 text-slate-600 border border-slate-200'
                                                        }`}>
                                                            {item.status}
                                                        </span>
                                                    </div>
                                                    {editingId === item.id ? (
                                                        <textarea
                                                            value={editText}
                                                            onChange={(e) => setEditText(e.target.value)}
                                                            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all mb-2"
                                                            rows={4}
                                                        />
                                                    ) : (
                                                        <div className="text-sm text-slate-800 whitespace-pre-wrap line-clamp-6">{item.content}</div>
                                                    )}
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        {editingId === item.id ? (
                                                            <>
                                                                <button
                                                                    onClick={async () => {
                                                                        await fetch(`/api/content-items/${item.id}`, {
                                                                            method: 'PUT',
                                                                            credentials: 'same-origin',
                                                                            headers: { 'Content-Type': 'application/json' },
                                                                            body: JSON.stringify({ content: editText }),
                                                                        });
                                                                        setContentItems((prev) => prev.map((i) => (i.id === item.id ? { ...i, content: editText } : i)));
                                                                        setEditingId(null);
                                                                    }}
                                                                    className="text-xs text-accent hover:text-accent/80 underline font-medium transition-colors"
                                                                >
                                                                    Save
                                                                </button>
                                                                <button
                                                                    onClick={() => setEditingId(null)}
                                                                    className="text-xs text-muted hover:text-ink underline transition-colors"
                                                                >
                                                                    Cancel
                                                                </button>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <button
                                                                    onClick={() => { setEditingId(item.id); setEditText(item.content); }}
                                                                    className="text-xs text-muted hover:text-ink underline transition-colors"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    onClick={() => navigator.clipboard.writeText(item.content)}
                                                                    className="text-xs text-muted hover:text-ink underline transition-colors"
                                                                >
                                                                    Copy
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
                                                                <button
                                                                    onClick={() => handleDeleteItem(item.id)}
                                                                    className="text-xs text-red-500 hover:text-red-700 underline transition-colors"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Media Tab */}
                                {activeTab === 'media' && (
                                    <div className="p-4 max-h-[600px] overflow-y-auto">
                                        {mediaAssets.length === 0 && (
                                            <div className="text-center py-8">
                                                <p className="text-sm text-muted">No media yet.</p>
                                                <p className="text-xs text-muted mt-1">Switch to Image or Video mode to generate media.</p>
                                            </div>
                                        )}
                                        <div className="space-y-3">
                                            {mediaAssets.map((asset) => (
                                                <div key={asset.id} className="rounded-lg border border-line/40 p-3 bg-paper">
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                                            {asset.type}
                                                        </span>
                                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide ${
                                                            asset.status === 'ready' ? 'bg-green-50 text-green-700 border border-green-100' :
                                                            asset.status === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                                            asset.status === 'processing' ? 'bg-blue-50 text-blue-700 border border-blue-100' :
                                                            'bg-red-50 text-red-700 border border-red-100'
                                                        }`}>
                                                            {asset.status}
                                                        </span>
                                                    </div>
                                                    {asset.type === 'image' && asset.local_path && (
                                                        <img
                                                            src={`/storage/media/${asset.campaign_id}/${asset.local_path.split('/').pop()}`}
                                                            alt="Generated"
                                                            className="w-full rounded-lg mb-2"
                                                        />
                                                    )}
                                                    {asset.type === 'video' && asset.status === 'ready' && asset.local_path && (
                                                        <video
                                                            src={`/storage/media/${asset.campaign_id}/${asset.local_path.split('/').pop()}`}
                                                            controls
                                                            className="w-full rounded-lg mb-2"
                                                        />
                                                    )}
                                                    {asset.type === 'video' && asset.status === 'pending' && (
                                                        <div className="flex items-center gap-2 text-xs text-muted py-4">
                                                            <span className="inline-block w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                                                            Processing video...
                                                            <button
                                                                onClick={() => pollVideo(asset.id)}
                                                                className="text-accent hover:text-accent/80 underline font-medium"
                                                            >
                                                                Check status
                                                            </button>
                                                        </div>
                                                    )}
                                                    {asset.type === 'audio' && asset.status === 'ready' && asset.local_path && (
                                                        <audio
                                                            src={`/storage/media/${asset.campaign_id}/${asset.local_path.split('/').pop()}`}
                                                            controls
                                                            className="w-full rounded-lg mb-2"
                                                        />
                                                    )}
                                                    {asset.type === 'video' && asset.status === 'failed' && (
                                                        <div className="text-xs text-red-500 py-2">Generation failed.</div>
                                                    )}
                                                    <div className="text-xs text-muted line-clamp-2">
                                                        {JSON.parse(asset.metadata || '{}').prompt || ''}
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        {asset.local_path && (
                                                            <button
                                                                onClick={() => downloadMedia(asset.id)}
                                                                className="text-xs text-accent hover:text-accent/80 underline font-medium transition-colors"
                                                            >
                                                                Download
                                                            </button>
                                                        )}
                                                        <button
                                                            onClick={() => deleteMedia(asset.id)}
                                                            className="text-xs text-red-500 hover:text-red-700 underline transition-colors"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </AppShell>
        </>
    );
}
