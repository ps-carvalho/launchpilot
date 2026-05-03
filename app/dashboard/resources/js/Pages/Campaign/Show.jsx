import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AgentChat from '../../Components/AgentChat';

const AGENTS = [
    { type: 'social', label: 'Social Agent', icon: '💬', description: 'LinkedIn, Facebook, and promotional posts' },
    { type: 'content', label: 'Content Agent', icon: '📝', description: 'Blog posts, success stories, announcements' },
    { type: 'seo', label: 'SEO Agent', icon: '🔍', description: 'SEO analysis and optimization suggestions' },
    { type: 'brainstorm', label: 'Brainstorm Agent', icon: '💡', description: 'Feature ideas and targeting advice' },
];

export default function CampaignShow({ campaign, contentItems, sessions }) {
    const [activeAgent, setActiveAgent] = useState('social');
    const [showItems, setShowItems] = useState(true);

    const statusColors = {
        draft: 'bg-amber-50 text-amber-700',
        approved: 'bg-blue-50 text-blue-700',
        scheduled: 'bg-purple-50 text-purple-700',
        published: 'bg-green-50 text-green-700',
    };

    const typeLabels = {
        social_post: 'Social Post',
        blog_post: 'Blog Post',
        seo_report: 'SEO Report',
        brainstorm_note: 'Brainstorm Note',
    };

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
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                            <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink">Knowledge Base</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-6 py-8">
                    {/* Campaign Header */}
                    <div className="mb-8">
                        <Link href="/dashboard" className="text-sm text-muted hover:text-ink mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <div className="flex items-center gap-3 mt-2">
                            <h1 className="text-2xl font-bold">{campaign.title}</h1>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusColors[campaign.status] || 'bg-slate-100 text-slate-700'}`}>
                                {campaign.status}
                            </span>
                        </div>
                        {campaign.description && (
                            <p className="mt-1 text-sm text-muted">{campaign.description}</p>
                        )}
                        {campaign.goal && (
                            <p className="mt-1 text-sm text-slate-600">Goal: {campaign.goal}</p>
                        )}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-5">
                        {/* Left: Agent Chat */}
                        <div className="lg:col-span-3">
                            {/* Agent Tabs */}
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
                                                <p className="text-xs text-slate-600 line-clamp-3">{item.content}</p>
                                                <div className="mt-2 flex items-center gap-2">
                                                    <button
                                                        onClick={() => navigator.clipboard.writeText(item.content)}
                                                        className="text-xs text-muted hover:text-ink underline"
                                                    >
                                                        Copy
                                                    </button>
                                                    {item.platform && (
                                                        <span className="text-xs text-muted">{item.platform}</span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Sessions */}
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
