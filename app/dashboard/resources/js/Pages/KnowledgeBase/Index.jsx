import { Head, Link } from '@inertiajs/react';

export default function KnowledgeBaseIndex({ documents, workspace }) {
    return (
        <>
            <Head title="Knowledge Base — LaunchPilot" />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-lg font-bold tracking-tight">LaunchPilot AI</Link>
                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                {workspace?.name || 'Workspace'}
                            </span>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link href="/dashboard" className="text-sm text-muted hover:text-ink">Dashboard</Link>
                            <Link href="/knowledge-base" className="text-sm font-semibold text-ink">Knowledge Base</Link>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Knowledge Base</h1>
                            <p className="mt-1 text-sm text-muted">Documents and context that power your AI agents.</p>
                        </div>
                        <span className="text-sm text-muted">{documents.length} document{documents.length !== 1 ? 's' : ''}</span>
                    </div>

                    {documents.length === 0 ? (
                        <div className="mt-12 flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-20">
                            <div className="text-4xl mb-4">📚</div>
                            <h2 className="text-lg font-bold">No documents yet</h2>
                            <p className="mt-1 text-sm text-muted">Add your website or upload documents to get started.</p>
                            <Link href="/onboarding" className="mt-6 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90">
                                Add your website
                            </Link>
                        </div>
                    ) : (
                        <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {documents.map((doc) => {
                                const meta = JSON.parse(doc.metadata || '{}');
                                return (
                                    <Link
                                        key={doc.id}
                                        href={`/knowledge-base/${doc.id}`}
                                        className="rounded-xl border border-line bg-white p-5 hover:border-ink/30 transition-colors"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="text-2xl">{doc.source_url ? '🌐' : '📄'}</span>
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
                                        <div className="mt-3 flex items-center justify-between">
                                            <span className="text-xs text-muted">
                                                {new Date(doc.created_at).toLocaleDateString()}
                                            </span>
                                            <span className="text-xs font-medium text-ink">View →</span>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
