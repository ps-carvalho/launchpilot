import { useState, useRef, useEffect } from 'react';

export default function AgentChat({ campaignId, agentType, agentLabel, agentIcon, remainingRuns, onRemainingRunsChange, isPro, agentModels }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [sessionLoaded, setSessionLoaded] = useState(false);
    const [currentModel, setCurrentModel] = useState(null);
    const messagesEndRef = useRef(null);

    const agentColors = {
        social: 'bg-blue-50/80 border-blue-100',
        content: 'bg-green-50/80 border-green-100',
        seo: 'bg-purple-50/80 border-purple-100',
        media: 'bg-rose-50/80 border-rose-100',
    };

    const agentAccentColors = {
        social: 'text-blue-700',
        content: 'text-green-700',
        seo: 'text-purple-700',
        media: 'text-rose-700',
    };

    useEffect(() => {
        loadSession();
    }, [campaignId, agentType]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const loadSession = async () => {
        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${agentType}/session`, {
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.messages) {
                setMessages(data.messages);
            }
        } catch (e) {
            console.error('Failed to load session', e);
        } finally {
            setSessionLoaded(true);
        }
    };

    const handleSend = async (e) => {
        e.preventDefault();
        if (!input.trim() || loading) return;

        const userMsg = input.trim();
        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: userMsg, timestamp: new Date().toISOString() }]);
        setLoading(true);

        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${agentType}/chat`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: userMsg }),
            });
            const data = await res.json();

            if (data.message) {
                setMessages((prev) => [...prev, data.message]);
                if (typeof data.remaining_runs === 'number' && onRemainingRunsChange) {
                    onRemainingRunsChange(data.remaining_runs);
                }
                if (data.model) {
                    setCurrentModel(data.model);
                }
            } else if (data.error) {
                setMessages((prev) => [...prev, { role: 'assistant', content: `Error: ${data.error}` }]);
            }
        } catch (err) {
            setMessages((prev) => [...prev, { role: 'assistant', content: 'Error: Could not reach the agent.' }]);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (content) => {
        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${agentType}/save`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content, platform: agentType === 'social' ? 'linkedin' : null }),
            });
            const data = await res.json();
            if (data.message) {
                alert(data.message);
            }
        } catch (err) {
            alert('Failed to save content.');
        }
    };

    if (!sessionLoaded) {
        return (
            <div className="flex h-96 items-center justify-center rounded-xl bg-white border border-line/60 shadow-elevation-1">
                <div className="text-sm text-muted animate-pulse">Loading agent...</div>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-[600px] rounded-xl border border-line/60 bg-white shadow-elevation-1">
            <div className={`px-4 py-3 border-b border-line/60 rounded-t-xl ${agentColors[agentType] || 'bg-slate-50/80'}`}>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-lg">{agentIcon}</span>
                        <div>
                            <h3 className={`text-sm font-bold ${agentAccentColors[agentType] || 'text-slate-700'}`}>{agentLabel}</h3>
                            <p className="text-xs text-muted">Conversational AI agent</p>
                        </div>
                    </div>
                    {remainingRuns >= 0 && (
                        <span className="text-xs font-medium text-slate-500 bg-white/80 px-2 py-0.5 rounded-full border border-line/40">
                            {remainingRuns} run{remainingRuns !== 1 ? 's' : ''} left today
                        </span>
                    )}
                    {remainingRuns === -1 && (
                        <span className="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full border border-green-100">Unlimited</span>
                    )}
                    {currentModel && (
                        <span className="text-xs font-medium text-slate-400 bg-white/60 px-2 py-0.5 rounded-full border border-line/30" title="AI model powering this agent">
                            {currentModel.split('/').pop()}
                        </span>
                    )}
                </div>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-4xl mb-3">{agentIcon}</div>
                        <p className="text-sm font-medium text-slate-700">Start a conversation with the {agentLabel}.</p>
                        <p className="text-xs text-muted mt-1">Example: &ldquo;Write me 5 LinkedIn posts about my product&rdquo;</p>
                    </div>
                )}

                {messages.map((msg, i) => (
                    <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[85%] rounded-xl px-4 py-2.5 text-sm shadow-sm ${
                            msg.role === 'user'
                                ? 'bg-ink text-white'
                                : 'bg-paper border border-line/40 text-slate-800'
                        }`}>
                            <div className="whitespace-pre-wrap">{msg.content}</div>
                            {msg.role === 'assistant' && (
                                <div className="mt-2 flex gap-3">
                                    <button
                                        onClick={() => navigator.clipboard.writeText(msg.content)}
                                        className="text-xs text-muted hover:text-ink underline transition-colors"
                                    >
                                        Copy
                                    </button>
                                    <button
                                        onClick={() => handleSave(msg.content)}
                                        className="text-xs text-accent hover:text-accent/80 underline font-medium transition-colors"
                                    >
                                        Save to campaign
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                ))}

                {loading && (
                    <div className="flex justify-start">
                        <div className="bg-paper border border-line/40 rounded-xl px-4 py-2.5 text-sm text-muted shadow-sm">
                            <span className="animate-pulse flex items-center gap-2">
                                <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent animate-bounce" style={{ animationDelay: '0ms' }} />
                                <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent animate-bounce" style={{ animationDelay: '150ms' }} />
                                <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent animate-bounce" style={{ animationDelay: '300ms' }} />
                                Agent is thinking
                            </span>
                        </div>
                    </div>
                )}
                <div ref={messagesEndRef} />
            </div>

            <form onSubmit={handleSend} className="border-t border-line/60 p-3">
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder={`Ask the ${agentLabel}...`}
                        className="flex-1 rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                        disabled={loading}
                    />
                    <button
                        type="submit"
                        disabled={loading || !input.trim()}
                        className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                    >
                        Send
                    </button>
                </div>
            </form>
        </div>
    );
}
