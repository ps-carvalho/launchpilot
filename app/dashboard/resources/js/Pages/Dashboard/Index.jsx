import { Head } from '@inertiajs/react';

export default function DashboardIndex({ user, workspaces, campaigns }) {
    return (
        <>
            <Head title="Dashboard — LaunchPilot" />
            <div className="min-h-screen">
                <header className="border-b border-line bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <a href="/" className="text-lg font-bold tracking-tight">LaunchPilot AI</a>
                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                {workspaces.length > 0 ? workspaces[0].name : 'Workspace'}
                            </span>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="text-sm text-muted">{user?.name}</span>
                            <a href="/logout" className="text-sm font-semibold text-muted hover:text-ink">
                                Log out
                            </a>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold">Campaigns</h1>
                        <button className="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/90">
                            + New campaign
                        </button>
                    </div>

                    {campaigns.length === 0 ? (
                        <div className="mt-12 flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-white py-20">
                            <div className="text-4xl mb-4">📋</div>
                            <h2 className="text-lg font-bold">No campaigns yet</h2>
                            <p className="mt-1 text-sm text-muted">Create your first campaign to start planning your marketing.</p>
                            <button className="mt-6 rounded-lg bg-ink px-5 py-2.5 text-sm font-bold text-white hover:bg-ink/90">
                                Create your first campaign
                            </button>
                        </div>
                    ) : (
                        <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {campaigns.map((campaign) => (
                                <div key={campaign.id} className="rounded-xl border border-line bg-white p-5">
                                    <div className="flex items-center justify-between">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                            campaign.status === 'active' ? 'bg-green-50 text-green-700' :
                                            campaign.status === 'completed' ? 'bg-slate-100 text-slate-700' :
                                            'bg-amber-50 text-amber-700'
                                        }`}>
                                            {campaign.status}
                                        </span>
                                    </div>
                                    <h3 className="mt-3 text-base font-bold">{campaign.title}</h3>
                                    {campaign.goal && <p className="mt-1 text-xs text-muted">{campaign.goal}</p>}
                                    <div className="mt-3 flex flex-wrap gap-1">
                                        {(campaign.channels || []).map((channel) => (
                                            <span key={channel} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                {channel}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
