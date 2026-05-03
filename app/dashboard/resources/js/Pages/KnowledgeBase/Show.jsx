import { Head, Link } from '@inertiajs/react';

export default function KnowledgeBaseShow({ document }) {
    const meta = document.metadata || {};

    return (
        <>
            <Head title={`${document.original_name || 'Document'} — Knowledge Base`} />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                            <Link href="/knowledge-base" className="text-sm font-semibold text-ink">Knowledge Base</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-6 py-10">
                    <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink mb-6 inline-block">
                        ← Back to Knowledge Base
                    </Link>

                    <div className="rounded-xl border border-line bg-white p-8">
                        <div className="flex items-start gap-3">
                            <span className="text-3xl">{document.source_url ? '🌐' : '📄'}</span>
                            <div>
                                <h1 className="text-xl font-bold">{document.original_name || 'Untitled Document'}</h1>
                                {document.source_url && (
                                    <a
                                        href={document.source_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-sm text-ink hover:underline"
                                    >
                                        {document.source_url}
                                    </a>
                                )}
                            </div>
                        </div>

                        {meta.title && (
                            <div className="mt-6">
                                <h2 className="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Title</h2>
                                <p className="text-sm text-slate-800">{meta.title}</p>
                            </div>
                        )}

                        {meta.description && (
                            <div className="mt-4">
                                <h2 className="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Description</h2>
                                <p className="text-sm text-slate-800">{meta.description}</p>
                            </div>
                        )}

                        <div className="mt-6">
                            <h2 className="text-xs font-semibold uppercase tracking-wider text-muted mb-2">Content</h2>
                            <div className="rounded-lg bg-slate-50 p-4 max-h-[600px] overflow-y-auto">
                                <pre className="text-sm text-slate-700 whitespace-pre-wrap font-mono leading-relaxed">
                                    {document.raw_text}
                                </pre>
                            </div>
                        </div>

                        <div className="mt-6 flex items-center justify-between text-xs text-muted">
                            <span>Added {new Date(document.created_at).toLocaleString()}</span>
                            <span>ID: {document.id}</span>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
