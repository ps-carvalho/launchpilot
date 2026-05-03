import { Head, Link } from '@inertiajs/react';
import AppShell from '../../Components/AppShell';

export default function KnowledgeBaseShow({ document }) {
    const meta = document.metadata || {};

    return (
        <>
            <Head title={`${document.original_name || 'Document'} — Knowledge Base`} />
            <AppShell>
                <Link href="/knowledge-base" className="text-sm text-muted hover:text-ink transition-colors inline-flex items-center gap-1 mb-4">
                    ← Back to Knowledge Base
                </Link>

                <div className="rounded-xl bg-white p-6 border border-line/60 shadow-elevation-1">
                    <div className="flex items-start gap-3">
                        <span className="text-3xl flex-shrink-0">{document.source_url ? '🌐' : '📄'}</span>
                        <div className="min-w-0">
                            <h1 className="text-xl font-bold">{document.original_name || 'Untitled Document'}</h1>
                            {document.source_url && (
                                <a
                                    href={document.source_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm text-accent hover:underline"
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
                        <div className="rounded-lg bg-paper border border-line/40 p-4 max-h-[600px] overflow-y-auto shadow-sm">
                            <pre className="text-sm text-slate-700 whitespace-pre-wrap font-mono leading-relaxed">
                                {document.raw_text}
                            </pre>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-between text-xs text-muted">
                        <span>Added {new Date(document.created_at).toLocaleString()}</span>
                        <span className="bg-paper px-2 py-0.5 rounded-full border border-line/30">ID: {document.id}</span>
                    </div>
                </div>
            </AppShell>
        </>
    );
}
