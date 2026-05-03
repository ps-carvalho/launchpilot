import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function OnboardingIndex({ errors }) {
    const [url, setUrl] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        setLoading(true);
        router.post('/onboarding', { url });
    };

    const errorMessages = [];
    for (const key in errors) {
        if (Array.isArray(errors[key])) {
            errorMessages.push(...errors[key]);
        } else {
            errorMessages.push(errors[key]);
        }
    }

    return (
        <>
            <Head title="Welcome — LaunchPilot" />
            <div className="min-h-screen bg-paper flex items-center justify-center px-4">
                <div className="w-full max-w-lg">
                    <div className="text-center mb-8">
                        <div className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-ink text-white text-lg font-bold mb-4 shadow-elevation-2">LP</div>
                        <h1 className="text-3xl font-bold tracking-tight text-ink">Welcome to LaunchPilot</h1>
                        <p className="mt-2 text-muted">
                            Let's build your knowledge base so our AI agents can help you market your business.
                        </p>
                    </div>

                    <div className="rounded-xl border border-line/60 bg-white p-8 shadow-elevation-1">
                        <div className="mb-6">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-ink text-white text-sm font-bold shadow-sm">1</div>
                                <h2 className="text-lg font-bold">Add your website</h2>
                            </div>
                            <p className="text-sm text-muted ml-11">
                                We'll scan your site to understand your business, products, and messaging.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="ml-11">
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={url}
                                    onChange={(e) => setUrl(e.target.value)}
                                    placeholder="https://yoursite.com"
                                    className="flex-1 rounded-lg border border-line bg-paper px-4 py-2.5 text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 transition-all"
                                    required
                                />
                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90 shadow-elevation-1 hover:shadow-elevation-2 transition-all hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                                >
                                    {loading ? 'Scanning...' : 'Scan'}
                                </button>
                            </div>

                            {errorMessages.length > 0 && (
                                <div className="mt-3 rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700 shadow-sm">
                                    {errorMessages.map((msg, i) => (
                                        <p key={i}>{msg}</p>
                                    ))}
                                </div>
                            )}
                        </form>

                        <div className="mt-8 border-t border-line/60 pt-6">
                            <div className="flex items-center gap-3 opacity-40">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-500 text-sm font-bold">2</div>
                                <div>
                                    <h3 className="text-sm font-medium text-slate-700">Upload documents (coming next)</h3>
                                    <p className="text-xs text-slate-400">PDFs, brand guidelines, product docs</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p className="mt-6 text-center text-xs text-muted">
                        You can skip this and add your knowledge base later from the dashboard.
                    </p>
                </div>
            </div>
        </>
    );
}
