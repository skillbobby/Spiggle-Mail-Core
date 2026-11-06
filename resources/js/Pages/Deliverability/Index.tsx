import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { HealthGaugeCard } from '@/Components/Deliverability/HealthGaugeCard';
import { ShieldCheck, ShieldAlert, Ban, RefreshCw, ExternalLink } from 'lucide-react';
import { router } from '@inertiajs/react';

interface SuppressedContact {
    id: string;
    emailHash: string;
    reason: string;
    createdAt: string;
}

interface DeliverabilityProps {
    currentNewsletter: {
        id: string;
        name: string;
        slug: string;
        fromEmail: string;
    };
    health: {
        bounceRate: number;
        complaintRate: number;
        totalSent30d: number;
        totalBounced30d: number;
        totalComplaints30d: number;
        disposableDomainsBlocked: number;
    };
    suppressions: SuppressedContact[];
}

export default function DeliverabilityIndex({ currentNewsletter, health, suppressions }: DeliverabilityProps) {
    const handleSyncBlocklist = () => {
        router.post('/deliverability/sync-blocklist', {}, { preserveScroll: true });
    };

    return (
        <DashboardLayout currentNewsletter={currentNewsletter}>
            <div className="space-y-8">
                {/* Header */}
                <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-6">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <ShieldCheck className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                            Deliverability Health & Anti-Spam Telemetry
                        </h1>
                        <p className="text-sm text-zinc-400 mt-1">
                            Real-time reputation metrics monitored against Amazon SES, Google, and Yahoo probation thresholds.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <a
                            href="http://localhost:8025"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 text-zinc-200 text-xs font-medium rounded-lg transition"
                        >
                            Open Local Mailpit UI
                            <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
                        </a>

                        <button
                            type="button"
                            onClick={handleSyncBlocklist}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition"
                        >
                            <RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />
                            Sync Disposable Blocklist
                        </button>
                    </div>
                </header>

                {/* Health Gauges Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <HealthGaugeCard
                        title="Bounce Rate"
                        description="Hard bounces vs total dispatches (30 days)"
                        currentRate={health.bounceRate}
                        warningThreshold={0.02} // 2.0% auto-pause
                        blockThreshold={0.05} // 5.0% account block
                        helperText="Amazon SES places accounts under probation if bounce rate exceeds 5.0%. SpiggleMail auto-pauses sends at 2.0%."
                    />

                    <HealthGaugeCard
                        title="Complaint Rate (Spam Reports)"
                        description="User spam reports vs total deliveries (30 days)"
                        currentRate={health.complaintRate}
                        warningThreshold={0.0008} // 0.08% auto-pause
                        blockThreshold={0.003} // 0.3% account block
                        helperText="Google & Yahoo mandate complaint rates below 0.10% (0.001). SpiggleMail auto-pauses dispatches at 0.08%."
                    />
                </div>

                {/* Hygiene & Protection Summary Cards */}
                <section className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="p-5 rounded-xl bg-zinc-900/50 border border-zinc-800">
                        <span className="text-xs uppercase font-semibold text-zinc-500">Disposable Domains Blocked</span>
                        <div className="mt-2 text-2xl font-bold text-white">
                            {health.disposableDomainsBlocked.toLocaleString()}
                        </div>
                        <p className="text-xs text-zinc-400 mt-1">Updated nightly from community feed</p>
                    </div>

                    <div className="p-5 rounded-xl bg-zinc-900/50 border border-zinc-800">
                        <span className="text-xs uppercase font-semibold text-zinc-500">30-Day Dispatches</span>
                        <div className="mt-2 text-2xl font-bold text-white">
                            {health.totalSent30d.toLocaleString()}
                        </div>
                        <p className="text-xs text-zinc-400 mt-1">{health.totalBounced30d} bounces, {health.totalComplaints30d} complaints</p>
                    </div>

                    <div className="p-5 rounded-xl bg-zinc-900/50 border border-zinc-800">
                        <span className="text-xs uppercase font-semibold text-zinc-500">Reputation Status</span>
                        <div className="mt-2 text-2xl font-bold text-emerald-400 flex items-center gap-2">
                            <ShieldCheck className="w-5 h-5" aria-hidden="true" />
                            Pristine
                        </div>
                        <p className="text-xs text-zinc-400 mt-1">Zero active circuit breaks</p>
                    </div>
                </section>

                {/* Suppressions Table */}
                <section className="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
                    <header className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-2.5">
                            <Ban className="w-5 h-5 text-red-400" aria-hidden="true" />
                            <h2 className="text-base font-semibold text-white">Hashed Suppression List</h2>
                        </div>
                        <span className="text-xs text-zinc-400">
                            {suppressions.length} addresses auto-suppressed
                        </span>
                    </header>

                    <p className="text-xs text-zinc-400 mb-4">
                        Suppressed contacts receive zero outbound emails regardless of campaign audience selections, protecting your domain from repeated hard bounces and spam traps.
                    </p>

                    {suppressions.length === 0 ? (
                        <div className="text-center py-8 text-zinc-500 text-sm">
                            No suppressions recorded yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs text-zinc-300">
                                <thead className="bg-zinc-950 text-zinc-400 uppercase font-mono">
                                    <tr>
                                        <th scope="col" className="px-4 py-2.5">Hashed Identifier</th>
                                        <th scope="col" className="px-4 py-2.5">Reason</th>
                                        <th scope="col" className="px-4 py-2.5">Suppressed At</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60 font-mono">
                                    {suppressions.map((s) => (
                                        <tr key={s.id} className="hover:bg-zinc-800/20">
                                            <td className="px-4 py-2.5 text-zinc-400">{s.emailHash.substring(0, 24)}...</td>
                                            <td className="px-4 py-2.5">
                                                <span className="px-2 py-0.5 rounded-full text-[10px] bg-red-500/10 text-red-400 border border-red-500/20">
                                                    {s.reason}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5 text-zinc-500">{s.createdAt}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
