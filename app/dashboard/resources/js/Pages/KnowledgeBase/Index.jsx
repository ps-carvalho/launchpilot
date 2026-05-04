import { Head, Link, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import AppShell from '../../Components/AppShell';

function SearchPanel() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleSearch = async (e) => {
        e.preventDefault();
        if (!query.trim()) return;

        setLoading(true);
        setError(null);

        try {
            const res = await fetch(`/api/knowledge-base/search?q=${encodeURIComponent(query)}`, {
                credentials: 'same-origin',
            });
            const data = await res.json();
            setResults(data.results || []);
            if (data.error) setError(data.error);
        } catch (err) {
            setError('Search failed.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="mb-8 rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1">
            <h3 className="text-sm font-bold mb-3">🔍 Test Vector Search</h3>
            <form onSubmit={handleSearch} className="flex gap-2">
                <input
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Ask something about your knowledge base..."
                    className="flex-1 rounded-lg border border-line bg-paper px-4 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                />
                <button
                    type="submit"
                    disabled={loading}
                    className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50"
                >
                    {loading ? '...' : 'Search'}
                </button>
            </form>

            {error && (
                <p className="mt-2 text-xs text-amber-600">{error}</p>
            )}

            {results.length > 0 && (
                <div className="mt-4 space-y-3">
                    {results.map((r) => (
                        <div key={r.id} className="rounded-lg bg-paper border border-line/40 p-3 shadow-sm hover:shadow-elevation-1 transition-all">
                            <div className="flex items-center justify-between mb-1">
                                <span className="text-xs font-semibold text-slate-700">{r.original_name}</span>
                                <span className="text-xs text-muted bg-white px-1.5 py-0.5 rounded-full border border-line/30">{Math.round((parseFloat(r.similarity) || 0) * 100)}% match</span>
                            </div>
                            <p className="text-xs text-slate-600 line-clamp-3">{r.chunk_text}</p>
                        </div>
                    ))}
                </div>
            )}

            {results.length === 0 && !loading && !error && query && (
                <p className="mt-2 text-xs text-muted">No results. Make sure documents have embeddings (requires OpenRouter API key).</p>
            )}
        </div>
    );
}

export default function KnowledgeBaseIndex({ documents, workspace, flash }) {
    const [dragActive, setDragActive] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [url, setUrl] = useState('');
    const [scraping, setScraping] = useState(false);
    const inputRef = useRef(null);

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleUpload(e.dataTransfer.files[0]);
        }
    };

    const handleChange = (e) => {
        e.preventDefault();
        if (e.target.files && e.target.files[0]) {
            handleUpload(e.target.files[0]);
        }
    };

    const handleScrape = (e) => {
        e.preventDefault();
        if (!url.trim()) return;
        setScraping(true);
        router.post('/knowledge-base/scrape', { url: url.trim() }, {
            onFinish: () => {
                setScraping(false);
                setUrl('');
            },
        });
    };

    const handleUpload = (file) => {
        const allowed = ['text/plain', 'text/markdown', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowed.includes(file.type)) {
            alert('Please upload a TXT, MD, PDF, or DOCX file.');
            return;
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('document', file);

        router.post('/knowledge-base/upload', formData, {
            onFinish: () => setUploading(false),
        });
    };

    const flashMessages = [];
    for (const key in flash) {
        if (Array.isArray(flash[key])) {
            flashMessages.push(...flash[key].map(m => ({ type: key, text: m })));
        } else if (flash[key]) {
            flashMessages.push({ type: key, text: flash[key] });
        }
    }

    return (
        <>
            <Head title="Knowledge Base — LaunchPilot" />
            <AppShell>
                {flashMessages.length > 0 && (
                    <div className="mb-6 space-y-2">
                        {flashMessages.map((msg, i) => (
                            <div key={i} className={`rounded-xl px-4 py-3 text-sm shadow-elevation-1 ${msg.type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'}`}>
                                {msg.text}
                            </div>
                        ))}
                    </div>
                )}

                <SearchPanel />

                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">Knowledge Base</h1>
                        <p className="mt-1 text-sm text-muted">Documents and context that power your AI agents.</p>
                    </div>
                    <span className="text-sm text-muted bg-white px-3 py-1 rounded-full border border-line/60 shadow-sm">{documents.length} document{documents.length !== 1 ? 's' : ''}</span>
                </div>

                {/* Upload & URL Area */}
                <div className="grid gap-4 md:grid-cols-2 mb-8">
                    <div
                        className={`rounded-xl border-2 border-dashed p-8 text-center transition-all cursor-pointer shadow-elevation-1 hover:shadow-elevation-2 ${
                            dragActive ? 'border-accent bg-blue-50/30' : 'border-line/60 bg-white hover:border-accent/40'
                        } ${uploading ? 'opacity-50 pointer-events-none' : ''}`}
                        onDragEnter={handleDrag}
                        onDragLeave={handleDrag}
                        onDragOver={handleDrag}
                        onDrop={handleDrop}
                        onClick={() => inputRef.current?.click()}
                    >
                        <div className="text-3xl mb-3">📤</div>
                        <p className="text-sm font-semibold">
                            {uploading ? 'Uploading and processing...' : 'Drop a file here, or click to browse'}
                        </p>
                        <p className="mt-1 text-xs text-muted">Supports TXT, MD, PDF, and DOCX files</p>
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            accept=".txt,.md,.pdf,.docx"
                            onChange={handleChange}
                        />
                    </div>

                    <div className="rounded-xl border border-line/60 bg-white p-6 shadow-elevation-1 flex flex-col justify-center">
                        <div className="text-3xl mb-3 text-center">🌐</div>
                        <p className="text-sm font-semibold text-center mb-3">Add a website</p>
                        <form onSubmit={handleScrape} className="flex gap-2">
                            <input
                                type="url"
                                value={url}
                                onChange={(e) => setUrl(e.target.value)}
                                placeholder="https://example.com"
                                className="flex-1 rounded-lg border border-line bg-paper px-3 py-2 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                disabled={scraping}
                            />
                            <button
                                type="submit"
                                disabled={scraping || !url.trim()}
                                className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:pointer-events-none"
                            >
                                {scraping ? '...' : 'Add'}
                            </button>
                        </form>
                    </div>
                </div>

                {documents.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-20 shadow-elevation-1">
                        <div className="text-4xl mb-4">📚</div>
                        <h2 className="text-lg font-bold">No documents yet</h2>
                        <p className="mt-1 text-sm text-muted">Upload a document or add a website above to get started.</p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {documents.map((doc) => {
                            let meta = {};
                            try {
                                meta = JSON.parse(doc.metadata || '{}');
                            } catch {}
                            return (
                                <div
                                    key={doc.id}
                                    className="rounded-xl border border-line/60 bg-white p-5 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 group"
                                >
                                    <Link href={`/knowledge-base/${doc.id}`} className="block">
                                        <div className="flex items-center gap-2">
                                            <span className="text-2xl flex-shrink-0">{doc.source_url ? '🌐' : '📄'}</span>
                                            <div className="min-w-0">
                                                <h3 className="text-sm font-bold truncate">{doc.original_name || 'Untitled'}</h3>
                                                {doc.source_url && (
                                                    <p className="text-xs text-muted truncate">{doc.source_url}</p>
                                                )}
                                            </div>
                                        </div>
                                        {meta.title && (
                                            <p className="mt-2 text-xs text-slate-600 line-clamp-2">{meta.title}</p>
                                        )}
                                    </Link>
                                    <div className="mt-3 flex items-center justify-between">
                                        <span className="text-xs text-muted">
                                            {new Date(doc.created_at).toLocaleDateString()}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <Link href={`/knowledge-base/${doc.id}`} className="text-xs font-medium text-ink hover:text-accent transition-colors">
                                                View →
                                            </Link>
                                            <button
                                                onClick={() => {
                                                    if (confirm('Delete this document?')) {
                                                        router.post(`/knowledge-base/${doc.id}/delete`);
                                                    }
                                                }}
                                                className="text-xs text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </AppShell>
        </>
    );
}
