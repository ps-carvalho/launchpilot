import { useState, useRef, useEffect } from 'react';

export default function AgentChat({
    campaignId,
    activeMode,
    setActiveMode,
    modes,
    prompts,
    remainingRuns,
    onRemainingRunsChange,
    isPro,
    agentModels,
    modalityModels,
    onMediaAsset,
    onPollVideo,
}) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [sessionLoaded, setSessionLoaded] = useState(false);
    const [currentModel, setCurrentModel] = useState(null);
    const [selectedPrompt, setSelectedPrompt] = useState(null);
    const [selectedModel, setSelectedModel] = useState('');
    const messagesEndRef = useRef(null);

    const modeColors = {
        text: 'bg-blue-50/80 border-blue-100',
        image: 'bg-purple-50/80 border-purple-100',
        video: 'bg-rose-50/80 border-rose-100',
    };

    const modeAccentColors = {
        text: 'text-blue-700',
        image: 'text-purple-700',
        video: 'text-rose-700',
    };

    useEffect(() => {
        loadSession();
    }, [campaignId, activeMode]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    useEffect(() => {
        // Reset model selection when mode changes
        setSelectedModel('');
        setSelectedPrompt(null);
        setInput('');
    }, [activeMode]);

    const loadSession = async () => {
        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${activeMode}/session`, {
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.messages) {
                setMessages(data.messages);
            } else {
                setMessages([]);
            }
        } catch (e) {
            console.error('Failed to load session', e);
        } finally {
            setSessionLoaded(true);
        }
    };

    const resolveModel = () => {
        if (isPro && selectedModel) {
            return selectedModel;
        }
        // Free tier: use defaults
        const defaults = {
            text: 'meta-llama/llama-3.3-70b-instruct',
            image: 'black-forest-labs/flux-2-schnell',
            video: 'google/veo-3.1-lite',
        };
        return defaults[activeMode] || defaults.text;
    };

    const handleSend = async (e) => {
        e.preventDefault();
        if (!input.trim() || loading) return;

        const userMsg = input.trim();
        setInput('');
        setLoading(true);

        const userMessageObj = { role: 'user', content: userMsg, timestamp: new Date().toISOString() };
        if (activeMode !== 'image') {
            setMessages((prev) => [...prev, userMessageObj]);
        }

        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${activeMode}/chat`, {
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

                // Handle image generation results
                if (activeMode === 'image' && data.message.images?.length > 0) {
                    // Save images to media assets
                    for (const imgData of data.message.images) {
                        await saveImage(imgData, userMsg);
                    }
                }

                // Handle video job submission
                if (activeMode === 'video' && data.message.asset_id) {
                    // Trigger initial poll after a delay
                    setTimeout(() => onPollVideo(data.message.asset_id), 5000);
                }

                // Handle free-tier video script
                if (activeMode === 'video' && data.message.upgrade_cta) {
                    // Just show the text response; the CTA is in the message
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

    const saveImage = async (base64Data, prompt) => {
        try {
            // Convert base64 to file and upload
            const byteString = atob(base64Data.split(',')[1] || base64Data);
            const mimeType = base64Data.match(/data:([^;]+);/)?.[1] || 'image/png';
            const ext = mimeType.split('/')[1] || 'png';
            const ab = new ArrayBuffer(byteString.length);
            const ia = new Uint8Array(ab);
            for (let i = 0; i < byteString.length; i++) {
                ia[i] = byteString.charCodeAt(i);
            }
            const blob = new Blob([ab], { type: mimeType });
            const formData = new FormData();
            formData.append('image', blob, `generated.${ext}`);
            formData.append('prompt', prompt);

            const res = await fetch(`/api/campaigns/${campaignId}/media/upload`, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const data = await res.json();
            if (data.asset) {
                onMediaAsset(data.asset);
            }
        } catch (e) {
            console.error('Failed to save image', e);
        }
    };

    const handleSave = async (content) => {
        try {
            const res = await fetch(`/api/campaigns/${campaignId}/agents/${activeMode}/save`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content, platform: activeMode === 'text' ? 'general' : null }),
            });
            const data = await res.json();
            if (data.message) {
                alert(data.message);
            }
        } catch (err) {
            alert('Failed to save content.');
        }
    };

    const applyPrompt = (prompt) => {
        setSelectedPrompt(prompt);
        setInput(prompt.template);
    };

    const modelOptions = modalityModels?.[activeMode] || [];
    const currentMode = modes.find((m) => m.key === activeMode);

    if (!sessionLoaded) {
        return (
            <div className="flex h-96 items-center justify-center rounded-xl bg-white border border-line/60 shadow-elevation-1">
                <div className="text-sm text-muted animate-pulse">Loading agent...</div>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-[700px] rounded-xl border border-line/60 bg-white shadow-elevation-1">
            {/* Header */}
            <div className={`px-4 py-3 border-b border-line/60 rounded-t-xl ${modeColors[activeMode] || 'bg-slate-50/80'}`}>
                <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                        <span className="text-lg">🤖</span>
                        <div>
                            <h3 className={`text-sm font-bold ${modeAccentColors[activeMode] || 'text-slate-700'}`}>LaunchPilot Agent</h3>
                            <p className="text-xs text-muted">{currentMode?.description}</p>
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
                </div>

                {/* Output Mode Selector */}
                <div className="flex gap-2 mt-2">
                    {modes.map((mode) => (
                        <button
                            key={mode.key}
                            onClick={() => setActiveMode(mode.key)}
                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                                activeMode === mode.key
                                    ? 'bg-ink text-white shadow-elevation-1'
                                    : 'bg-white/60 text-slate-600 hover:bg-white border border-line/30'
                            }`}
                        >
                            <span>{mode.icon}</span>
                            {mode.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Prompt Templates */}
            {prompts.length > 0 && (
                <div className="px-4 py-2 border-b border-line/60 bg-paper/50">
                    <div className="flex flex-wrap gap-1.5">
                        {prompts.map((prompt) => (
                            <button
                                key={prompt.id}
                                onClick={() => applyPrompt(prompt)}
                                className={`text-xs px-2.5 py-1 rounded-full border transition-all ${
                                    selectedPrompt?.id === prompt.id
                                        ? 'bg-ink text-white border-ink'
                                        : 'bg-white text-slate-600 border-line/40 hover:border-accent'
                                }`}
                            >
                                {prompt.label}
                            </button>
                        ))}
                        {!isPro && (
                            <span className="text-[10px] text-muted self-center ml-1">Pro unlocks more</span>
                        )}
                    </div>
                </div>
            )}

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-4xl mb-3">{currentMode?.icon}</div>
                        <p className="text-sm font-medium text-slate-700">
                            {activeMode === 'text' && 'Start a conversation with the agent.'}
                            {activeMode === 'image' && 'Describe the image you want to generate.'}
                            {activeMode === 'video' && 'Describe the video you want to create.'}
                        </p>
                        <p className="text-xs text-muted mt-1">
                            {activeMode === 'text' && 'Example: "Write me 5 LinkedIn posts about my product"'}
                            {activeMode === 'image' && 'Example: "A modern SaaS dashboard hero image, blue gradient, clean UI"'}
                            {activeMode === 'video' && 'Example: "30-second explainer for a fitness app, energetic tone"'}
                        </p>
                    </div>
                )}

                {messages.map((msg, i) => (
                    <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[90%] rounded-xl px-4 py-2.5 text-sm shadow-sm ${
                            msg.role === 'user'
                                ? 'bg-ink text-white'
                                : 'bg-paper border border-line/40 text-slate-800'
                        }`}>
                            <div className="whitespace-pre-wrap">{msg.content}</div>

                            {/* Display images inline */}
                            {msg.images && msg.images.length > 0 && (
                                <div className="mt-3 space-y-2">
                                    {msg.images.map((img, idx) => (
                                        <img
                                            key={idx}
                                            src={img.startsWith('data:') ? img : `data:image/png;base64,${img}`}
                                            alt={`Generated ${idx + 1}`}
                                            className="max-w-full rounded-lg border border-line/40"
                                        />
                                    ))}
                                </div>
                            )}

                            {/* Upgrade CTA for free video */}
                            {msg.upgrade_cta && (
                                <div className="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-100">
                                    <p className="text-xs text-amber-800 font-medium">💡 Pro Feature</p>
                                    <p className="text-xs text-amber-700 mt-1">
                                        Upgrade to Pro to generate actual videos with AI. Pro supports Kling, Veo, Wan, and Seedance models.
                                    </p>
                                </div>
                            )}

                            {/* Video job notification */}
                            {msg.job_id && (
                                <div className="mt-3 p-3 rounded-lg bg-blue-50 border border-blue-100">
                                    <p className="text-xs text-blue-800 font-medium">🎬 Video Job Started</p>
                                    <p className="text-xs text-blue-700 mt-1">
                                        Your video is being generated. Check the Media panel for progress.
                                    </p>
                                </div>
                            )}

                            {msg.role === 'assistant' && activeMode === 'text' && (
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
                                {activeMode === 'image' ? 'Generating image' : activeMode === 'video' ? 'Submitting video job' : 'Agent is thinking'}
                            </span>
                        </div>
                    </div>
                )}
                <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <form onSubmit={handleSend} className="border-t border-line/60 p-3">
                <div className="space-y-2">
                    {/* Model Selector (Pro only) */}
                    {isPro && modelOptions.length > 0 && (
                        <div className="flex items-center gap-2">
                            <label className="text-[10px] font-bold uppercase tracking-wider text-muted">Model</label>
                            <select
                                value={selectedModel}
                                onChange={(e) => setSelectedModel(e.target.value)}
                                className="text-xs rounded-md border border-line bg-paper px-2 py-1 focus:border-accent focus:outline-none"
                            >
                                <option value="">Default ({resolveModel().split('/').pop()})</option>
                                {modelOptions.map((m) => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                            {currentModel && (
                                <span className="text-[10px] text-muted ml-auto">
                                    Using: {currentModel.split('/').pop()}
                                </span>
                            )}
                        </div>
                    )}
                    <div className="flex gap-2">
                        <textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder={
                                activeMode === 'text' ? 'Ask the agent...' :
                                activeMode === 'image' ? 'Describe the image you want...' :
                                'Describe the video you want...'
                            }
                            className="flex-1 rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all resize-none"
                            rows={2}
                            disabled={loading}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    handleSend(e);
                                }
                            }}
                        />
                        <button
                            type="submit"
                            disabled={loading || !input.trim()}
                            className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0 self-end"
                        >
                            {activeMode === 'image' ? 'Generate' : activeMode === 'video' ? 'Create' : 'Send'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}
