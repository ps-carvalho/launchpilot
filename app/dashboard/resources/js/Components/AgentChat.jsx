import { useState, useRef, useEffect } from 'react';

export default function AgentChat({ campaignId, agentType, agentLabel, agentIcon }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [sessionLoaded, setSessionLoaded] = useState(false);
    const messagesEndRef = useRef(null);

    const agentColors = {
        social: 'bg-blue-50 border-blue-200 text-blue-800',
        content: 'bg-green-50 border-green-200 text-green-800',
        seo: 'bg-purple-50 border-purple-200 text-purple-800',
        brainstorm: 'bg-amber-50 border-amber-200 text-amber-800',
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
            <div className="flex h-96 items-center justify-center">
                <div className="text-sm text-muted">Loading agent...</div>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-[600px] rounded-xl border border-line bg-white">
            <div className={`px-4 py-3 border-b border-line rounded-t-xl ${agentColors[agentType]?.split(' ')[0] || 'bg-slate-50'}`}>
                <div className="flex items-center gap-2">
                    <span className="text-lg">{agentIcon}</span>
                    <div>
                        <h3 className="text-sm font-bold">{agentLabel}</h3>
                        <p className="text-xs text-muted">Conversational AI agent</p>
                    </div>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-3xl mb-3">{agentIcon}</div>
                        <p className="text-sm text-muted">Start a conversation with the {agentLabel}.</p>
                        <p className="text-xs text-muted mt-1">Example: "Write me 5 LinkedIn posts about my product"</p>
                    </div>
                )}

                {messages.map((msg, i) => (
                    <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[85%] rounded-lg px-4 py-2.5 text-sm ${
                            msg.role === 'user'
                                ? 'bg-ink text-white'
                                : 'bg-slate-100 text-slate-800'
                        }`}>
                            <div className="whitespace-pre-wrap">{msg.content}</div>
                            {msg.role === 'assistant' && (
                                <div className="mt-2 flex gap-2">
                                    <button
                                        onClick={() => navigator.clipboard.writeText(msg.content)}
                                        className="text-xs text-muted hover:text-ink underline"
                                    >
                                        Copy
                                    </button>
                                    <button
                                        onClick={() => handleSave(msg.content)}
                                        className="text-xs text-ink hover:text-ink/80 underline font-medium"
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
                        <div className="bg-slate-100 rounded-lg px-4 py-2.5 text-sm text-muted">
                            <span className="animate-pulse">Agent is thinking...</span>
                        </div>
                    </div>
                )}
                <div ref={messagesEndRef} />
            </div>

            <form onSubmit={handleSend} className="border-t border-line p-3">
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder={`Ask the ${agentLabel}...`}
                        className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink"
                        disabled={loading}
                    />
                    <button
                        type="submit"
                        disabled={loading || !input.trim()}
                        className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 disabled:opacity-50"
                    >
                        Send
                    </button>
                </div>
            </form>
        </div>
    );
}
